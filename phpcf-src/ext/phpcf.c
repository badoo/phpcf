/* $Id$ */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include <stdarg.h>

#include "php.h"
#include "php_ini.h"
#include "ext/standard/php_smart_str.h"
#include "ext/standard/info.h"
#include "php_phpcf.h"

#include "zend.h"
#include "zend_language_scanner.h"
#include "zend_language_scanner_defs.h"
#include <zend_language_parser.h>

/* internal classes: Phpcf */
static zend_class_entry *phpcf_class_entry;

static zend_object_handlers php_phpcf_handlers;

static phpcf_hashtable *rewrite_callbacks;

static int le_phpcf;

#define zendtext LANG_SCNG(yy_text)
#define zendleng LANG_SCNG(yy_leng)

/**
 * Internal functions
 */

#define FOREACH_FIELDS zval **entry; HashPosition pos; ulong num_key; uint str_key_len; char *string_key;
#define FOREACH_BEGIN(arr) zend_hash_internal_pointer_reset_ex(arr, &pos); \
    while (zend_hash_get_current_data_ex(arr, (void **)&entry, &pos) == SUCCESS) { \
        switch (zend_hash_get_current_key_ex(arr, &string_key, &str_key_len, &num_key, 0, &pos)) {
#define FOREACH_END(arr) \
        } \
        zend_hash_move_forward_ex(arr, &pos); \
    }
#define APPEND_WHITESPACE(text) _append_whitespace(text, TOKEN_HOOK_ARGS);

/**
 * Hash table functions
 */
// Create new hash table
static phpcf_hashtable *ht_init(int size)
{
    phpcf_hashtable *ht = (phpcf_hashtable*)ecalloc(1, sizeof(phpcf_hashtable));
    ht->len = 0;
    ht->cap = size;
    ht->entries = (_phpcf_hashtable_entry*)ecalloc(ht->cap, sizeof(_phpcf_hashtable_entry));

    return ht;
}

/**
 * @TODO add as param to ht_init
 */
static phpcf_hashtable *ht_init_persistent(int size)
{
    phpcf_hashtable *ht = (phpcf_hashtable*)calloc(1, sizeof(phpcf_hashtable));
    ht->len = 0;
    ht->cap = size;
    ht->entries = (_phpcf_hashtable_entry*)calloc(1, ht->cap * sizeof(_phpcf_hashtable_entry));

    return ht;
}

// Add value to hash table
static void ht_add(phpcf_hashtable *ht, int key, void *val)
{
    if (ht->len + 1 > ht->cap) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "PHPCF hash table capacity exceeded");
    }

    int hash = key % (ht->cap - 1);
    while (ht->entries[hash].val) {
        /* if entries[hash].key == key, then we will just update the value */
        if (ht->entries[hash].key == key) break;
        hash++;
        if (hash > ht->cap - 1) hash = 0;
    }

    if (ht->entries[hash].key != key) {
        ht->entries[hash].key = key;
        ht->len++;
    }

    ht->entries[hash].val = val;
}

// Retrieve value from hash table
static void *ht_get(phpcf_hashtable *ht, int key)
{
    int hash = key % (ht->cap - 1);
    while (ht->entries[hash].val && ht->entries[hash].key != key) {
        hash++;
        if (hash > ht->cap - 1) {
        	hash = 0;
        }
    }
    return ht->entries[hash].val;
}

/**
 * Whether message sniffing is enabled
 */
static zend_bool is_sniff_enabled(Formatter *frm)
{
	return frm->options->sniff;
}

/**
 * Gains number of char occurencies into str
 */
static int substr_count(char *str, char symbol)
{
    void *p = (void*)str, *endp = p + strlen(str);
    int cnt = 0;
    while ((p = memchr(p, symbol, endp - p))) {
        cnt++;
        p++;
    }
    return cnt;
}

/*
 stokens = source tokens
 scur    = source tokens current index
 scnt    = source tokens count
 tokens  = rewritten tokens
 cur     = rewritten tokens current index
 buf     = string buffer for writing
 */

static void _append_whitespace(char *whitespace, Formatter *frm, phpcf_token *stokens, int *scur, int scnt, phpcf_token *tokens, int *cur, char **buf)
{
    phpcf_token *src = stokens + *scur, *new = tokens + (++(*cur));

    new->type = T_WHITESPACE;
    strcpy(*buf, whitespace);
    new->val = *buf;
    *buf += strlen(whitespace) + 1;
    new->line = src->line;
}

static int _is_line_aligned(phpcf_token *tokens, int cnt, int line_pos, int line_length, int whitespace_length)
{
    phpcf_token *tok;
    int i, is_str, prev_is_comment, other_line_length = 0;

    for (i = line_pos; i < cnt; i++) {
        tok = tokens + i;
        is_str = tok->type == T_CONSTANT_ENCAPSED_STRING || tok->type == T_ENCAPSED_AND_WHITESPACE;
        prev_is_comment = i > 0 && tok->type == T_WHITESPACE && tokens[i - 1].type == T_COMMENT;
        if (prev_is_comment || (!is_str && strstr(tok->val, "\n"))) break;
        other_line_length += strlen(tok->val);

        if (other_line_length == whitespace_length && tok->type == T_WHITESPACE) {
            // aligned by whitespace
            return 1;
        } else if (other_line_length == line_length && tokens[i - 1].type == T_WHITESPACE) {
            // aligned by tokens!
            return 1;
        } else if (other_line_length > line_length) {
            // no chance
            break;
        }
    }

    return 0;
}

static int _is_prev_line_aligned(phpcf_token *tokens, int cnt, int prev_line_pos, int line_length, int whitespace_length)
{
    phpcf_token *tok;
    int i, is_str, prev_is_comment;

    for (i = prev_line_pos; i >= 0; i--) { // find beginning of line
        tok = tokens + i;
        is_str = tok->type == T_CONSTANT_ENCAPSED_STRING || tok->type == T_ENCAPSED_AND_WHITESPACE || tok->type == T_COMMENT;
        prev_is_comment = i > 0 && tok->type == T_WHITESPACE && tokens[i - 1].type == T_COMMENT;
        if (prev_is_comment || (!is_str && strstr(tok->val, "\n"))) break;
    }

    if (i < prev_line_pos) {
        return _is_line_aligned(tokens, cnt, i + 1, line_length, whitespace_length);
    }

    return 0;
}

static int _is_next_line_aligned(phpcf_token *tokens, int cnt, int next_line_pos, int line_length, int whitespace_length)
{
    phpcf_token *tok;
    int i, is_str, prev_is_comment;
    for (i = next_line_pos; i < cnt; i++) { // find beginning of line
        tok = tokens + i;
        is_str = tok->type == T_CONSTANT_ENCAPSED_STRING || tok->type == T_ENCAPSED_AND_WHITESPACE;
        prev_is_comment = i > 0 && tok->type == T_WHITESPACE && tokens[i - 1].type == T_COMMENT;
        if (prev_is_comment || (!is_str && strstr(tok->val, "\n"))) break;
    }

    if (i > next_line_pos) {
        phpcf_token *tmp;
        while(i < cnt) {
        	tmp = &tokens[i];
        	if (tmp->type == T_WHITESPACE) {
        		break;
        	}
        	++i;
        }
        return _is_line_aligned(tokens, cnt, i + 1, line_length, whitespace_length);
    }

    return 0;
}

static int _is_whitespace_string(char *str)
{
    do {
        if (!isspace(*str)) return 0;
    } while(*(str++));

    return 1;
}

static int _tokens_is_whitespace_aligned(phpcf_token *tokens, int cur, int cnt)
{
    phpcf_token *tok = tokens + cur;
    int next_pos = cur + 1, whitespace_length, line_length, i, is_str, prev_is_comment, is_aligned;
    // either it's EOF or whitespace has tabs/newlines or it has length less than 2
    if (next_pos >= cnt || tok->val[0] != ' ' || tok->val[1] != ' ') return 0;
    for (i = 0; tok->val[i]; i++) if (tok->val[i] != ' ') return 0;

    // search for beginning of line and count length of line before this whitespace
    whitespace_length = line_length = 0;
    for (i = next_pos; i >= 0; i--) {
        tok = tokens + i;
        is_str = tok->type == T_CONSTANT_ENCAPSED_STRING || tok->type == T_ENCAPSED_AND_WHITESPACE;
        prev_is_comment = i > 0 && tok->type == T_WHITESPACE && tokens[i - 1].type == T_COMMENT;
        if (prev_is_comment || (!is_str && strstr(tok->val, "\n"))) break; // this will skip the indent as well, we do not care
        line_length += strlen(tok->val);
        if (i != next_pos) whitespace_length += strlen(tok->val);
    }

    int prev, next;
    is_aligned = prev = next = 0;
    if (i > 0)       is_aligned = prev =_is_prev_line_aligned(tokens, cnt, i - 1, line_length, whitespace_length);
    if (!is_aligned) is_aligned = next = _is_next_line_aligned(tokens, cnt, next_pos, line_length, whitespace_length);

    //printf("%d - %d \n", prev, next);
    return is_aligned;
}

static int _count_expr_length(phpcf_token *tokens, int cur, int cnt, int found_first_bracket, int max_length)
{
    int length = 0, depth = found_first_bracket ? 1 : 0;
    int current_pos = cur + 1, i = 0;
    phpcf_token tok;

    for (i = current_pos; i < cnt; i++) {
        tok = tokens[i];

        if (!found_first_bracket) {
            if (tok.type == '(' || tok.type == '[') found_first_bracket = 1;
            else                 continue;
        }

        // allow having long array definitions if user has decided that there should be an array in long form
        if (tok.type == T_WHITESPACE && strstr(tok.val, "\n")) {
            return max_length + 1;
        }

        length += strlen(tok.val);

        if (length > max_length) break;

        if (tok.type == '(') depth++;
        if (tok.type == ')') depth--;
        if (tok.type == '[') depth++;
        if (tok.type == ']') depth--;
        if (depth <= 0) break;
    }

    return length;
}

static void _print_indent_descr(smart_str *str, char *indent, char *buf, int len)
{
    int i = 0;
    char tmp[100];

    for (i = 0; i < len; i++) {
        if (buf[i] == ' ') {
        	continue;
        }
        smart_str_appends(str, "'");
        smart_str_appendl(str, buf, len);
        smart_str_appends(str, "'");
        return;
    }

    if (!len) {
    	smart_str_appends(str, "no indent");
    } else if (len % (sizeof(indent) - 1) == 0) {
        sprintf(tmp, "indent level %ld", len / (sizeof(indent) - 1));
        smart_str_appends(str, tmp);
    } else {
    	sprintf(tmp, "indent of %ld spaces", len / (sizeof(indent) - 1));
    	smart_str_appends(str, tmp);
    }
    int d = 1;
}


/**
 * Smart string destructor
 */
static void issue_dtor(void *data)
{
	char *issue = *(char **)data;

	if (issue) {
		efree(issue);
	}
}

/**
 * Sniff execution message
 */
static void sniff_message(Formatter *frm, int line, char *fmt, ...)
{
    int size, ret;
    va_list ap;
    char *buffer;
    char *prepend = "    ";

    if(!is_sniff_enabled(frm)) {
    	return;
    }

    smart_str str = {0};

    smart_str_appends(&str, prepend);

    va_start(ap, fmt);
    size = vspprintf(&buffer, 0, fmt, ap);
    smart_str_appends(&str, buffer);
    va_end(ap);

    sprintf(buffer, "on line %d", line);

    smart_str_appends(&str, buffer);
    smart_str_0(&str);
    efree(buffer);

    zend_hash_next_index_insert(frm->ctx->sniff, &str.c, sizeof(char *), NULL);
}

static void sniff_context_message(Formatter *frm, char *in, char *out, int origin, char *descr, int token_pos, phpcf_token *tokens, int cnt)
{
    phpcf_token token, tok;
    int token_line, ln, col, i, outlen, inlen;
    char *contents, *tmpbuf, *outbuf, *inbuf;
    static char buffer[300];

    char *indent = frm->options->indent;

    if (!is_sniff_enabled(frm)) {
    	return;
    }

    if (strlen(in) && origin) {
        if (origin == EXEC_SEQ_ORIGIN_LEFT) {
        	token_pos--;
        }
        else {
        	token_pos++;
        }
    }

    if (token_pos < 0) token_pos = 0;
    else if (token_pos >= cnt) token_pos = cnt - 1;

    token = tokens[token_pos];
    token_line = token.line;

    if (!substr_count(out, '\n') || substr_count(out, '\n') != substr_count(in, '\n')) {
        col = 1;
        for (i = token_pos - 1; i >= 0; i--) {
            tok = tokens[i];
            contents = tok.val;
            if (strstr(contents, "\n")) {
                while (NULL != (tmpbuf = strstr(contents, "\n"))) contents = tmpbuf + 1;
                col += strlen(contents);
                break;
            } else {
                col += strlen(contents);
            }
        }
        if (!strlen(in) && origin == EXEC_SEQ_ORIGIN_RIGHT) {
            col += strlen(token.val);
        }

        smart_str str = {0};
        sprintf(buffer, "    Expected %c%s ", tolower(descr[0]), descr + 1);
        smart_str_appends(&str, buffer);

        if (NULL != (tmpbuf = strstr(out, "\n"))) {
            tmpbuf += 1;
            smart_str_appends(&str, "and ");
            _print_indent_descr(&str, indent, tmpbuf, strstr(tmpbuf, "\n") ? strstr(tmpbuf, "\n") - tmpbuf : strlen(tmpbuf));
        }

        sprintf(buffer, " on line %d column %d", token_line, col);
        smart_str_appends(&str, buffer);
        smart_str_0(&str);
		zend_hash_next_index_insert(frm->ctx->sniff, &str.c, sizeof(char *), NULL);

    } else {
        i = 0;
        outbuf = out;
        inbuf = in;

        do {
        	smart_str str = {0};

            if (inbuf) {
                inlen = strstr(inbuf, "\n") ? strstr(inbuf, "\n") - inbuf : strlen(inbuf);
            }

            if (outbuf) {
                outlen = strstr(outbuf, "\n") ? strstr(outbuf, "\n") - outbuf : strlen(outbuf);
            }

            if (!inbuf || !outbuf || strncmp(inbuf, outbuf, outlen) || (inbuf[outlen] != '\n' && inbuf[outlen] != 0)) {

            	smart_str_appends(&str, "    Expected ");

                if (i == 0) {
                	smart_str_appends(&str, "'");
                    if (outbuf) {
                    	smart_str_appendl(&str, outbuf, outlen);
                    }
                    smart_str_appends(&str, "', got '");
                    if (inbuf) {
                    	smart_str_appendl(&str, inbuf, inlen);
                    }
                    sprintf(buffer, "' at the end of line %d", token_line + i);
                    smart_str_appends(&str, buffer);
                } else {
                    if (outbuf) {
                    	_print_indent_descr(&str, indent, outbuf, outlen);
                    }
                    else {
                    	smart_str_appends(&str, "nothing");
                    }
                    smart_str_appends(&str, ", got ");
                    if (inbuf) {
                    	_print_indent_descr(&str, indent, inbuf, inlen);
                    }
                    else {
                    	smart_str_appends(&str, "nothing");
                    }
                    sprintf(buffer, " on line %d column %d", token_line + i, inlen + 1);
                    smart_str_appends(&str, buffer);
                }

                smart_str_0(&str);
                zend_hash_next_index_insert(frm->ctx->sniff, &str.c, sizeof(char *), NULL);
            }

            outbuf = strstr(outbuf, "\n");
            if (outbuf) outbuf += 1;
            inbuf = strstr(inbuf, "\n");
            if (inbuf) inbuf += 1;
            i++;

        } while (outbuf || inbuf);
    }
}

/**
 * Check, if in current block, before close_symbol there are any other symbols,
 * except spaces or comments
 */
int _is_body_empty(phpcf_token *stokens, int *scur, int scnt, int close_symbol)
{
	phpcf_token *tok;
    int next_pos = *scur + 1, i;

    int found = 0;

    for (i = next_pos; i < scnt; i++) {
        tok = &stokens[i];
        // found closing brace
        if(tok->type==close_symbol) {
            found = 1;
            break;
        }
        else if (SHOULD_IGNORE_TOKEN(tok->type)) {
        // it is blank symbol
            continue;
        }
        else {
        // something non-blank
            break;
        }
    }
    return found;
}

/**
 * Init map of tokens to their callbacks
 */
static void init_rewrite_callbacks(void)
{
    rewrite_callbacks = ht_init_persistent(100);
    ht_add(rewrite_callbacks, T_OPEN_TAG,        token_hook_open_tag);
    ht_add(rewrite_callbacks, T_CLOSE_TAG,       token_hook_close_tag);
    ht_add(rewrite_callbacks, T_WHITESPACE,      token_hook_whitespace);
    ht_add(rewrite_callbacks, T_FUNCTION,        token_hook_function);
    ht_add(rewrite_callbacks, T_STRING,          token_hook_tstring);
    ht_add(rewrite_callbacks, T_COMMENT,         token_hook_comment);
    ht_add(rewrite_callbacks, T_VAR,             token_hook_classdef);
    ht_add(rewrite_callbacks, T_PUBLIC,          token_hook_classdef);
    ht_add(rewrite_callbacks, T_PROTECTED,       token_hook_classdef);
    ht_add(rewrite_callbacks, T_PRIVATE,         token_hook_classdef);
    ht_add(rewrite_callbacks, T_CONST,           token_hook_classdef);
    ht_add(rewrite_callbacks, T_FINAL,           token_hook_classdef);
    ht_add(rewrite_callbacks, T_ELSE,            token_hook_else);
    ht_add(rewrite_callbacks, T_ELSEIF,          token_hook_else);
    ht_add(rewrite_callbacks, T_INC,             token_hook_increment);
    ht_add(rewrite_callbacks, T_DEC,             token_hook_increment);
    ht_add(rewrite_callbacks, T_STATIC,          token_hook_static);
    ht_add(rewrite_callbacks, T_START_HEREDOC,   token_hook_heredoc);
    ht_add(rewrite_callbacks, T_OBJECT_OPERATOR, token_hook_binary);
    ht_add(rewrite_callbacks, T_BOOLEAN_AND,     token_hook_binary);
    ht_add(rewrite_callbacks, T_BOOLEAN_OR,      token_hook_binary);
    ht_add(rewrite_callbacks, T_LOGICAL_OR,      token_hook_binary);
    ht_add(rewrite_callbacks, T_LOGICAL_AND,     token_hook_binary);
    ht_add(rewrite_callbacks, T_VARIABLE,	     token_hook_variable);
    ht_add(rewrite_callbacks, '.',               token_hook_binary);
    ht_add(rewrite_callbacks, '`',               token_hook_str);
    ht_add(rewrite_callbacks, '"',               token_hook_str);
    ht_add(rewrite_callbacks, '(',               token_hook_open_brace);
    ht_add(rewrite_callbacks, '+',               token_hook_check_unary);
    ht_add(rewrite_callbacks, '-',               token_hook_check_unary);
    ht_add(rewrite_callbacks, '&',               token_hook_check_unary);
    ht_add(rewrite_callbacks, ',',               token_hook_comma);
    ht_add(rewrite_callbacks, '?',               token_hook_ternary_begin);
    ht_add(rewrite_callbacks, '{',               token_hook_curly_brace);
    ht_add(rewrite_callbacks, '[',               token_hook_square_brace);
    ht_add(rewrite_callbacks, T_ARRAY,           token_hook_array);
}

/**
 * Can line be changed within current formatting ctx
 */
static int can_change_lines(formatting_ctx *ctx, int line)
{
	int retval = !zend_hash_num_elements(ctx->user_lines) || zend_hash_index_exists(ctx->user_lines, line);

	return retval;
}

/**
 * Hooks
 */

TOKEN_HOOK(token_hook_whitespace)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur;
    new->type = _tokens_is_whitespace_aligned(stokens, *scur, scnt) ? T_WHITESPACE_ALIGNED : T_WHITESPACE;
    new->val  = src->val;
    new->line = src->line;
}

TOKEN_HOOK(token_hook_array)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int is_normal_context = 0, next_pos = *scur + 1, i;

    new->type = src->type;
    new->val  = src->val;
    new->line = src->line;

    for (i = next_pos; i < scnt; i++)
    {
       tok = stokens[i];
	   if (SHOULD_IGNORE_TOKEN(tok.type)) {
			   continue;
	   }
	   // it is array hint
	   else if( tok.type == T_VARIABLE || tok.type == '&') {
		   new->type = T_ARRAY_HINT;
		   break;
	   }
	   else {
		   break;
	   }
    }
}


// hook opening brace for another type - {_EMPTY - opens block without content
TOKEN_HOOK(token_hook_curly_brace)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;

    int found = _is_body_empty(stokens, scur, scnt, '}');

    if(found) {
        new->type = TOK_EMPTY(src->type);
    }
    else {
        new->type = src->type;
    }

    new->val  = src->val;
    new->line = src->line;
}

/**
 * Rewrite T_VARIABLE to T_FUNCTION_NAME, when it is function call like
 * $foo() or Class::$call()
 */
TOKEN_HOOK(token_hook_variable)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int type = src->type, next_pos = *scur + 1, i;

    for (i = next_pos; i < scnt; i++) {
        tok = stokens[i];
        if (SHOULD_IGNORE_TOKEN(tok.type)) continue;
        // in situations like '++ $a' there would be variable on the right, so the token is positioned left
        if (tok.type == '(') {
        	type = T_FUNCTION_NAME;
        }
        break;
    }

    new->type = type;
    new->val  = src->val;
    new->line = src->line;
}

/**
 * rewrite [ to T_SHORT_ARRAY(_ML), if necessary
 */
TOKEN_HOOK(token_hook_square_brace)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int prev_pos, i;
    prev_pos = *scur;

    new->type = src->type;
    new->val  = src->val;
    new->line = src->line;

    for(i=prev_pos -1; i>0; i--)
    {
    	tok = stokens[i];
		if (SHOULD_IGNORE_TOKEN(tok.type)) {
			continue;
		}
		// it is dereferencing, ok
		else if( tok.type == ']' || tok.type == ')' || tok.type == T_CONSTANT_ENCAPSED_STRING || tok.type == T_VARIABLE) {
			break;
		}
		else {
			new->type = T_ARRAY_SHORT;
			break;
		}
    }

    // add multilines, if needed
    if (new->type == T_ARRAY_SHORT) {
        int length = _count_expr_length(stokens, *scur, scnt, 1, frm->options->max_line_length);
        if (length >= frm->options->max_line_length) {
        	new->type = T_ARRAY_SHORT_ML;
        }
    }
}

int _is_long_comma(formatting_ctx *ctx, phpcf_token *tokens, int curr_pos, int cnt, int max_length, int remember_positions)
{
    int len = 0, depth = 0, is_long, i;
    phpcf_token tok;
    // getting first right non-whitespace token length (only if it is not )
    for (i = curr_pos + 1; i < cnt; i++) {
        tok = tokens[i];
        if (tok.type == T_WHITESPACE) {
            // if there already is a new line after comma, we will keep it and remember its position
            if (strstr(tok.val, "\n")) {
                if (remember_positions) {
                	ctx->last_long_position = curr_pos;
                }
                return 1;
            }
            continue;
        }
        if (tok.type == ')') break;

        len += strlen(tok.val);
        break;
    }

    // going backwards to see if there already is more than 120 symbols in line
    for (i = curr_pos; i > 0; i--) {
        // we can remember that which comma was long and
        // count the next long comma only starting from the previous long one
        if (i == ctx->last_long_position) {
        	break;
        }
        tok = tokens[i];

        // we should stop when line beginning is reached or in another special case described below
        if (strstr(tok.val, "\n"))    break;
        if (tok.type == T_WHITESPACE) continue;
        if (tok.type == ',')          len += 2;
        else if (tok.type == '(')     depth--;
        else if (tok.type == ')')     depth++;
        else                          len += strlen(tok.val);

        // found opening "(" before reaching max length, which means it is something like this:
        // array( 'something', some_func(argument100, argument200), 'something else' )
        //                              ^ our brace  ^ our comma
        // such comma must not be long even though the length before newline can be higher than max.
        // comma must be directly related to the array (or argument list), but in this case it does not
        if (depth < 0) return 0;
        if (depth == 0 && len >= max_length) break;
    }

    is_long = (len >= max_length);
    if (remember_positions && is_long) {
    	ctx->last_long_position = curr_pos;
    }
    return is_long;
}

TOKEN_HOOK(token_hook_comma)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int type = src->type;

    if (_is_long_comma(frm->ctx, stokens, *scur, scnt, frm->options->max_line_length, 1)) {
    	type = TOK_LONG(type);
    }

    new->type = type;
    new->val  = src->val;
    new->line = src->line;
}

TOKEN_HOOK(token_hook_open_brace)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur;
    int length = _count_expr_length(stokens, *scur, scnt, 1, frm->options->max_line_length);
    new->type = '(';
    if (length >= frm->options->max_line_length) {
    	new->type = TOK_LONG(new->type);
    }

    int can_change_tokens = can_change_lines(frm->ctx, src->line);
    if (can_change_tokens && _is_body_empty(stokens, scur, scnt, ')')) {
    	new->type = '(';
    	new->type = TOK_EMPTY('(');
    }

    new->val  = src->val;
    new->line = src->line;
}

/* text inside "strings" (and `strings`) is also tokenized in PHP by default: we do not care about these */
TOKEN_HOOK(token_hook_str)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int q = src->type, i;
    char *string_buf = *buf;

    new->type = src->type;
    new->val  = src->val;
    new->line = src->line;

    for (;;) {
        src = stokens + (++*scur);
        if (src->type == q) {
            (*buf)++;
            **buf = 0;

            new = tokens + (++*cur);
            new->type = T_STRING_CONTENTS;
            new->val  = string_buf;
            new->line = src->line;
            break;
        }

        strcpy(*buf, src->val);
        *buf += strlen(src->val);
    }

    new = tokens + (++*cur);
    new->type = src->type;
    new->val  = src->val;
    new->line = src->line;
}

/* rewrite ?: as a single token */
TOKEN_HOOK(token_hook_ternary_begin)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    if (*scur + 1 < scnt && (stokens + *scur + 1)->type == ':') {
        (*scur)++;
        new->type = T_TERNARY_GLUED;
        new->val  = "?:";
    } else {
        new->type = src->type;
        new->val  = src->val;
    }

    new->line = src->line;
}

/* text inside HEREDOCs is tokenized in PHP by default, which is not what we need at all */
TOKEN_HOOK(token_hook_heredoc)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int i;
    char *string_buf = *buf;

    new->type = src->type;
    new->val  = src->val;
    new->line = src->line;

    for (;;) {
        src = stokens + (++*scur);
        if (src->type == T_END_HEREDOC) {
            (*buf)++;
            **buf = 0;

            new = tokens + (++*cur);
            new->type = T_HEREDOC_CONTENTS;
            new->val  = string_buf;
            new->line = src->line;
            break;
        }

        strcpy(*buf, src->val);
        *buf += strlen(src->val);
    }

    new = tokens + (++*cur);
    new->type = src->type;
    new->val  = src->val;
    new->line = src->line;
}

// interpret "static" in "static::HELLO" as normal text (T_STRING) instead of keyword (T_STATIC)
TOKEN_HOOK(token_hook_static)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int is_normal_context = 0, next_pos = *scur + 1, i;

    for (i = next_pos; i < scnt; i++) {
        tok = stokens[i];
        if (SHOULD_IGNORE_TOKEN(tok.type)) continue;
        switch (tok.type) {
            case T_VARIABLE: // static var; protected static var;
            case T_PROTECTED: case T_PUBLIC: case T_PRIVATE: case T_FINAL: case T_VAR: case T_ABSTRACT: // static protected var;
            case T_FUNCTION: // static function() { ... }
                is_normal_context = 1;
                break;
        }

        break;
    }

    if (is_normal_context) {
        return token_hook_classdef(TOKEN_HOOK_ARGS);
    }

    new->type = T_STRING;
    new->val  = src->val;
    new->line = src->line;
}

TOKEN_HOOK(token_hook_function)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;

    int next_pos = *scur + 1, length = 0, depth = 0, i = 0;
    int is_anonymous = 1, found_arguments_begin = 0, found_open_bracket = 0;
    int type = T_FUNCTION;

    for (i = next_pos; i < scnt; i++) {
        tok = stokens[i];

        length += strlen(tok.val);
        if (!found_open_bracket) {
            if (SHOULD_IGNORE_TOKEN(tok.type)) continue;
            // function doSomethingGood(
            //          ^ T_STRING     ^ arguments begin here
            if (tok.type == T_STRING && !found_arguments_begin) is_anonymous = 0;
            if (tok.type == '(') found_arguments_begin = 1;
            if (tok.type == '{') {
                found_open_bracket = 1;
                depth = 1;
            }
        } else {
            if (tok.type == '{') depth++;
            if (tok.type == '}') depth--;
            if (depth <= 0) break;
            // if someone wants to put function declaration on several lines, so be it
            if (strstr(tok.val, "\n")) {
                length = frm->options->max_line_length;
                break;
            }
        }
    }

    if (is_anonymous) {
        if (length >= frm->options->max_line_length) {
        	type = T_ANONFUNC_LONG;
        }
        else {
        	type = T_ANONFUNC;
        }
    }

    new->type = type;
    new->val  = src->val;
    new->line = src->line;
}

TOKEN_HOOK(token_hook_close_tag)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int is_eof = 1, can_delete = 1, next_pos = *scur + 1, i = 0, curr_pos, prev_pos;
    int can_change_tokens = can_change_lines(frm->ctx, src->line);

    if (can_change_tokens) {
        if (next_pos < scnt) {
            for (i = next_pos; i < scnt; i++) {
                tok = stokens[i];
                if (SHOULD_IGNORE_TOKEN(tok.type)) continue;
                if (tok.type == T_INLINE_HTML && _is_whitespace_string(tok.val)) continue;
                is_eof = 0;
                break;
            }
            curr_pos = next_pos - 1;
        } else {
            curr_pos = scnt - 1;
        }

        if (is_eof) {
            prev_pos = curr_pos - 1;
            if (stokens[prev_pos].type == T_WHITESPACE) prev_pos--;
            if (stokens[prev_pos].type != ';' && stokens[prev_pos].type != '}') {
                sniff_message(frm, src->line, "Expected either ';' or '}' before closing tag for it to be safely deleted");
                can_delete = 0;
            }
        }

        if (is_eof && can_delete) {
            sniff_message(frm, src->line, "No close tag allowed at the end of file");
            (*cur) -= 1;
            return;
        }
    }

    new->type = src->type;
    new->val  = src->val;
    new->line = src->line;
}

TOKEN_HOOK(token_hook_open_tag)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur;
    int append_offset = 0;
    int can_change_tokens = can_change_lines(frm->ctx, src->line);

    new->type = src->type;
    new->line = src->line;

    if (can_change_tokens) new->val  = "<?php";

    if (!strncmp("<?php", src->val, 5)) {
        append_offset = 5;
    } else {
        append_offset = 2; // short open tag: "<?"
    }

    if (!can_change_tokens) {
        strncpy(*buf, src->val, append_offset);
        new->val = *buf;
        new->val[append_offset] = 0;
        *buf += append_offset + 1;
    }

    if (src->val[append_offset]) {
        APPEND_WHITESPACE(src->val + append_offset);
    }
}

TOKEN_HOOK(token_hook_check_unary)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int is_unary = 0, i;

    for (i = *scur - 1; i > 0; i--) {
        tok = stokens[i];
        if (SHOULD_IGNORE_TOKEN(tok.type)) continue;

        switch(tok.type) {
            case ']':
            case ')':
            case T_LNUMBER:
            case T_DNUMBER:
            case T_VARIABLE:
            case T_STRING:
                break;
            default:
                is_unary = 1;
                break;
        }
        break;
    }

    new->type = is_unary ? TOK_UNARY(src->type) : src->type;
    new->val  = src->val;
    new->line = src->line;
}

TOKEN_HOOK(token_hook_tstring)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int type = T_STRING, i;
    zval *names_callback = frm->string_filter;

    for (i = *scur + 1; i < scnt; i++) {
        tok = stokens[i];
        if (SHOULD_IGNORE_TOKEN(tok.type)) continue;
        if (tok.type == '(') type = T_FUNCTION_NAME;
        break;
    }

    // checking for non-ascii symbol presence
    int sizer = sizeof(src->val);
    int contains = 0;
    int j;
    for ( j=0; j<sizer; j++)
    {
    	char sym = src->val[j];
    	// valid symbols for classes, functions etc. are a-zA-Z_
    	if ( ! ( (sym >= 'a' && sym <= 'z' ) || (sym >= 'A' && sym >= 'Z') || (sym >= '0' && sym <= '9') || sym == '_' ) ) {
    		contains = 1;
    		break;
    	}
    }

    // value to return
    char *value_to_assign = src->val;

    // executing user-defined callback for string validation
    if (contains && names_callback &&  NULL != names_callback) {
    	zval *retval_ptr;
    	zval **argv[1];
    	zval *val;
    	MAKE_STD_ZVAL(val);
    	ZVAL_STRING(val, src->val, 1);
    	argv[0]= &val;

    	zval *local = names_callback;
    	int call_res = call_user_function_ex(EG(function_table), NULL, names_callback, &retval_ptr, 1, argv, 0, NULL TSRMLS_CC);
    	if(FAILURE == call_res) {
    		zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "Error invoking T_STRING callback");
    	}
    	else if(IS_ARRAY == Z_TYPE_P(retval_ptr)) {
    		// extracting data from ["newString", "debugMessage"]
    		HashTable *ht = Z_ARRVAL_P(retval_ptr);
    		zval **tmpzval = NULL;

    	    if( FAILURE == zend_hash_index_find(ht, 0, (void **) &tmpzval) ) {
    	    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "Error getting result string from callback result");
    	    }
    	    else {
    	    	value_to_assign = Z_STRVAL_PP(tmpzval);
    	    	// try find debug message
        	    if ( SUCCESS == zend_hash_index_find(ht, 1, (void **) &tmpzval) && Z_STRLEN_PP(tmpzval) != 0 ) {
        	    	sniff_message(frm, src->line, Z_STRVAL_PP(tmpzval));
        	    }
    	    }
    	}
    }

    new->type = type;
    new->val  = value_to_assign;
    new->line = src->line;
}

TOKEN_HOOK(token_hook_comment)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int type = src->type, prev_pos, i = 0, l = 0;
    int can_change_tokens = can_change_lines(frm->ctx, src->line);

    if (can_change_tokens && src->val[0] == '#') {
        sniff_message(frm, src->line, "Comments starting with '#' are not allowed");
        new->val = *buf;
        strcpy(new->val, "//");
        strcat(new->val, src->val + 1);
        *buf += strlen(new->val) + 1;
    } else {
        new->val = *buf;
        strcpy(new->val, src->val);
        *buf += strlen(new->val) + 1;
    }

    if (src->val[0] == '#' || (src->val[0] == '/' && src->val[1] == '/')) {
        // if previous token is whitespace with line feed, then it means that this comment takes whole line
        prev_pos = *scur - 1; // otherwise it is appended to some expression like this one
        if (prev_pos > 1 && stokens[prev_pos].type == T_WHITESPACE && strstr(stokens[prev_pos].val, "\n")) {
            type = T_SINGLE_LINE_COMMENT_ALONE;
        } else {
            type = T_SINGLE_LINE_COMMENT;
        }

        while (new->val[i]) {
            if (new->val[i] == '\n') {
                new->val[i] = 0;
                break;
            }
            i++;
        }
    }

    new->type = type;
    new->line = src->line;

    if (type == T_COMMENT) return; // do not touch multiline comments

    APPEND_WHITESPACE("\n");
}

TOKEN_HOOK(token_hook_classdef)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int type = src->type, next_pos = *scur + 1, i;
    int is_property_def = 0;
    int is_newline = 0; // whether or not definition is on a new line

    for (i = next_pos; i < scnt; i++) {
        tok = stokens[i];
        // look for whitespace, comment, variable or constant name after, e.g. "private"
        // if nothing was found, then it is end of property def (or real property def did not begin)
        if (tok.type == T_VARIABLE || tok.type == T_STRING) is_property_def = 1;
        else if (tok.type != T_COMMENT && tok.type != T_WHITESPACE) break;
        if (strstr(tok.val, "\n")) is_newline = 1;
    }

    if (is_property_def && is_newline) type = TOK_NL(type);

    new->type = type;
    new->val  = src->val;
    new->line = src->line;
}

TOKEN_HOOK(token_hook_binary)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int type = src->type, next_pos = *scur + 1, prev_pos = *scur - 1;

    if (stokens[next_pos].type == T_WHITESPACE && strstr(stokens[next_pos].val, "\n")) {
        type = TOK_NL(type);
    } else {
    	// searching for comment or whitespace with \n
    	while(prev_pos > 0) {
    		// non-comments and whitespaces does not cause new line
    		if (!SHOULD_IGNORE_TOKEN(stokens[prev_pos].type)) {
    			break;
    		}
        	if( strstr(stokens[prev_pos].val, "\n") ) {
        		type = TOK_NL(type);
        		break;
        	}
        	prev_pos--;
    	}
    }

    new->type = type;
    new->val  = src->val;
    new->line = src->line;
}

TOKEN_HOOK(token_hook_else)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int type = src->type, prev_pos = *scur - 1, has_block_before = 0, i;

    for (i = prev_pos; i > 0; i--) {
        tok = stokens[i];
        if (SHOULD_IGNORE_TOKEN(tok.type)) continue;
        if (tok.type == '}') has_block_before = 1;
        break;
    }

    if (!has_block_before) type = TOK_INLINE(type);

    new->type = type;
    new->val  = src->val;
    new->line = src->line;
}

// T_INC => T_INC_LEFT || T_INC_RIGHT ( T_INC_LEFT in case "++$a", T_INC_RIGHT in case "$a++")
TOKEN_HOOK(token_hook_increment)
{
    phpcf_token *src = stokens + *scur, *new = tokens + *cur, tok;
    int type = src->type, next_pos = *scur + 1, is_left = 0, i;

    for (i = next_pos; i < scnt; i++) {
        tok = stokens[i];
        if (SHOULD_IGNORE_TOKEN(tok.type)) continue;
        // in situations like '++ $a' there would be variable on the right, so the token is positioned left
        if (tok.type == T_VARIABLE) is_left = 1;
        break;
    }

    type = is_left ? TOK_LEFT(type) : TOK_RIGHT(type);
    new->type = type;
    new->val  = src->val;
    new->line = src->line;
}

static int phpcf_tokenize(int source_len, char *buf, phpcf_token *tokens)
{
    zval token;
    zval *keyword;
    int token_type;
    zend_bool destroy;
    int token_line = 1, i = -1;
    void *p, *endp;

    char *cur_buf = buf;

    ZVAL_NULL(&token);
    while ((token_type = lex_scan(&token TSRMLS_CC))) {
        i++;
        destroy = 1;
        switch (token_type) {
            case T_CLOSE_TAG:
                if (zendtext[zendleng - 1] != '>') {
                    CG(zend_lineno)++;
                }
            case T_OPEN_TAG:
            case T_OPEN_TAG_WITH_ECHO:
            case T_WHITESPACE:
            case T_COMMENT:
            case T_DOC_COMMENT:
                destroy = 0;
                break;
        }

        if (token_type >= 256) {
            if (token_type == T_END_HEREDOC) {
                if (CG(increment_lineno)) {
                    token_line = ++CG(zend_lineno);
                    CG(increment_lineno) = 0;
                }
            }

            strncpy(cur_buf, (const char*)zendtext, zendleng);
            tokens[i].val = cur_buf;
            cur_buf[zendleng] = 0;
            cur_buf += zendleng + 2;

            tokens[i].type = token_type;
            tokens[i].line = token_line;
            p = (void*)zendtext;
            endp = p + zendleng;
            // token_line += substr_count(zendtext, "\n")
            while ((p = memchr(p, '\n', endp - p))) {
                token_line++;
                p++;
            }
        } else {
            tokens[i].type = token_type;
            strncpy(cur_buf, (const char*)zendtext, zendleng);
            tokens[i].val = cur_buf;
            cur_buf[zendleng] = 0;
            cur_buf += zendleng + 2;
            tokens[i].line = token_line;
        }
        if (destroy && Z_TYPE(token) != IS_NULL) {
            zval_dtor(&token);
        }
        ZVAL_NULL(&token);

        if (token_type == T_HALT_COMPILER) {
            break;
        }
    }

    return i + 1;
}

static void _append_indent(char *buf, int indent_count, char *indent)
{
    int i, indent_length = strlen(indent);
    if (indent_count > 20) {
    	indent_count = 20; // protection from buffer overflow
    }
    buf += strlen(buf);
    for (i = 0; i < indent_count; i++) {
        strcat(buf, indent);
        buf += indent_length;
    }
}


static void _apply_exec_sequence(phpcf_exec_seq **exec_sequence, int seq_len, int line, phpcf_token *tokens, int tokens_cnt, int *indent, smart_str *result, Formatter *frm)
{
    static char out[100];
    formatting_ctx *ctx = frm->ctx;
    phpcf_exec_seq *cur;
    phpcf_exec_rule *rule = NULL, *cur_rule = NULL, *erule;
    int i, tok_idx, origin;
    char *in = "";
    char *indent_seq = frm->options->indent;

    out[0] = 0;
    for (i = 0; i < seq_len; i++) {
        cur = exec_sequence[i];

        #if 0
        if (cur->type == EXEC_SEQ_TYPE_EXEC_RULE) {
            printf("Rule (indent level %d):\n", *indent);
            erule = &(cur->seq_data.rule);
            if (erule->descr)     printf("  descr: %s\n", erule->descr);
            if (erule->ex_indent) printf("  ex indent: %d\n", erule->ex_indent);
            if (erule->ex)        printf("  ex: %d\n", erule->ex);
        } else {
            printf("Text: '%s'\n", cur->seq_data.tok->val);
        }
        #endif

        if (cur->type == EXEC_SEQ_TYPE_TOKEN) {
            in = cur->seq_data.tok->val;
            continue;
        }

        cur_rule = &(cur->seq_data.rule);
        if (!rule) {
            rule = cur_rule;
            tok_idx = cur->tok_idx;
            origin = cur->origin;
        }
        *indent += cur_rule->ex_indent;
        if (cur_rule->ex && cur_rule->ex < rule->ex) {
            rule = cur_rule; // rules with lower value have higher priority
            tok_idx = cur->tok_idx;
            origin = cur->origin;
        }
    }

    int can_change_tokens = can_change_lines(ctx, line);
    if (!can_change_tokens || !rule || !rule->ex || rule->ex == PHPCF_EX_DO_NOT_TOUCH_ANYTHING) {
        smart_str_appends(result, in);
        return;
    }

    switch(rule->ex) {
        case PHPCF_EX_CHECK_NL:
        case PHPCF_EX_CHECK_NL_STRONG:
            // allow 2 new lines
            if (substr_count(in, '\n') >= 2) {
                strcat(out, "\n\n");
                _append_indent(out, *indent, indent_seq);
            } else {
                strcat(out, "\n");
                _append_indent(out, *indent, indent_seq);
            }
            break;
        case PHPCF_EX_DELETE_SPACES:
        case PHPCF_EX_DELETE_SPACES_STRONG:
            out[0] = 0;
            break;
        case PHPCF_EX_SHRINK_SPACES:
        case PHPCF_EX_SHRINK_SPACES_STRONG:
            out[0] = ' ';
            out[1] = 0;
            break;
        case PHPCF_EX_SHRINK_NLS_2:
            strcat(out, "\n\n");
            _append_indent(out, *indent, indent_seq);
            break;
        case PHPCF_EX_SHRINK_NLS:
        case PHPCF_EX_SHRINK_NLS_STRONG:
            strcat(out, "\n");
            _append_indent(out, *indent, indent_seq);
            break;
        case PHPCF_EX_NL_OR_SPACE:
            if (substr_count(in, '\n') > 0) {
                strcat(out, "\n");
                _append_indent(out, *indent, indent_seq);
            } else {
                out[0] = ' ';
                out[1] = 0;
            }
            break;
    }

    if (strcmp(in, out)) {
    	sniff_context_message(frm, in, out, origin, rule->descr, tok_idx, tokens, tokens_cnt);
    }


    smart_str_appends(result, out);
}

static char *phpcf_exec(phpcf_exec_seq *exec, int exec_len, phpcf_token *tokens, int tokens_cnt, Formatter *frm)
{
    int i, indent = 0, seq_len = 0;
    smart_str result = {0};
    phpcf_exec_seq *exec_sequence[10];
    phpcf_token *tok;

    smart_str_appends(&result, "");

    for (i = 0; i < exec_len; i++) {
        if (exec[i].type == EXEC_SEQ_TYPE_TOKEN) {
            tok = exec[i].seq_data.tok;
            if (tok->type == T_WHITESPACE) {
                exec_sequence[seq_len++] = exec + i;
            } else {
                if (seq_len) {
                	_apply_exec_sequence(exec_sequence, seq_len, tok->line, tokens, tokens_cnt, &indent, &result, frm);
                }
                smart_str_appends(&result, tok->val);
                seq_len = 0;
            }
        } else {
            exec_sequence[seq_len++] = exec + i;
        }
    }

    if (seq_len) {
    	_apply_exec_sequence(exec_sequence, seq_len, tok->line, tokens, tokens_cnt, &indent, &result, frm);
    }

    smart_str_0(&result);

    return result.c;
}

static char *_get_context_name(int idx, phpcf_hashtable *indexes)
{
    return (char*)ht_get(indexes, idx);
}

static void _fsm_print_stack(phpcf_fsm_stack *stack, phpcf_hashtable *table)
{
    int i = 0;
    for (i = 0; i < stack->len; i++) {
        fprintf(stderr, "%s%s", _get_context_name(stack->contexts[i], table), i == stack->len - 1 ? "" : " / ");
    }
}


static char *get_token_type_name(int token_type)
{
    if (token_type <= 256) return "";
    switch (token_type) {

        case T_REQUIRE_ONCE: return "T_REQUIRE_ONCE";
        case T_REQUIRE: return "T_REQUIRE";
        case T_EVAL: return "T_EVAL";
        case T_INCLUDE_ONCE: return "T_INCLUDE_ONCE";
        case T_INCLUDE: return "T_INCLUDE";
        case T_LOGICAL_OR: return "T_LOGICAL_OR";
        case T_LOGICAL_XOR: return "T_LOGICAL_XOR";
        case T_LOGICAL_AND: return "T_LOGICAL_AND";
        case T_PRINT: return "T_PRINT";
        case T_SR_EQUAL: return "T_SR_EQUAL";
        case T_SL_EQUAL: return "T_SL_EQUAL";
        case T_XOR_EQUAL: return "T_XOR_EQUAL";
        case T_OR_EQUAL: return "T_OR_EQUAL";
        case T_AND_EQUAL: return "T_AND_EQUAL";
        case T_MOD_EQUAL: return "T_MOD_EQUAL";
        case T_CONCAT_EQUAL: return "T_CONCAT_EQUAL";
        case T_DIV_EQUAL: return "T_DIV_EQUAL";
        case T_MUL_EQUAL: return "T_MUL_EQUAL";
        case T_MINUS_EQUAL: return "T_MINUS_EQUAL";
        case T_PLUS_EQUAL: return "T_PLUS_EQUAL";
        case T_BOOLEAN_OR: return "T_BOOLEAN_OR";
        case T_BOOLEAN_AND: return "T_BOOLEAN_AND";
        case T_IS_NOT_IDENTICAL: return "T_IS_NOT_IDENTICAL";
        case T_IS_IDENTICAL: return "T_IS_IDENTICAL";
        case T_IS_NOT_EQUAL: return "T_IS_NOT_EQUAL";
        case T_IS_EQUAL: return "T_IS_EQUAL";
        case T_IS_GREATER_OR_EQUAL: return "T_IS_GREATER_OR_EQUAL";
        case T_IS_SMALLER_OR_EQUAL: return "T_IS_SMALLER_OR_EQUAL";
        case T_SR: return "T_SR";
        case T_SL: return "T_SL";
        case T_INSTANCEOF: return "T_INSTANCEOF";
        case T_UNSET_CAST: return "T_UNSET_CAST";
        case T_BOOL_CAST: return "T_BOOL_CAST";
        case T_OBJECT_CAST: return "T_OBJECT_CAST";
        case T_ARRAY_CAST: return "T_ARRAY_CAST";
        case T_STRING_CAST: return "T_STRING_CAST";
        case T_DOUBLE_CAST: return "T_DOUBLE_CAST";
        case T_INT_CAST: return "T_INT_CAST";
        case T_DEC: return "T_DEC";
        case T_INC: return "T_INC";
        case T_CLONE: return "T_CLONE";
        case T_NEW: return "T_NEW";
        case T_EXIT: return "T_EXIT";
        case T_IF: return "T_IF";
        case T_ELSEIF: return "T_ELSEIF";
        case T_ELSE: return "T_ELSE";
        case T_ENDIF: return "T_ENDIF";
        case T_LNUMBER: return "T_LNUMBER";
        case T_DNUMBER: return "T_DNUMBER";
        case T_STRING: return "T_STRING";
        case T_STRING_VARNAME: return "T_STRING_VARNAME";
        case T_VARIABLE: return "T_VARIABLE";
        case T_NUM_STRING: return "T_NUM_STRING";
        case T_INLINE_HTML: return "T_INLINE_HTML";
        case T_CHARACTER: return "T_CHARACTER";
        case T_BAD_CHARACTER: return "T_BAD_CHARACTER";
        case T_ENCAPSED_AND_WHITESPACE: return "T_ENCAPSED_AND_WHITESPACE";
        case T_CONSTANT_ENCAPSED_STRING: return "T_CONSTANT_ENCAPSED_STRING";
        case T_ECHO: return "T_ECHO";
        case T_DO: return "T_DO";
        case T_WHILE: return "T_WHILE";
        case T_ENDWHILE: return "T_ENDWHILE";
        case T_FOR: return "T_FOR";
        case T_ENDFOR: return "T_ENDFOR";
        case T_FOREACH: return "T_FOREACH";
        case T_ENDFOREACH: return "T_ENDFOREACH";
        case T_DECLARE: return "T_DECLARE";
        case T_ENDDECLARE: return "T_ENDDECLARE";
        case T_AS: return "T_AS";
        case T_SWITCH: return "T_SWITCH";
        case T_ENDSWITCH: return "T_ENDSWITCH";
        case T_CASE: return "T_CASE";
        case T_DEFAULT: return "T_DEFAULT";
        case T_BREAK: return "T_BREAK";
        case T_CONTINUE: return "T_CONTINUE";
        case T_GOTO: return "T_GOTO";
        case T_FUNCTION: return "T_FUNCTION";
        case T_CONST: return "T_CONST";
        case T_RETURN: return "T_RETURN";
        case T_TRY: return "T_TRY";
        case T_CATCH: return "T_CATCH";
        case T_THROW: return "T_THROW";
        case T_USE: return "T_USE";
        case T_GLOBAL: return "T_GLOBAL";
        case T_PUBLIC: return "T_PUBLIC";
        case T_PROTECTED: return "T_PROTECTED";
        case T_PRIVATE: return "T_PRIVATE";
        case T_FINAL: return "T_FINAL";
        case T_ABSTRACT: return "T_ABSTRACT";
        case T_STATIC: return "T_STATIC";
        case T_VAR: return "T_VAR";
        case T_UNSET: return "T_UNSET";
        case T_ISSET: return "T_ISSET";
        case T_EMPTY: return "T_EMPTY";
        case T_HALT_COMPILER: return "T_HALT_COMPILER";
        case T_CLASS: return "T_CLASS";
        case T_INTERFACE: return "T_INTERFACE";
        case T_EXTENDS: return "T_EXTENDS";
        case T_IMPLEMENTS: return "T_IMPLEMENTS";
        case T_OBJECT_OPERATOR: return "T_OBJECT_OPERATOR";
        case T_DOUBLE_ARROW: return "T_DOUBLE_ARROW";
        case T_LIST: return "T_LIST";
        case T_ARRAY: return "T_ARRAY";
        case T_CLASS_C: return "T_CLASS_C";
        case T_METHOD_C: return "T_METHOD_C";
        case T_FUNC_C: return "T_FUNC_C";
        case T_LINE: return "T_LINE";
        case T_FILE: return "T_FILE";
        case T_COMMENT: return "T_COMMENT";
        case T_DOC_COMMENT: return "T_DOC_COMMENT";
        case T_OPEN_TAG: return "T_OPEN_TAG";
        case T_OPEN_TAG_WITH_ECHO: return "T_OPEN_TAG_WITH_ECHO";
        case T_CLOSE_TAG: return "T_CLOSE_TAG";
        case T_WHITESPACE: return "T_WHITESPACE";
        case T_START_HEREDOC: return "T_START_HEREDOC";
        case T_END_HEREDOC: return "T_END_HEREDOC";
        case T_DOLLAR_OPEN_CURLY_BRACES: return "T_DOLLAR_OPEN_CURLY_BRACES";
        case T_CURLY_OPEN: return "T_CURLY_OPEN";
        case T_PAAMAYIM_NEKUDOTAYIM: return "T_DOUBLE_COLON";
        case T_NAMESPACE: return "T_NAMESPACE";
        case T_NS_C: return "T_NS_C";
        case T_DIR: return "T_DIR";
        case T_NS_SEPARATOR: return "T_NS_SEPARATOR";
        // custom tokens by phpcf:
        case T_STRING_CONTENTS: return "T_STRING_CONTENTS";
        case T_HEREDOC_CONTENTS: return "T_HEREDOC_CONTENTS";
        case T_SINGLE_LINE_COMMENT: return "T_SINGLE_LINE_COMMENT";
        case T_SINGLE_LINE_COMMENT_ALONE: return "T_SINGLE_LINE_COMMENT_ALONE";
        case T_ANONFUNC: return "T_ANONFUNC";
        case T_ANONFUNC_LONG: return "T_ANONFUNC_LONG";
        case T_WHITESPACE_ALIGNED: return "T_WHITESPACE_ALIGNED";
        case T_FUNCTION_NAME: return "T_FUNCTION_NAME";
        case T_ARRAY_SHORT: return "T_ARRAY_SHORT";
        case T_ARRAY_SHORT_ML: return "T_ARRAY_SHORT_ML";
        case T_ARRAY_HINT: return "T_ARRAY_HINT";

    }
    return "UNKNOWN";
}

static void _print_whitespace_val(char *val)
{
    char buf[100];
    // '\n' => "\n", '\t' => "\t"
    int i = 0, j = 0, l = strlen(val);
    int final_len = l + substr_count(val, '\n') + substr_count(val, '\t');

    if (final_len > sizeof(buf) - 1) {
        fprintf(stderr, "%s", val);
        return;
    }

    for(i = 0; i <= l; i++) {
        if (val[i] == '\n')  {
            buf[j++] = '\\';
            buf[j++] = 'n';
        } else if (val[i] == '\t')  {
            buf[j++] = '\\';
            buf[j++] = 't';
        } else if (val[i] == ' ')  {
            buf[j++] = '.';
        } else {
            buf[j++] = val[i];
        }

        if (!val[i]) break;
    }

    fprintf(stderr, "%-30s", buf);
}


static void _print_debug_line(phpcf_fsm_stack *stack, phpcf_token *tok, phpcf_hashtable *table)
{
	fprintf(stderr, "%27s", get_token_type_name(tok->type & 0xFFFF));

    if (TOK_IS_LONG(tok->type))        fprintf(stderr, "_LONG  ");
    else if (TOK_IS_EMPTY(tok->type))  fprintf(stderr, "_EMPTY ");
    else if (TOK_IS_NL(tok->type))     fprintf(stderr, "_NL    ");
    else if (TOK_IS_UNARY(tok->type))  fprintf(stderr, "_UNARY ");
    else if (TOK_IS_LEFT(tok->type))   fprintf(stderr, "_LEFT  ");
    else if (TOK_IS_RIGHT(tok->type))  fprintf(stderr, "_RIGHT ");
    else if (TOK_IS_INLINE(tok->type)) fprintf(stderr, "_INLINE");
    else                               fprintf(stderr, "       ");

    _print_whitespace_val(tok->val);
    fprintf(stderr, "      ");
    _fsm_print_stack(stack,table);
    fprintf(stderr, "\n");
}


static int _fsm_perform_transition(phpcf_fsm_stack *stack, int type, int ctx)
{
    #if 0
    printf("FSM perform transition %d with ctx %d\n", type, ctx);
    #endif

    switch (type) {
        case CTX_RULE_TYPE_FLAT:
            stack->contexts[stack->len - 1] = ctx;
            break;
        case CTX_RULE_TYPE_PUSH:
            if (stack->len + 1 > sizeof(stack->contexts) / sizeof(stack->contexts[0])) {
            	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "PHPCF FSM stack overflow");
            }

            stack->contexts[stack->len] = ctx;
            stack->len++;
            break;
        case CTX_RULE_TYPE_POP:
            if (ctx > 0) {
            	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "PHPCF FSM incorrect rule for pop: %d", ctx);
            }
            stack->len += ctx;
            if (stack->len < 1) {
            	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "PHPCF FSM stack underflow");
            }
            break;
        default:
        	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "PHPCF FSM unknown rule type: %d", type);
            break;
    }
    return stack->contexts[stack->len - 1];
}

/*
 * performs context switch for tok, returns current context
 */
static int phpcf_fsm_transit(phpcf_fsm_stack *stack, phpcf_token *tok, Formatter *frm)
{
    phpcf_hashtable *tok_rules;
    phpcf_ctx_rule *rule;
    formatting_ctx *ctx = frm->ctx;

    if (tok->type == T_WHITESPACE) {
    	return stack->contexts[stack->len - 1];
    }

    if (ctx->delayed_rule_type) {

	if (frm->options->debug){
            fprintf(stderr, "Found delayed rule: ");
            switch (ctx->delayed_rule_type) {
                case CTX_RULE_TYPE_FLAT:
                    fprintf(stderr, "%s", _get_context_name(ctx->delayed_rule_ctx, frm->ctx_registry->inverted_indexes));
                    break;
                case CTX_RULE_TYPE_PUSH:
                    fprintf(stderr, "array(%s)", _get_context_name(ctx->delayed_rule_ctx,frm->ctx_registry->inverted_indexes));
                    break;
                case CTX_RULE_TYPE_POP:
                    fprintf(stderr, "%d", ctx->delayed_rule_ctx);
                    break;
            }
            fprintf(stderr, " in state %s\n", _get_context_name(stack->contexts[stack->len - 1], frm->ctx_registry->inverted_indexes));
        }

		_fsm_perform_transition(stack, ctx->delayed_rule_type, ctx->delayed_rule_ctx);
		ctx->delayed_rule_type = ctx->delayed_rule_ctx = 0;
	}


    tok_rules = ht_get(ctx->rules, stack->contexts[stack->len - 1]);
    if (!tok_rules) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "Inconsistent context rules: no rules for context %d", stack->contexts[stack->len - 1]);
    }

    rule = ht_get(tok_rules, tok->type);
    if (!rule) rule = ht_get(tok_rules, 0);
    if (rule) {
    	if (rule->type == CTX_RULE_TYPE_REPLACE) {
            _fsm_perform_transition(stack, rule->now_type, rule->now_ctx);
            _fsm_perform_transition(stack, rule->replace_type, rule->replace_ctx);
    	}
    	else if (rule->type == CTX_RULE_TYPE_DELAYED) {
            ctx->delayed_rule_type = rule->delayed_type;
            ctx->delayed_rule_ctx = rule->delayed_ctx;
            _fsm_perform_transition(stack, rule->now_type, rule->now_ctx);
        } else {
        	_fsm_perform_transition(stack, rule->type, rule->now_ctx);
        }
    }

    return stack->contexts[stack->len - 1];
}

static int phpcf_process(Formatter *frm, phpcf_token *tokens, int tokens_cnt, phpcf_exec_seq *exec, int *exec_len)
{
    int i = 0, j = 0, cur_ctx;
    phpcf_token *tok;
    phpcf_fsm_stack stack;
    phpcf_hashtable *ctx_control_rules;
    phpcf_exec_rule *erule;
    phpcf_control_rule *rule;
    /* init FSM */

    frm->ctx->delayed_rule_type = frm->ctx->delayed_rule_ctx = 0;
    stack.len = 1;
    stack.contexts[0] = frm->ctx_registry->first_ctx;

    for (i = 0; i < tokens_cnt; i++) {
        tok = tokens + i;
        cur_ctx = phpcf_fsm_transit(&stack, tok, frm);


        if (frm->options->debug) {
            _print_debug_line(&stack, tok,frm->ctx_registry->inverted_indexes);
        }


        ctx_control_rules = (phpcf_hashtable*)ht_get(frm->format_rules, tok->type);
        if (!ctx_control_rules) {
            // unknown token, leaving as-is
            exec[j].type = EXEC_SEQ_TYPE_TOKEN;
            exec[j].tok_idx = i;
            exec[j].seq_data.tok = tok;
            j++;
            continue;
        }

        // look for control rule for current context, otherwise get rule for default context
        rule = (phpcf_control_rule*)ht_get(ctx_control_rules, cur_ctx);
        if (!rule) {
            rule = (phpcf_control_rule*)ht_get(ctx_control_rules, 0);
            if (!rule) {
                // no default rule, leaving as-is
                exec[j].type = EXEC_SEQ_TYPE_TOKEN;
                exec[j].tok_idx = i;
                exec[j].seq_data.tok = tok;
                j++;
                continue;
            }
        }

        if ((rule->ex_left || rule->ex_indent_left) && (i == 0 || (tok - 1)->type != T_WHITESPACE_ALIGNED)) {
            exec[j].type = EXEC_SEQ_TYPE_EXEC_RULE;
            exec[j].origin = EXEC_SEQ_ORIGIN_LEFT;
            exec[j].tok_idx = i;
            exec[j].seq_data.rule.descr = rule->descr_left;
            exec[j].seq_data.rule.ex_indent = rule->ex_indent_left;
            exec[j].seq_data.rule.ex = rule->ex_left;
            j++;
        }

        exec[j].type = EXEC_SEQ_TYPE_TOKEN;
        exec[j].tok_idx = i;
        exec[j].seq_data.tok = tok;
        j++;

        if ((rule->ex_right || rule->ex_indent_right) && (i == tokens_cnt - 1 || (tok + 1)->type != T_WHITESPACE_ALIGNED)) {
            exec[j].type = EXEC_SEQ_TYPE_EXEC_RULE;
            exec[j].origin = EXEC_SEQ_ORIGIN_RIGHT;
            exec[j].tok_idx = i;
            exec[j].seq_data.rule.descr = rule->descr_right;
            exec[j].seq_data.rule.ex_indent = rule->ex_indent_right;
            exec[j].seq_data.rule.ex = rule->ex_right;
            j++;
        }
    }

    if (frm->ctx->delayed_rule_type) {
        cur_ctx = _fsm_perform_transition(&stack, frm->ctx->delayed_rule_type, frm->ctx->delayed_rule_ctx);
    }

    #if 0
    for (i = 0; i < j; i++) {
        if (exec[i].type == EXEC_SEQ_TYPE_EXEC_RULE) {
            erule = &(exec[i].seq_data.rule);
            if (erule->ex_indent) printf("  ex indent: %d", erule->ex_indent);
            if (erule->ex || 1)        printf("  ex: %d\n", erule->ex);
        } else {
            printf("Text: '%s'\n", exec[i].seq_data.tok->val);
        }
    }
    #endif

    if (cur_ctx != _get_context_idx("CTX_DEFAULT", frm->ctx_registry) && cur_ctx != _get_context_idx("CTX_PHP", frm->ctx_registry)) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "%s", "Internal formatter error, invalid final context");
    }

    *exec_len = j;
    return 0;
}

static char *phpcf_format(Formatter *frm, char *source, int source_len)
{
    phpcf_token *source_tokens, *tokens, tok;
    char *buf, *result, *rewrite_tokens_buf;
    int i, cnt, len = 0, l = 0, exec_len = 0, type;
    // maximum number of elements in exec per token is 3
    phpcf_exec_seq *exec;

    zval source_z;
    zend_lex_state original_lex_state;
    ZVAL_STRINGL(&source_z, source, source_len, 1);
    zend_save_lexical_state(&original_lex_state TSRMLS_CC);

    if (zend_prepare_string_for_scanning(&source_z, "" TSRMLS_CC) == FAILURE) {
        zend_restore_lexical_state(&original_lex_state TSRMLS_CC);
        return estrdup("");
    }

    LANG_SCNG(yy_state) = yycINITIAL;

    source_tokens = (phpcf_token*)emalloc(sizeof(phpcf_token) * source_len);
    buf = (char*)emalloc(source_len * 3);
    cnt = phpcf_tokenize(source_len, buf, source_tokens);

    zend_restore_lexical_state(&original_lex_state TSRMLS_CC);
    zval_dtor(&source_z);

    // 'rewrite token functions' do not split tokens to more than 2 new tokens
    tokens = (phpcf_token*)emalloc(sizeof(phpcf_token) * cnt * 2);
    l = source_len * 2;
    for (i = 0; i < cnt; i++) {
        type = source_tokens[i].type;
        if (type == T_OPEN_TAG)      {
        	l += 4; // '<?' can be replaced with '<?php'
        }
        else if (type == T_COMMENT)  {
        	l += 1; // '#' can be replaced with '//'
        }
    }

    rewrite_tokens_buf = (char*)emalloc(sizeof(phpcf_token) * l);
    cnt = phpcf_rewrite_tokens(frm, source_tokens, cnt, tokens, rewrite_tokens_buf);
    exec = (phpcf_exec_seq*)ecalloc(3 * cnt, sizeof(phpcf_exec_seq));
    phpcf_process(frm, tokens, cnt, exec, &exec_len);
    result = phpcf_exec(exec, exec_len, tokens, cnt, frm);

    efree(exec);
    efree(source_tokens);
    efree(tokens);
    efree(rewrite_tokens_buf);
    efree(buf);

    return result;
}


/**
 * Destroy all data, associated with formatter
 */
static void phpcf_formatter_dtor(void *object TSRMLS_DC)
{
	Formatter *formatter = (Formatter *)object;

	efree(formatter->format_rules);
	efree(formatter->ctx_rules);
	efree(formatter->options);
	efree(formatter->fsm_stack);
	efree(object);
}
/* }}} */

/**
 * Get token code by it's name
 */
static int _get_token_idx(char *name)
{
    zval tmp;
    int res, len = strlen(name);
    char buf[100];

    if (name[0] != 'T') {
        res = name[0];
        if (!strcmp(name + 1, "_NL")) res = TOK_NL(res);
        else if (!strcmp(name + 1, "_LONG")) res = TOK_LONG(res);
        else if (!strcmp(name + 1, "_UNARY")) res = TOK_UNARY(res);
        else if (!strcmp(name + 1, "_EMPTY")) res = TOK_EMPTY(res);
        return res;
    }

    if (!strcmp(name, "T_ARRAY_SHORT")) {
    	return T_ARRAY_SHORT;
    } else if (!strcmp(name, "T_ARRAY_SHORT_ML")) {
    	return T_ARRAY_SHORT_ML;
    }

    if (!strcmp(name + len - 5, "_LEFT"))        len -= 5;
    else if (!strcmp(name + len - 6, "_RIGHT"))  len -= 6;
    else if (!strcmp(name + len - 3, "_NL"))     len -= 3;
    else if (!strcmp(name + len - 7, "_INLINE")) len -= 7;
    else if (!strcmp(name + len - 6, "_EMPTY"))  len -= 6;

    if (len > sizeof(buf) - 1) {
        zend_error(E_ERROR, "Buffer overflow for token name %s", name);
    }

    strncpy(buf, name, len);
    buf[len] = 0;

    if (!zend_get_constant(buf, len, &tmp)) {
        zend_error(E_ERROR, "Could not parse constant %s", buf);
    }

    res = Z_LVAL(tmp);

    if (!strcmp(name + len, "_LEFT"))        res = TOK_LEFT(res);
    else if (!strcmp(name + len, "_RIGHT"))  res = TOK_RIGHT(res);
    else if (!strcmp(name + len, "_NL"))     res = TOK_NL(res);
    else if (!strcmp(name + len, "_INLINE")) res = TOK_INLINE(res);
    else if (!strcmp(name + len, "_EMPTY"))  res = TOK_EMPTY(res);

    return res;
}

// Creates new context registry
static phpcf_context_registry *new_context_registry()
{
	phpcf_context_registry *registry = (phpcf_context_registry*)ecalloc(1, sizeof(phpcf_context_registry));
    registry->counter = 0;
    MAKE_STD_ZVAL(registry->indexes);
    array_init(registry->indexes);

    return registry;
}

/**
 * Retrieve idx for context, named by name from registry
 */
static int _get_context_idx(char *name, phpcf_context_registry *registry)
{
    int i = 0;
    zval **res;
    HashTable *ht = Z_ARRVAL_P(registry->indexes);

    /**
     * @todo make it good
     */
    #if 0
    FOREACH_FIELDS;
    FOREACH_BEGIN(Z_ARRVAL_P(registry->indexes));
        case HASH_KEY_IS_STRING:
            if (strncmp(string_key, "CTX_", 4)) printf("Corrupted string key: %s\n", string_key);
            break;
    FOREACH_END(Z_ARRVAL_P(registry->indexes));

    for (i = 0; i < registry->inverted_indexes->cap; i++) {
        if (!registry->inverted_indexes->entries[i].val) {
        	continue;
        }
        if (strncmp(registry->inverted_indexes->entries[i].val, "CTX_", 4)) {
            printf("Added context %s\n", name);
            printf("Context %d value %s\n", registry->inverted_indexes->entries[i].key, registry->inverted_indexes->entries[i].val);
            zend_error(E_ERROR, "Memory corruption");
        }
    }
    #endif

    if (zend_hash_exists(ht, name, strlen(name) + 1)) {
        zend_hash_find(ht, name, strlen(name) + 1, (void**)&res);
        return Z_LVAL_PP(res);
    }
    registry->counter++;
    add_assoc_long(registry->indexes, name, registry->counter);
    ht_add(registry->inverted_indexes, registry->counter, estrdup(name));

    return registry->counter;
}

/*
Here is a consise description of PHP version rule actions

$rule_action = 'CTX_OTHER_THING';         // replace stack top with CTX_OTHER_THING
$rule_action = ['CTX_OTHER_THING'];       // perform push(CTX_OTHER_THING)
$rule_action = -2;                        // perform pop() 2 times
$rule_action = ['NOW' => N, 'NEXT' => M]; // execute N now, changing current context and execute M before next token
*/
static phpcf_ctx_rule _parse_context_rule_action(zval *rule_action, phpcf_context_registry *registry)
{
	phpcf_ctx_rule result, tmp, tmp2;
    zval **res;
    HashTable *ht;
    switch (Z_TYPE_P(rule_action)) {
        case IS_STRING:
            result.type = CTX_RULE_TYPE_FLAT;
            result.now_ctx = _get_context_idx(Z_STRVAL_P(rule_action), registry);
            break;
        case IS_LONG:
            result.type = CTX_RULE_TYPE_POP;
            result.now_ctx = Z_LVAL_P(rule_action);
            break;
        case IS_ARRAY:
            ht = Z_ARRVAL_P(rule_action);
            // replace rule
            if (zend_hash_exists(ht, "REPLACE", 8)) {
            	result.type = CTX_RULE_TYPE_REPLACE;
            	zval **replace_def;
            	zend_hash_find(ht, "REPLACE", 8, (void**)&replace_def);
            	if (IS_ARRAY != Z_TYPE_PP(replace_def)) {
            		zend_error(E_ERROR, "Not array passed for REPLACE definition");
            	}
            	HashTable *replace_ht;
            	replace_ht = Z_ARRVAL_PP(replace_def);
            	zval **from, **to;
            	if (FAILURE == zend_hash_index_find(replace_ht, 0, (void**)&from)
            			|| FAILURE == zend_hash_index_find(replace_ht, 1, (void**)&to)) {
            		zend_error(E_ERROR, "No array indexes 0,1 in REPLACE definition");
            	}
            	tmp = _parse_context_rule_action(*from, registry);
            	result.now_type = tmp.type;
            	result.now_ctx = tmp.now_ctx;
            	tmp = _parse_context_rule_action(*to, registry);
            	result.replace_type = tmp.type;
            	result.replace_ctx = tmp.now_ctx;
            }
            // common rule, as array - add it to stack
            else if (!zend_hash_exists(ht, "NOW", 4)) {
                zend_hash_index_find(ht, 0, (void**)&res);
                result.type = CTX_RULE_TYPE_PUSH;
                result.now_ctx = _get_context_idx(Z_STRVAL_PP(res),registry);
            }
            // delayed rule
            else {
                result.type = CTX_RULE_TYPE_DELAYED;
                zend_hash_find(ht, "NOW", 4, (void**)&res);
                tmp = _parse_context_rule_action(*res, registry);
                result.now_type = tmp.type;
                result.now_ctx  = tmp.now_ctx;
                zend_hash_find(ht, "NEXT", 5, (void**)&res);
                tmp = _parse_context_rule_action(*res, registry);
                result.delayed_type = tmp.type;
                result.delayed_ctx  = tmp.now_ctx;
            }

            break;
    }

    return result;
}

/*
$token_rules = array(
    '?'                    => array('CTX_TERNARY_BEGIN'),
    'T_OBJECT_OPERATOR $'  => array('CTX_INLINE_BRACE_BEGIN'),
    ...
);
*/

static phpcf_hashtable* _parse_token_context_rules(zval *ztoken_rules, phpcf_context_registry *registry)
{
    char tok_name[100];
    HashTable *token_rules = Z_ARRVAL_P(ztoken_rules);
    int i, l, ht_size = 0, last_idx = 0;
    phpcf_ctx_rule rule, *rulep;
    phpcf_hashtable *ht;

    FOREACH_FIELDS;
    FOREACH_BEGIN(token_rules);
        case HASH_KEY_IS_STRING:
            ht_size += substr_count(string_key, ' ') + 1;
            break;
        case HASH_KEY_IS_LONG:
            ht_size += 1;
            break;
    FOREACH_END(token_rules);

    ht = ht_init(ht_size * 2);

    FOREACH_BEGIN(token_rules);
        case HASH_KEY_IS_STRING:
            rule = _parse_context_rule_action(*entry, registry);
            l = strlen(string_key);
            last_idx = 0;
            for (i = 0; i < l; i++) {
                if (string_key[i] == ' ') {
                    strncpy(tok_name, string_key + last_idx, i - last_idx);
                    tok_name[i - last_idx] = 0;
                    last_idx = i + 1;
                    rulep = (phpcf_ctx_rule*)emalloc(sizeof(phpcf_ctx_rule));
                    memcpy(rulep, &rule, sizeof(phpcf_ctx_rule));
                    ht_add(ht, _get_token_idx(tok_name), rulep);
                }
            }
            strncpy(tok_name, string_key + last_idx, i - last_idx);
            tok_name[i - last_idx] = 0;
            rulep = (phpcf_ctx_rule*)emalloc(sizeof(phpcf_ctx_rule));
            memcpy(rulep, &rule, sizeof(phpcf_ctx_rule));
            ht_add(ht, _get_token_idx(tok_name), rulep);
            break;
        case HASH_KEY_IS_LONG:
            rule = _parse_context_rule_action(*entry,registry);
            if (num_key == 1) {
                rulep = (phpcf_ctx_rule*)emalloc(sizeof(phpcf_ctx_rule));
                memcpy(rulep, &rule, sizeof(phpcf_ctx_rule));
                ht_add(ht, 0, rulep);
            } else {
                zend_error(E_WARNING, "Unknown key in tokens in context rules: %lu", num_key);
            }
            break;
    FOREACH_END(token_rules);

    return ht;
}

/*
$context_rules = array(
    'CTX_LONG_FIRST_NL CTX_LONG_EXPR_NL' => <token_rule>,
    ...
);
*/
static phpcf_hashtable* parse_context_rules(HashTable *context_rules, phpcf_context_registry *registry)
{
    long cnt = 0;
    int last_idx, i, l, ht_size = 0;
    phpcf_hashtable *ht, *rule;
    char ctx_name[100];

    FOREACH_FIELDS;
    FOREACH_BEGIN(context_rules);
        case HASH_KEY_IS_STRING:
            ht_size += substr_count(string_key, ' ') + 1;
            break;
        case HASH_KEY_IS_LONG:
            ht_size += 1;
            break;
    FOREACH_END(context_rules);

    ht = ht_init(ht_size * 2);
    registry->inverted_indexes = ht_init(ht_size * 2);

    FOREACH_BEGIN(context_rules);
        case HASH_KEY_IS_STRING:
            rule = _parse_token_context_rules(*entry, registry);
            l = strlen(string_key);
            last_idx = 0;
            for (i = 0; i < l; i++) {
                if (string_key[i] == ' ') {
                    strncpy(ctx_name, string_key + last_idx, i - last_idx);
                    ctx_name[i - last_idx] = 0;
                    last_idx = i + 1;
                    ht_add(ht, _get_context_idx(ctx_name, registry), rule);
                }
            }
            strncpy(ctx_name, string_key + last_idx, i - last_idx);
            ctx_name[i - last_idx] = 0;
            ht_add(ht, _get_context_idx(ctx_name, registry), rule);
            break;
        case HASH_KEY_IS_LONG:
            if (num_key != 0) {
                zend_error(E_WARNING, "Incorrect key in context rules: %lu", num_key);
            } else {
                registry->first_ctx = _get_context_idx(Z_STRVAL_PP(entry), registry);
            }
            break;
    FOREACH_END(context_rules);

    return ht;
}

static phpcf_control_rule* _parse_token_control_rules(zval *zrules)
{
	phpcf_control_rule *return_val = (phpcf_control_rule*)ecalloc(1, sizeof(phpcf_control_rule));
    HashTable *rules = Z_ARRVAL_P(zrules);
    zval **res;
    FOREACH_FIELDS;
    if (zend_hash_index_find(rules, PHPCF_KEY_DESCR_LEFT, (void**)&res) == SUCCESS) {
        return_val->descr_left = estrdup(Z_STRVAL_PP(res));
    }

    if (zend_hash_index_find(rules, PHPCF_KEY_LEFT, (void**)&res) == SUCCESS) {
        if (Z_TYPE_PP(res) == IS_ARRAY) {
            FOREACH_BEGIN(Z_ARRVAL_PP(res));
                case HASH_KEY_IS_LONG:
                    if (Z_LVAL_PP(entry) == PHPCF_EX_DECREASE_INDENT) {
                        return_val->ex_indent_left--;
                    } else if (Z_LVAL_PP(entry) == PHPCF_EX_INCREASE_INDENT) {
                        return_val->ex_indent_left++;
                    } else {
                        return_val->ex_left = Z_LVAL_PP(entry);
                    }
                    break;
            FOREACH_END(Z_ARRVAL_PP(res));
        } else {
            return_val->ex_left = Z_LVAL_PP(res);
        }
    }

    if (zend_hash_index_find(rules, PHPCF_KEY_DESCR_RIGHT, (void**)&res) == SUCCESS) {
        return_val->descr_right = estrdup(Z_STRVAL_PP(res));
    }

    if (zend_hash_index_find(rules, PHPCF_KEY_RIGHT, (void**)&res) == SUCCESS) {
        if (Z_TYPE_PP(res) == IS_ARRAY) {
            FOREACH_BEGIN(Z_ARRVAL_PP(res));
                case HASH_KEY_IS_LONG:
                    if (Z_LVAL_PP(entry) == PHPCF_EX_DECREASE_INDENT) {
                        return_val->ex_indent_right--;
                    } else if (Z_LVAL_PP(entry) == PHPCF_EX_INCREASE_INDENT) {
                        return_val->ex_indent_right++;
                    } else {
                        return_val->ex_right = Z_LVAL_PP(entry);
                    }
                    break;
            FOREACH_END(Z_ARRVAL_PP(res));
        } else {
            return_val->ex_right = Z_LVAL_PP(res);
        }
    }

    return return_val;
}

static phpcf_hashtable* _parse_context_controls(zval *ztoken_rules, phpcf_context_registry *registry)
{
    HashTable *token_rules = Z_ARRVAL_P(ztoken_rules);
    phpcf_control_rule *rulep;
    phpcf_hashtable *ht;
    int ht_size = 0, l, i, last_idx;
    char ctx_name[100];

    FOREACH_FIELDS;
    FOREACH_BEGIN(token_rules);
        case HASH_KEY_IS_STRING:
            ht_size += substr_count(string_key, ' ') + 1;
            break;
        case HASH_KEY_IS_LONG:
            ht_size += 1;
            break;
    FOREACH_END(token_rules);

    ht = ht_init(ht_size * 2);

    FOREACH_BEGIN(token_rules);
        case HASH_KEY_IS_STRING:
            rulep = _parse_token_control_rules(*entry);
            l = strlen(string_key);
            last_idx = 0;
            for (i = 0; i < l; i++) {
                if (string_key[i] == ' ') {
                    strncpy(ctx_name, string_key + last_idx, i - last_idx);
                    ctx_name[i - last_idx] = 0;
                    last_idx = i + 1;
                    ht_add(ht, _get_context_idx(ctx_name, registry), rulep);
                }
            }
            strncpy(ctx_name, string_key + last_idx, i - last_idx);
            ctx_name[i - last_idx] = 0;
            ht_add(ht, _get_context_idx(ctx_name, registry), rulep);
            break;
        case HASH_KEY_IS_LONG:
            ht_add(ht, 0, _parse_token_control_rules(*entry));
            break;
    FOREACH_END(token_rules);

    return ht;
}

/*
$controls = array(
    '{' => ...,
    ', ,_LONG' => ...,
    ...
);
*/

static phpcf_hashtable* parse_controls(HashTable *controls, phpcf_context_registry *registry)
{
    phpcf_hashtable *ht, *token_controls;
    phpcf_control_rule *rule;
    int ht_size = 0, l, i, j, last_idx;
    char tok_name[100];

    FOREACH_FIELDS;
    FOREACH_BEGIN(controls);
        case HASH_KEY_IS_STRING:
            ht_size += substr_count(string_key, ' ') + 1;
            break;
        case HASH_KEY_IS_LONG:
            ht_size += 1;
            break;
    FOREACH_END(controls);

    ht = ht_init(ht_size * 2);

    FOREACH_BEGIN(controls);
        case HASH_KEY_IS_STRING:
            token_controls = _parse_context_controls(*entry, registry);
            l = strlen(string_key);
            last_idx = 0;
            for (i = 0; i < l; i++) {
                if (string_key[i] == ' ') {
                    strncpy(tok_name, string_key + last_idx, i - last_idx);
                    tok_name[i - last_idx] = 0;
                    last_idx = i + 1;
                    ht_add(ht, _get_token_idx(tok_name), token_controls);
                }
            }
            strncpy(tok_name, string_key + last_idx, i - last_idx);
            tok_name[i - last_idx] = 0;
            j = _get_token_idx(tok_name);
            ht_add(ht, j, token_controls);
            break;
    FOREACH_END(controls);


    #if 0
    for (i = 0; i < ht->cap; i++) {
        if (!ht->entries[i].val) continue;
        l = ht->entries[i].key;
        if (l < 256) {
            printf("token %c\n", l);
        } else if (l < 65536) {
            printf("token %s\n", get_token_type_name(l));
        } else {
            printf("token %d\n", l);
        }

        token_controls = (phpcf_hashtable*)ht->entries[i].val;
        for (j = 0; j < token_controls->cap; j++) {
            rule = (control_rule*)token_controls->entries[j].val;
            if (!rule) continue;
            l = token_controls->entries[j].key;
            printf("  context: %d\n", l);
            if (rule->descr_left)      printf("    rule descr left: %s\n", rule->descr_left);
            if (rule->ex_left)         printf("    ex left: %d\n", rule->ex_left);
            if (rule->ex_indent_left)  printf("    ex indent left: %d\n", rule->ex_indent_left);
            if (rule->descr_right)     printf("    rule descr right: %s\n", rule->descr_right);
            if (rule->ex_right)        printf("    ex right: %d\n", rule->ex_right);
            if (rule->ex_indent_right) printf("    ex indent right: %d\n", rule->ex_indent_right);
        }
    }
    #endif

    return ht;
}

static void php_phpcf_obj_dtor(Formatter *c TSRMLS_DC) /* {{{ */
{
	efree(c->ctx_rules);
	efree(c->format_rules);
	if (c->options) {
		efree(c->options);
	}

	if (c->fsm_stack) {
		efree(c->fsm_stack);
	}

	if (c->string_filter) {
		efree(c->string_filter);
	}

	zend_object_std_dtor(&c->std TSRMLS_CC);

	efree(c);
}
/* }}} */

static zend_object_value php_phpcf_new(zend_class_entry *ce TSRMLS_DC) /* {{{ */
{
	Formatter *c;
	formatting_ctx *ctx;
	phpcf_options *opts;
	zend_object_value retval;

	c = ecalloc(1, sizeof(*c));
	zend_object_std_init(&c->std, ce TSRMLS_CC);

	ctx = ecalloc(1, sizeof(*ctx));
	ctx->last_long_position = -1;
	ALLOC_HASHTABLE(ctx->user_lines);
	zend_hash_init(ctx->user_lines, 0, NULL, ZVAL_PTR_DTOR, 0);
	ALLOC_HASHTABLE(ctx->sniff);
	zend_hash_init(ctx->sniff, 0, NULL,issue_dtor, 1);
	c->ctx = ctx;

	opts = ecalloc(1, sizeof(*opts));
	opts->indent = PHPCF_INDENT;
	opts->max_line_length = 120;
	opts->debug = 0;
	opts->sniff = 0;
	c->options = opts;


	ALLOC_HASHTABLE(c->std.properties);
	zend_hash_init(c->std.properties, 0, NULL, ZVAL_PTR_DTOR, 0);
	object_properties_init(&c->std, ce);
	retval.handle = zend_objects_store_put(c, (zend_objects_store_dtor_t)zend_objects_destroy_object, (zend_objects_free_object_storage_t)php_phpcf_obj_dtor, NULL TSRMLS_CC);
	retval.handlers = &php_phpcf_handlers;
	return retval;
}
/* }}} */


/*
 rewrite `source_tokens` and write new ones to `tokens` using `buf` as buffer for strings
 returns number of new tokens
 */
static int phpcf_rewrite_tokens(Formatter *frm, phpcf_token *source_tokens, int source_tokens_cnt, phpcf_token *tokens, char *buf)
{
	phpcf_hashtable *tbl = rewrite_callbacks;
    phpcf_token tok;
    int cnt = 0, i = 0;
    void (*hook_func)(Formatter*, phpcf_token*, int*, int, phpcf_token*, int*, char**);

    frm->ctx->last_long_position = -1;

    for (i = 0; i < source_tokens_cnt; i++) {
        tok = source_tokens[i];
        // glue sequential whitespace tokens together
        if (tok.type == T_WHITESPACE && cnt > 0 && tokens[cnt - 1].type == T_WHITESPACE) {
            strcat(tokens[cnt - 1].val, tok.val);
            buf += strlen(tok.val) + 1;
            continue;
        }

        hook_func = ht_get(rewrite_callbacks, tok.type);
        if (hook_func) {
            hook_func(frm, source_tokens, &i, source_tokens_cnt, tokens, &cnt, &buf);
        } else {
            tokens[cnt].type = tok.type;
            strcpy(buf, tok.val);
            tokens[cnt].val = buf;
            tokens[cnt].line = tok.line;
            buf += strlen(tok.val) + 1;
        }

        cnt++;
    }

    return cnt;
}

/**
 * PHP interface
 */

/* {{{ arginfo */
ZEND_BEGIN_ARG_INFO_EX(arginfo_phpcf_get_version, 0, 0, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_phpcf_construct, 0, 0, 2)
    ZEND_ARG_INFO(0, ctx_rules)
    ZEND_ARG_INFO(0, format_rules)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_phpcf_format, 0, 0, 1)
    ZEND_ARG_INFO(0, content)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_phpcf_set_string_filter, 0, 0, 1)
    ZEND_ARG_INFO(0, callback)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_phpcf_set_max_line_length, 0, 0, 1)
    ZEND_ARG_INFO(0, line_length)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_phpcf_set_tab_sequence, 0, 0, 1)
    ZEND_ARG_INFO(0, tab_sequence)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_phpcf_set_sniff_messages, 0, 0, 1)
    ZEND_ARG_INFO(0, sniff)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_phpcf_set_debug_enabled, 0, 0, 1)
    ZEND_ARG_INFO(0, debug_enabled)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_phpcf_get_issues, 0, 0, 0)
ZEND_END_ARG_INFO()
/* }}} */


/* {{{ proto bool new \Phpcf\Impl\Extension(array ctx, array format) */
PHP_METHOD(phpcf, __construct)
{
    zval *ctx_rules, *format_rules;
    phpcf_hashtable *parsed_ctx_rules, *parsed_format_rules;
    phpcf_context_registry *registry;
    Formatter *FormatterImpl;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "aa", &ctx_rules, &format_rules) == FAILURE) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "%s", "Expect context and formatting rules being arrays");
    	return;
    }

    registry = new_context_registry();
    parsed_ctx_rules = parse_context_rules(Z_ARRVAL_P(ctx_rules), registry);
    parsed_format_rules = parse_controls(Z_ARRVAL_P(format_rules), registry);

    FormatterImpl = (Formatter*)zend_object_store_get_object(getThis() TSRMLS_CC);

    FormatterImpl->ctx_rules = parsed_ctx_rules;
    FormatterImpl->format_rules = parsed_format_rules;
    FormatterImpl->ctx_registry = registry;
    FormatterImpl->ctx->rules = FormatterImpl->ctx_rules; // @TODO is it OK?
}
/* }}} */

/* {{{ proto string Formatter->format(string body) */
PHP_METHOD(phpcf, format)
{
	Formatter *FormatterImpl;

    char *source = NULL, *result;
    int source_len;
    zval *lines;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sz", &source, &source_len, &lines) == FAILURE) {
    	return;
    }

    FormatterImpl = (Formatter*)zend_object_store_get_object(getThis() TSRMLS_CC);

    if (Z_TYPE_P(lines) == IS_ARRAY) {
    	FormatterImpl->ctx->user_lines = Z_ARRVAL_P(lines);
    } else {
    	zend_hash_clean(FormatterImpl->ctx->user_lines);
    }

    zend_hash_clean(FormatterImpl->ctx->sniff);

    result = phpcf_format(FormatterImpl, source, source_len);

    RETVAL_STRING(result, 0);
}
/* }}} */

/* {{{ proto void Formatter->setStringFilter(filter) */
PHP_METHOD(phpcf, setStringFilter)
{
	// callback, passed to the function
    zval 	**callback;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "Z", &callback) == FAILURE) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "%s", "Non-callable callback given");
    }
    Formatter *FormatterImpl = (Formatter*)zend_object_store_get_object(getThis() TSRMLS_CC);

    // non-callable and not null (to reset existing)
	if (!zend_is_callable(*callback, IS_CALLABLE_CHECK_NO_ACCESS, NULL TSRMLS_CC) && IS_NULL != Z_TYPE_PP(callback)) {
		zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "Non callable and not null string given");
		RETURN_FALSE;
	} else {
		if (IS_NULL == Z_TYPE_PP(callback)) {
			FormatterImpl->string_filter = NULL;
		} else {
			FormatterImpl->string_filter = *callback;
			SEPARATE_ZVAL(&FormatterImpl->string_filter);
			Z_ADDREF_P(FormatterImpl->string_filter);
		}
	}

    RETURN_TRUE;
}
/* }}} */

/* {{{ proto void Formatter->setMaxLineLength(width) */
PHP_METHOD(phpcf, setMaxLineLength)
{
	long line_length;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &line_length) == FAILURE) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "%s", "Invalid max line length given");
    }

    if (line_length <= 0) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "Negative max line length given");
    }

    Formatter *FormatterImpl = (Formatter*)zend_object_store_get_object(getThis() TSRMLS_CC);
    FormatterImpl->options->max_line_length = line_length;
}
/* }}} */

/* {{{ proto void Formatter->setMaxLineLength(width) */
PHP_METHOD(phpcf, setTabSequence)
{
	char *sequence;
	int sequence_len;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &sequence, &sequence_len) == FAILURE) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "Invalid sequence given");
    }

    if (sequence_len == 0) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "Empty sequence given");
    }

    Formatter *FormatterImpl = (Formatter*)zend_object_store_get_object(getThis() TSRMLS_CC);
    FormatterImpl->options->indent = sequence;
}
/* }}} */

/* {{{ proto void Formatter->setSniffMessages(flag) */
PHP_METHOD(phpcf, setSniffMessages)
{
	zend_bool sniff = 0;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "b", &sniff) == FAILURE) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "Invalid sniff flag given");
    }

    Formatter *FormatterImpl = (Formatter*)zend_object_store_get_object(getThis() TSRMLS_CC);
    FormatterImpl->options->sniff = sniff;
}
/* }}} */

/* {{{ proto void Formatter->setDebugEnabled(flag) */
PHP_METHOD(phpcf, setDebugEnabled)
{
	zend_bool debug = 0;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "b", &debug) == FAILURE) {
    	zend_throw_exception_ex(zend_exception_get_default(TSRMLS_C), 0 TSRMLS_CC, "Invalid debug flag given");
    }

    Formatter *FormatterImpl = (Formatter*)zend_object_store_get_object(getThis() TSRMLS_CC);
    FormatterImpl->options->debug = debug;
}
/* }}} */

/* {{{ proto void Formatter->getIssues() */
PHP_METHOD(phpcf, getIssues)
{
	HashPosition pointer;
	HashTable *issue_ht;


    Formatter *FormatterImpl = (Formatter*)zend_object_store_get_object(getThis() TSRMLS_CC);
    issue_ht = FormatterImpl->ctx->sniff;

    array_init_size(return_value, issue_ht->nNumOfElements);

    char **data;
    zval *issue;
    for (zend_hash_internal_pointer_reset_ex(issue_ht, &pointer); zend_hash_get_current_data_ex(issue_ht, (void **) &data, &pointer) == SUCCESS; zend_hash_move_forward_ex(issue_ht, &pointer)) {
    	add_next_index_string(return_value, *data, 1);
	}
}
/* }}} */

/* {{{ phpcf_functions[]
 *
 * Every user visible function must have an entry in phpcf_functions[].
 */
const zend_function_entry phpcf_functions[] = {
	PHP_ME(phpcf, __construct, arginfo_phpcf_construct, ZEND_ACC_PUBLIC)
	PHP_ME(phpcf, format, arginfo_phpcf_format, ZEND_ACC_PUBLIC)
	PHP_ME(phpcf, setStringFilter, arginfo_phpcf_set_string_filter, ZEND_ACC_PUBLIC)
	PHP_ME(phpcf, setMaxLineLength, arginfo_phpcf_set_max_line_length, ZEND_ACC_PUBLIC)
	PHP_ME(phpcf, setDebugEnabled, arginfo_phpcf_set_debug_enabled, ZEND_ACC_PUBLIC)
	PHP_ME(phpcf, setTabSequence, arginfo_phpcf_set_tab_sequence, ZEND_ACC_PUBLIC)
	PHP_ME(phpcf, setSniffMessages, arginfo_phpcf_set_sniff_messages, ZEND_ACC_PUBLIC)
	PHP_ME(phpcf, getIssues, arginfo_phpcf_get_issues, ZEND_ACC_PUBLIC)
	PHP_FE(phpcf_get_version, arginfo_phpcf_get_version)
    {NULL, NULL, NULL}
};
/* }}} */

/* {{{ phpcf_module_entry
 */
zend_module_entry phpcf_module_entry = {
    STANDARD_MODULE_HEADER,
    "phpcf",
    phpcf_functions,
    PHP_MINIT(phpcf),
    NULL,
    NULL,
    NULL,
    NULL,
    PHPCF_VERSION_STRING,
    STANDARD_MODULE_PROPERTIES
};
/* }}} */

#ifdef COMPILE_DL_PHPCF
ZEND_GET_MODULE(phpcf)
#endif

/**
 * Get version method
 */
PHP_FUNCTION(phpcf_get_version)
{
    RETURN_STRING(PHPCF_VERSION_STRING, 1);
}

/**
 * phpinfo() section
 */
PHP_MINFO_FUNCTION(phpcf) /* {{{ */
{
	php_info_print_table_start();
	php_info_print_table_header(2, "Version", PHPCF_VERSION_STRING);
	php_info_print_table_end();
}
/* }}} */

ZEND_RSRC_DTOR_FUNC(php_phpcf_dtor)
{
	if (rsrc->ptr) {
		Formatter *formatter = (Formatter *)rsrc->ptr;
		php_phpcf_obj_dtor(formatter);
		rsrc->ptr = NULL;
	}
}

PHP_MINIT_FUNCTION(phpcf) /* {{{ */
{
	zend_class_entry ce;

	le_phpcf = zend_register_list_destructors_ex(NULL, php_phpcf_dtor, "Formatter instance", module_number);

	memcpy(&php_phpcf_handlers, zend_get_std_object_handlers(), sizeof(zend_object_handlers));
	php_phpcf_handlers.clone_obj = NULL;

    INIT_NS_CLASS_ENTRY(ce,"Phpcf\\Impl", "Extension", phpcf_functions);
    phpcf_class_entry = zend_register_internal_class(&ce TSRMLS_CC);
    phpcf_class_entry->create_object = php_phpcf_new;
    phpcf_class_entry->ce_flags |= ZEND_ACC_FINAL_CLASS;

    init_rewrite_callbacks();

    return SUCCESS;
};
/* }}} */

PHP_RINIT_FUNCTION(phpcf) /* {{{ */
{
    return SUCCESS;
}
/* }}} */

