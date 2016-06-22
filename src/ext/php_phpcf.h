/*
 +----------------------------------------------------------------------+
 | PHPCF code formatter                                                 |
 +----------------------------------------------------------------------+
*/

/* $Id$ */

#ifndef PHP_PHPCF_H
#define PHP_PHPCF_H

extern zend_module_entry phpcf_module_entry;
#define phpext_phpcf_ptr &phpcf_module_entry

#ifdef ZTS
#include "TSRM.h"
#endif

#define PHPCF_VERSION_STRING "1.0.0"

#ifdef ZTS
#define PHPCF_G(v) TSRMG(phpcf_globals_id, zend_phpcf_globals *, v)
#else
#define PHPCF_G(v) (phpcf_globals.v)
#endif

/**
 * Constants
 */
#define PHPCF_DEFAULT_MAX_LINE_LENGTH    120

 // Custom tokens
#define T_STRING_CONTENTS               10000
#define T_HEREDOC_CONTENTS              10001
#define T_SINGLE_LINE_COMMENT           10002
#define T_SINGLE_LINE_COMMENT_ALONE     10003
#define T_ANONFUNC                      10004
#define T_ANONFUNC_LONG                 10005
#define T_WHITESPACE_ALIGNED            10006
#define T_FUNCTION_NAME                 10007
#define T_TERNARY_GLUED                 10008
#define T_EMPTY_BODY_OPEN               10009
#define T_ARRAY_SHORT                   10010
#define T_ARRAY_SHORT_ML                10011
#define T_ARRAY_HINT                    10012

#define PHPCF_INDENT "    "

// Control constants
#define PHPCF_KEY_ALL            1
#define PHPCF_KEY_LEFT           2
#define PHPCF_KEY_RIGHT          3
#define PHPCF_KEY_TYPE           4
#define PHPCF_KEY_CODE           5
#define PHPCF_KEY_TEXT           6
#define PHPCF_KEY_LINE           7
#define PHPCF_KEY_SEQUENCE       8
#define PHPCF_KEY_TOKEN_LENGTH   9

#define PHPCF_KEY_DESCR_LEFT     10
#define PHPCF_KEY_DESCR_RIGHT    11

#define PHPCF_EX_DO_NOT_TOUCH_ANYTHING  100
#define PHPCF_EX_CHECK_NL_STRONG        101
#define PHPCF_EX_DELETE_SPACES_STRONG   102
#define PHPCF_EX_SHRINK_SPACES_STRONG   103
#define PHPCF_EX_SHRINK_NLS_STRONG      104
// shrink to two lines (if any)
#define PHPCF_EX_SHRINK_NLS_2           105
#define PHPCF_EX_SHRINK_NLS             106
#define PHPCF_EX_CHECK_NL               107
#define PHPCF_EX_DELETE_SPACES          108
#define PHPCF_EX_SHRINK_SPACES          109
// accept either new line or space as whitespace
#define PHPCF_EX_NL_OR_SPACE            110

#define PHPCF_EX_INCREASE_INDENT        200
#define PHPCF_EX_DECREASE_INDENT        201

/**
 * Macros
 */
#define TOK_IS_LONG(tok)    (tok & 1 << 24)
#define TOK_IS_NL(tok)      (tok & 1 << 23)
#define TOK_IS_UNARY(tok)   (tok & 1 << 22)
#define TOK_IS_LEFT(tok)    (tok & 1 << 21)
#define TOK_IS_RIGHT(tok)   (tok & 1 << 20)
#define TOK_IS_INLINE(tok)  (tok & 1 << 19)
#define TOK_IS_EMPTY(tok)   (tok & 1 << 18)

#define TOK_LONG(tok)    (tok | 1 << 24)
#define TOK_NL(tok)      (tok | 1 << 23)
#define TOK_UNARY(tok)   (tok | 1 << 22)
#define TOK_LEFT(tok)    (tok | 1 << 21)
#define TOK_RIGHT(tok)   (tok | 1 << 20)
#define TOK_INLINE(tok)  (tok | 1 << 19)
#define TOK_EMPTY(tok)   (tok | 1 << 18)

#define SHOULD_IGNORE_TOKEN(type) ((type) == T_COMMENT || (type) == T_WHITESPACE)

#define TOKEN_HOOK_ARGS frm, stokens, scur, scnt, tokens, cur, buf
#define TOKEN_HOOK(name) static void name(Formatter *frm, phpcf_token *stokens, int *scur, int scnt, phpcf_token *tokens, int *cur, char **buf)


/**
 * Structures
 */

// Formatter options
typedef struct {
	int max_line_length; // number of characters to mark line as long
	char *indent; // your indent
	zend_bool sniff; // sniff messages
	zend_bool debug; // debug fsm
} phpcf_options;

// Rewrote token
typedef struct {
	int type;
	int line;
	char *val;
} phpcf_token;

// Operation
typedef struct {
	char *descr_left;
	char *descr_right;
	int ex_left;
	int ex_indent_left;
	int ex_right;
	int ex_indent_right;
} phpcf_control_rule;

#define CTX_RULE_TYPE_FLAT      1
#define CTX_RULE_TYPE_POP       2
#define CTX_RULE_TYPE_PUSH      3
#define CTX_RULE_TYPE_DELAYED   4
#define CTX_RULE_TYPE_REPLACE   5

// Context rule
typedef struct {
	int type;
	int now_type;      // only set for "delayed" and "replace"
	int now_ctx;       // just int for pop, for others it is ctx_id
	int delayed_type;  // only set for "delayed"
	int delayed_ctx;   // only set for "delayed"
	int replace_type;  // only set for "replace"
	int replace_ctx;   // only set for "replace"
} phpcf_ctx_rule;

// Execution rules
#define EXEC_SEQ_TYPE_TOKEN      1
#define EXEC_SEQ_TYPE_EXEC_RULE  2

#define EXEC_SEQ_ORIGIN_LEFT     1
#define EXEC_SEQ_ORIGIN_RIGHT    2

typedef struct {
	int ex_indent;
	int ex;
	char *descr;
} phpcf_exec_rule;

typedef struct {
	int type;
	int origin;
	int tok_idx;
	union {
		phpcf_token *tok;
		phpcf_exec_rule rule;
	} seq_data;
} phpcf_exec_seq;

typedef struct {
	int len;
	int contexts[100];
} phpcf_fsm_stack;

// Hash table
typedef struct {
	int key;
	void *val;
} _phpcf_hashtable_entry;

typedef struct {
	int cap;
	int len;
	_phpcf_hashtable_entry *entries;
} phpcf_hashtable;

typedef struct {
	HashTable *user_lines;
	HashTable *sniff;
	int last_long_position;
	int delayed_rule_type;
	int delayed_rule_ctx;
	phpcf_hashtable *rules;
} formatting_ctx;

typedef struct {
	int counter;
	int first_ctx;
	zval *indexes;
	phpcf_hashtable *inverted_indexes;
} phpcf_context_registry;

// Formatter
typedef struct {
	zend_object std;
	phpcf_hashtable *ctx_rules;
	phpcf_hashtable *format_rules;
	phpcf_fsm_stack *fsm_stack;
	phpcf_options *options;
	zval *string_filter;
	formatting_ctx *ctx;
	phpcf_context_registry *ctx_registry;
} Formatter;

/**
 * Signatures
 */
PHP_MINIT_FUNCTION(phpcf);
PHP_MINFO_FUNCTION(phpcf);

PHP_METHOD(phpcf, format); // Formatting method, returning string
PHP_METHOD(phpcf, setStringFilter); // method to install callback for string tokens
PHP_METHOD(phpcf, setMaxLineLength); // max line length installation
PHP_METHOD(phpcf, setTabSequence); // spaces, tabs, all you want
PHP_METHOD(phpcf, setSniffMessages); // sniff mode toggle
PHP_METHOD(phpcf, setDebugEnabled); // debug output activator
PHP_METHOD(phpcf, getIssues); // collect wrong formatting info
PHP_FUNCTION(phpcf_get_version); // retrieve version info

/**
 * Hook signatures
 */
TOKEN_HOOK(token_hook_open_tag);
TOKEN_HOOK(token_hook_close_tag);
TOKEN_HOOK(token_hook_whitespace);
TOKEN_HOOK(token_hook_function);
TOKEN_HOOK(token_hook_tstring);
TOKEN_HOOK(token_hook_comment);
TOKEN_HOOK(token_hook_classdef);
TOKEN_HOOK(token_hook_else);
TOKEN_HOOK(token_hook_increment);
TOKEN_HOOK(token_hook_static);
TOKEN_HOOK(token_hook_heredoc);
TOKEN_HOOK(token_hook_binary);
TOKEN_HOOK(token_hook_str);
TOKEN_HOOK(token_hook_open_brace);
TOKEN_HOOK(token_hook_check_unary);
TOKEN_HOOK(token_hook_comma);
TOKEN_HOOK(token_hook_ternary_begin);
TOKEN_HOOK(token_hook_curly_brace);
TOKEN_HOOK(token_hook_square_brace);
TOKEN_HOOK(token_hook_variable);
TOKEN_HOOK(token_hook_array);

static int phpcf_rewrite_tokens(Formatter *frm, phpcf_token *source_tokens, int source_tokens_cnt, phpcf_token *tokens, char *buf);
static int _get_context_idx(char *name, phpcf_context_registry *registry);

#endif	/* PHP_PHPCF_H */


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: t
 * End:
 */
