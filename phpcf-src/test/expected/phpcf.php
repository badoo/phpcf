#!/local/php/bin/php
<?php

define('PHPCF_KEY_ALL',          1);
define('PHPCF_KEY_LEFT',         2);
define('PHPCF_KEY_RIGHT',        3);

define('PHPCF_KEY_TYPE',         4);
define('PHPCF_KEY_CODE',         5);
define('PHPCF_KEY_TEXT',         6);
define('PHPCF_KEY_LINE',         7);
define('PHPCF_KEY_SEQUENCE',     8);
define('PHPCF_KEY_TOKEN_LENGTH', 9);

define('PHPCF_KEY_DESCR_LEFT',   10);
define('PHPCF_KEY_DESCR_RIGHT',  11);

/*************************************************************************************************/
/*  PHPCF_EX-constants - special PHPCF_EXecutors processed at final output stage
/*  Note, that order is important -- first executor has higher priority than the last 
/*    - DELETE: just delete, nuff said
/*    - SHRINK: shrink to a single token
/*    - CHECK: just check, if not exists - add, but don't shrink
/*    - INCREASE/DECREASE: indent operations
/*************************************************************************************************/
$cnt = 100;
define('PHPCF_EX_DO_NOT_TOUCH_ANYTHING',  $cnt++);
define('PHPCF_EX_CHECK_NL_STRONG',        $cnt++);
define('PHPCF_EX_DELETE_SPACES_STRONG',   $cnt++);
define('PHPCF_EX_SHRINK_SPACES_STRONG',   $cnt++);
define('PHPCF_EX_SHRINK_NLS_STRONG',      $cnt++);
define('PHPCF_EX_SHRINK_NLS_2',           $cnt++);  // shrink to two lines (if any)
define('PHPCF_EX_SHRINK_NLS',             $cnt++);
define('PHPCF_EX_CHECK_NL',               $cnt++);
define('PHPCF_EX_DELETE_SPACES',          $cnt++);
define('PHPCF_EX_SHRINK_SPACES',          $cnt++);
define('PHPCF_EX_NL_OR_SPACE',            $cnt++);  // accept either new line or space as whitespace
unset($cnt);

define('PHPCF_EX_INCREASE_INDENT',        200);
define('PHPCF_EX_DECREASE_INDENT',        201);

define('PHPCF_CTX_NOW',  'CTX_NOW');
define('PHPCF_CTX_NEXT', 'CTX_NEXT');

// constant is used to determine whether or not need to split expression to several lines
define('PHPCF_LONG_EXPRESSION_LENGTH', 120);

if (!ini_get('short_open_tag')) {
    // otherwise short tags will not be tokenized
    trigger_error('short_open_tag must be enabled for tokenization to work');
}

mb_internal_encoding('UTF-8');
if (is_callable('dl')) {
    if (!function_exists('posix_isatty')) @dl('posix.so');
    if (!function_exists('pcntl_fork'))   @dl('pcntl.so');
}

if (!function_exists('posix_isatty')) {
    function posix_isatty()
    {
        return false;
    }
}

class PHPCodeFSM
{
    public $rules = array();
    public $state = null;
    public $state_stack = array();
    public $old_state = null;
    public $prev_code = null;
    public $current_rules = array(/* state, code_key, rules */);

    private $delayed_rule = null;

    public function __construct($rules)
    {
        if (!empty($rules)) {
            foreach ($rules as $key => $data) {
                $parts = explode(' ', $key);
                if (empty($parts)) $parts = array($key);
                foreach ($parts as $key_sub) {
                    $this->rules[$key_sub] = $data;
                }
            }

            if (isset($rules[0])) {
                $this->state = $rules[0];
            }
        }
    }

    public function getState()
    {
        return $this->state;
    }

    public function getStackPath()
    {
        $result = $this->state_stack;
        $result[] = $this->state;
        return implode(' / ', $result);
    }

    public function transit($code)
    {
        if ($code == 'T_WHITESPACE') {
            return;
        }

        $i_rules = false;
        $code_key = $code;

        if (isset($this->delayed_rule)) {
            if (PHPCF_DEBUG) {
                echo "Found delayed rule: " . print_r($this->delayed_rule, true);
                echo " in current state: {$this->state}\n";
            }
            $this->state = $this->delayed_rule;
            $this->executeTransition();
            $this->delayed_rule = null;
        }

        if (isset($this->rules[$this->state])) {
            $i_rules = $this->rules[$this->state];
            if (!isset($i_rules[$code_key])) {
                $code_key = PHPCF_KEY_ALL;
            }

            if (!empty($i_rules[$code_key])) {
                $this->current_rules = array($this->state, $code);
                $this->old_state = $this->state;
                $this->state = $i_rules[$code_key];

                // you can specify delayed context switch as
                // array(PHPCF_CTX_NOW => ..., PHPCF_CTX_NEXT => ...)
                if (is_array($this->state) && isset($this->state[PHPCF_CTX_NOW])) {
                    if (isset($this->state[PHPCF_CTX_NEXT])) {
                        $this->delayed_rule = $this->state[PHPCF_CTX_NEXT];
                    }
                    $this->state = $this->state[PHPCF_CTX_NOW];
                }

                $this->executeTransition();
            }
        }

        $this->prev_code = $code;
    }

    private function executeTransition()
    {
        if (is_array($this->state)) {
            // context inside context
            $this->state = $this->state[0];
            $this->state_stack[] = $this->old_state;
        } elseif ($this->state < 0) {
            $i = -$this->state;
            while ($i > 0) {
                $this->state = array_pop($this->state_stack);
                $i--;
            }
        }
    }
}

class PHPCodeFormatter
{
    private $tokens = array();
    public  $FSM = null;
    public  $sniff_exit_code = 0;
    private $exec = array();
    private $exec_ctx = array();
    private $color = false;
    private $indent_char = ' ';
    private $newline_char = "\n";
    private $indent_level = 0;
    private $indent_width = 4;
    private $current_pos = 0;
    private $controls = array();
    private $context_map = array();
    private $context = false;
    private $new_line = "\n";
    // private $file_lines = array(); // can be useful for debugging of problem with line numbers
    private $sniff_errors = array(/* error text => array(lines) */);
    private $white_space_map = array(
        "\r" => "",
        "\0" => "",
    );
    private $constants = array(
        'true'  => 1,
        'false' => 1,
        'null'  => 1,
    );

    function __construct($filename, $fsm_rules, $controls)
    {
        $this->init();
        $this->initFile($filename);
        $this->initControls($controls);
        $this->FSM = new PHPCodeFSM($fsm_rules);
    }

    private function initFile($filename)
    {
        if (!is_readable($filename)) {
            fwrite(STDERR, "ERROR: can't read file '" . $filename . "', will exit.\n");
            exit(1);
        }
        $body = file_get_contents($filename);
        // $this->file_lines = file($filename); // can be useful for debugging of problem with line numbers
        $this->prepareTokens($body);
    }

    private function init()
    {
        $this->white_space_map += array(
            " "  => $this->indent_char,
            "\t" => $this->indent_char,
            "\n" => $this->newline_char,
        );

        $this->color = posix_isatty(1);
    }

    private function isDebug()
    {
        return PHPCF_DEBUG;
    }

    private function debug($m, $data = null)
    {
        if ($this->isDebug()) {
            echo $m, "\n";
            if ($data !== null) {
                var_dump($data);
            }
        }
    }

    /* text inside HEREDOCs is tokenized in PHP by default, which is not what we need at all */
    private function tokenHookHeredoc(&$tokens, $idx_tokens, $i_value)
    {
        $processed_tokens = array();
        $heredoc_value = '';

        $this->current_line = $i_value[2];
        $processed_tokens[] = array(
            PHPCF_KEY_CODE  => $idx_tokens[$i_value[0]],
            PHPCF_KEY_TEXT  => $i_value[1],
            PHPCF_KEY_LINE  => $this->current_line,
        );

        while (list(, $i_value) = each($tokens)) {
            if ($i_value[0] === T_END_HEREDOC) {
                $this->current_line = $i_value[2];
                $processed_tokens[] = array(
                    PHPCF_KEY_CODE => 'T_HEREDOC_CONTENTS',
                    PHPCF_KEY_TEXT => $heredoc_value,
                    PHPCF_KEY_LINE => $this->current_line,
                );
                break;
            }

            if (is_array($i_value)) {
                $heredoc_value .= $i_value[1];
                $this->current_line = $i_value[2];
            } else {
                $heredoc_value .= $i_value;
            }
        }

        $processed_tokens[] = array(
            PHPCF_KEY_CODE  => $idx_tokens[$i_value[0]],
            PHPCF_KEY_TEXT  => $i_value[1],
            PHPCF_KEY_LINE  => $this->current_line,
        );

        return $processed_tokens;
    }

    /* text inside "strings" is also tokenized in PHP by default: we do not care about these */
    private function tokenHookDblStr(&$tokens)
    {
        $quote_token = array(
            PHPCF_KEY_CODE  => '"',
            PHPCF_KEY_TEXT  => '"',
            PHPCF_KEY_LINE  => $this->current_line,
        );

        $string_value = '';
        $processed_tokens = array();

        $processed_tokens[] = $quote_token;

        while (list(, $i_value) = each($tokens)) {
            if ($i_value === '"') {
                $processed_tokens[] = array(
                    PHPCF_KEY_CODE  => 'T_STRING_CONTENTS',
                    PHPCF_KEY_TEXT  => $string_value,
                    PHPCF_KEY_LINE  => $this->current_line,
                );
                break;
            }

            if (is_array($i_value)) {
                $string_value .= $i_value[1];
                $this->current_line = $i_value[2];
            } else {
                $string_value .= $i_value;
            }
        }

        $quote_token[PHPCF_KEY_LINE] = $this->current_line;
        $processed_tokens[] = $quote_token;

        return $processed_tokens;
    }

    private function countExprLength(&$tokens, $found_first_bracket = false, $max_length)
    {
        $length = 0;
        $depth = $found_first_bracket ? 1 : 0;
        $current_pos = key($tokens);

        // not using next() as do not want to move array pointer
        for ($i = $current_pos; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (!$found_first_bracket) {
                if ($token == '(') $found_first_bracket = true;
                else               continue;
            }

            // allow having long array definitions if user has decided that there should be an array in long form
            // also, functions are always required to be on separate lines, so we will consider having opening brace
            // as a sign that this expression is "long"
            if ($token === '{' || $token[0] === T_WHITESPACE && strpos($token[1], "\n") !== false) {
                return $max_length + 1;
            }

            if (is_array($token)) $length += strlen($token[1]);
            else                  $length += strlen($token);

            if ($length > $max_length) break;

            if ($token === '(') $depth++;
            if ($token === ')') $depth--;
            if ($depth <= 0) break;
        }

        return $length;
    }

    // T_ARRAY => T_ARRAY_LONG in case of array with a lot of contents
    private function tokenHookOpenBrace(&$tokens, $idx_tokens, $i_value)
    {
        $length = $this->countExprLength($tokens, true, PHPCF_LONG_EXPRESSION_LENGTH);
        $token = '(';
        if ($length >= PHPCF_LONG_EXPRESSION_LENGTH) $token = '(_LONG';

        return array(
            array(
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => '(',
                PHPCF_KEY_LINE  => $this->current_line,
            )
        );
    }

    private function shouldIgnoreToken($token)
    {
        return $token == T_WHITESPACE || $token == T_COMMENT;
    }

    private function tokenHookCheckUnary(&$tokens, $idx_tokens, $i_value)
    {
        static $left_expression_symbols = array(']', ')', T_LNUMBER, T_DNUMBER, T_VARIABLE, T_STRING,);

        $is_unary = false;
        $current_pos = key($tokens) - 1; // token position is already pointing to next token

        // token is unary if there is no expression on the left
        for ($i = $current_pos - 1; $i > 0; $i--) {
            $tok = $tokens[$i];
            if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) continue;
            if (is_array($tok)) $tok = $tok[0];

            if (!in_array($tok, $left_expression_symbols, true)) {
                $is_unary = true;
            }
            break;
        }

        $token = $i_value . ($is_unary ? '_UNARY' : '');

        return array(
            array(
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => $i_value,
                PHPCF_KEY_LINE  => $this->current_line,
            )
        );
    }

    // interpret "static" in "static::HELLO" as normal text (T_STRING) instead of keyword (T_STATIC)
    private function tokenHookStatic(&$tokens, $idx_tokens, $i_value)
    {
        static $right_normal_context_symbols = array(
            T_VARIABLE, // static $var; protected static $var;
            T_PROTECTED, T_PUBLIC, T_PRIVATE, T_FINAL, T_VAR, T_ABSTRACT, // static protected $var;
            T_FUNCTION, // static function() { ... }
        );

        $is_normal_context = false;
        $next_pos = key($tokens);
        $this->current_line = $i_value[2];

        for ($i = $next_pos; $i < count($tokens); $i++) {
            $tok = $tokens[$i];
            if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) continue;
            if (in_array($tok[0], $right_normal_context_symbols, true)) $is_normal_context = true;
            break;
        }

        if ($is_normal_context) return $this->tokenHookClassdef($tokens, $idx_tokens, $i_value);

        return array(
            array(
                PHPCF_KEY_CODE => 'T_STRING',
                PHPCF_KEY_TEXT => 'static',
                PHPCF_KEY_LINE => $this->current_line,
            )
        );
    }

    // check for newline after "const", "private", etc and check if it is something like this:
    // const
    //     CONST1 = "Something",
    //     CONST2 = "Another thing";
    private function tokenHookClassdef(&$tokens, $idx_tokens, $i_value)
    {
        $token = $idx_tokens[$i_value[0]];
        $this->current_line = $i_value[2];

        $next_pos = key($tokens);
        $is_property_def = false;
        $is_newline = false; // whether or not definition is on a new line

        for ($i = $next_pos; $i < count($tokens); $i++) {
            $tok = $tokens[$i];
            // look for whitespace, comment, variable or constant name after, e.g. "private"
            // if nothing was found, then it is end of property def (or real property def did not begin)
            if ($tok[0] === T_VARIABLE || $tok[0] === T_STRING) $is_property_def = true;
            else if ($tok[0] !== T_COMMENT && $tok[0] != T_WHITESPACE) break;
            if (strpos($tok[1], "\n") !== false) $is_newline = true;
        }

        if ($is_property_def && $is_newline) $token .= '_NL';

        return array(
            array(
                PHPCF_KEY_CODE => $token,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            )
        );
    }

    private function appendWhiteSpace(&$tokens, &$return_tokens, $text = "\n")
    {
        $next_token = current($tokens);
        if ($next_token[0] !== T_WHITESPACE) {
            $return_tokens[] = array(
                PHPCF_KEY_CODE  => 'T_WHITESPACE',
                PHPCF_KEY_TEXT  => $text,
                PHPCF_KEY_LINE  => $this->current_line,
            );
        } else {
            $tokens[key($tokens)][1] = "\n" . $tokens[key($tokens)][1];
        }
    }

    // only long "<?php" open tags are allowed by our rules
    private function tokenHookOpenTag(&$tokens, $idx_tokens, $i_value)
    {
        $open_tag = "<?php";
        $this->current_line = $i_value[2];
        if (rtrim($i_value[1]) !== $open_tag) $this->sniffMessage('Only "<?php" allowed as open tag');

        $ret = array(
            array(
                PHPCF_KEY_CODE => 'T_OPEN_TAG',
                PHPCF_KEY_TEXT => $open_tag,
                PHPCF_KEY_LINE => $this->current_line,
            )
        );

        $this->appendWhiteSpace($tokens, $ret);

        return $ret;
    }

    // T_INC => T_INC_LEFT || T_INC_RIGHT ( T_INC_LEFT in case "++$a", T_INC_RIGHT in case "$a++")
    private function tokenHookIncrement(&$tokens, $idx_tokens, $i_value)
    {
        $next_pos = key($tokens);
        $is_left = false;

        if ($next_pos) {
            for ($i = $next_pos; $i < count($tokens); $i++) {
                $tok = $tokens[$i];
                if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) continue;
                // in situations like '++ $a' there would be variable on the right, so the token is positioned left
                if ($tok[0] === T_VARIABLE) $is_left = true;
                break;
            }
        }

        $token = $idx_tokens[$i_value[0]] . ($is_left ? '_LEFT' : '_RIGHT');
        $this->current_line = $i_value[2];

        return array(
            array(
                PHPCF_KEY_CODE => $token,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            )
        );
    }

    private function isLineAligned($line_pos, $line_length, $whitespace_length, &$tokens)
    {
        $other_line_length = 0;

        for ($i = $line_pos; $i < count($tokens); $i++) {
            $tok = $tokens[$i];
            if (!is_array($tok)) {
                $other_line_length += strlen($tok);
            } else {
                $is_str = $tok[0] === T_CONSTANT_ENCAPSED_STRING || $tok[0] === T_ENCAPSED_AND_WHITESPACE;
                if (!$is_str && strpos($tok[1], "\n") !== false) break;
                $other_line_length += strlen($tok[1]);
            }

            if ($other_line_length == $whitespace_length && $tok[0] === T_WHITESPACE) {
                // aligned by whitespace
                return true;
            } else if ($other_line_length == $line_length && $tokens[$i - 1][0] === T_WHITESPACE) {
                // aligned by tokens!
                return true;
            } else if ($other_line_length > $line_length) {
                // no chance
                break;
            }
        }

        return false;
    }

    private function isPrevLineAligned($prev_line_pos, $line_length, $whitespace_length, &$tokens)
    {
        for ($i = $prev_line_pos; $i >= 0; $i--) { // find beginning of line
            $tok = $tokens[$i];
            if (!is_array($tok)) continue;
            $is_str = $tok[0] === T_CONSTANT_ENCAPSED_STRING || $tok[0] === T_ENCAPSED_AND_WHITESPACE;
            if (!$is_str && strpos($tok[1], "\n") !== false) break;
        }

        if ($i < $prev_line_pos) {
            return $this->isLineAligned($i + 1, $line_length, $whitespace_length, $tokens);
        }

        return false;
    }

    private function isNextLineAligned($next_line_pos, $line_length, $whitespace_length, &$tokens)
    {
        for ($i = $next_line_pos; $i < count($tokens); $i++) { // find beginning of line
            $tok = $tokens[$i];
            if (!is_array($tok)) continue;
            $is_str = $tok[0] === T_CONSTANT_ENCAPSED_STRING || $tok[0] === T_ENCAPSED_AND_WHITESPACE;
            if (!$is_str && strpos($tok[1], "\n") !== false) break;
        }

        if ($i > $next_line_pos) {
            return $this->isLineAligned($i + 1, $line_length, $whitespace_length, $tokens);
        }

        return false;
    }

    // find aligned expressions
    private function tokensIsWhiteSpaceAligned(&$tokens, $i_value)
    {
        $next_pos = key($tokens);

        // either it's EOF or whitespace has tabs/newlines or it has length less than 2
        if (!$next_pos || !preg_match('/^  +$/s', $i_value[1])) {
            return false;
        }

        // search for beginning of line and count length of line before this whitespace
        $whitespace_length = $line_length = 0;
        for ($i = $next_pos; $i >= 0; $i--) {
            $tok = $tokens[$i];
            if (!is_array($tok)) {
                $line_length += strlen($tok);
                if ($i != $next_pos) $whitespace_length += strlen($tok);
            } else {
                $is_str = $tok[0] === T_CONSTANT_ENCAPSED_STRING || $tok[0] === T_ENCAPSED_AND_WHITESPACE;
                if (!$is_str && strpos($tok[1], "\n") !== false) break;
                // this will skip the indent as well, we do not care
                $line_length += strlen($tok[1]);
                if ($i != $next_pos) $whitespace_length += strlen($tok[1]);
            }
        }

        $is_aligned = false;
        if ($i > 0)       $is_aligned = $this->isPrevLineAligned($i - 1, $line_length, $whitespace_length, $tokens);
        if (!$is_aligned) $is_aligned = $this->isNextLineAligned($next_pos, $line_length, $whitespace_length, $tokens);

        return $is_aligned;
    }

    private function tokenHookWhiteSpace(&$tokens, $idx_tokens, $i_value)
    {
        // fix for bug with wrong line numbers of tokens like "{" which do not have line number assotiated with them
        $this->current_line = $i_value[2] + substr_count($i_value[1], "\n");
        if ($this->tokensIsWhiteSpaceAligned($tokens, $i_value)) $token = 'T_WHITESPACE_ALIGNED';
        else                                                     $token = 'T_WHITESPACE';

        return array(
            array(
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => $i_value[1],
                PHPCF_KEY_LINE  => $this->current_line,
            )
        );
    }

    private function tokenHookElse(&$tokens, $idx_tokens, $i_value)
    {
        $token = $idx_tokens[$i_value[0]];
        $this->current_line = $i_value[2];
        $next_pos = key($tokens);
        $has_block_before = false;

        for ($i = $next_pos - 2; $i > 0; $i--) {
            $tok = $tokens[$i];
            if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) continue;
            if ($tok === '}') $has_block_before = true;
            break;
        }

        if (!$has_block_before) $token .= '_INLINE';

        return array(
            array(
                PHPCF_KEY_CODE => $token,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            )
        );
    }

    // "// comment\n" is split into 2 tokens "// comment" and "\n", and "\n" is prepended to previous whitespace token
    // if present. This split allows not to take easy cases with single-line comments into account

    private function tokenHookComment(&$tokens, $idx_tokens, $i_value)
    {
        $token = $idx_tokens[$i_value[0]];
        $this->current_line = $i_value[2];
        if (substr($i_value[1], 0, 2) == '//' || $i_value[1][0] == '#') {
            // if previous token is whitespace with line feed, then it means that this comment takes whole line
            $prev_pos = key($tokens) - 2; // otherwise it is appended to some expression like this one
            if ($prev_pos > 1 && $tokens[$prev_pos][0] === T_WHITESPACE && strpos($tokens[$prev_pos][1], "\n") !== false) {
                $token = 'T_SINGLE_LINE_COMMENT_ALONE';
            } else {
                $token = 'T_SINGLE_LINE_COMMENT';
            }

            $i_value[1] = rtrim($i_value[1]);
        }

        $ret = array(
            array(
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => $i_value[1],
                PHPCF_KEY_LINE  => $this->current_line,
            ),
        );

        if ($token == 'T_COMMENT') return $ret;

        $this->appendWhiteSpace($tokens, $ret);

        return $ret;
    }

    private function tokenHookConcat(&$tokens, $idx_tokens, $i_value)
    {
        $next_pos = key($tokens);
        $prev_pos = key($tokens) - 2;
        $token = '.';
        if ($tokens[$next_pos][0] === T_WHITESPACE && strpos($tokens[$next_pos][1], "\n") !== false) {
            $token = '._NL';
        } else if ($tokens[$prev_pos][0] === T_WHITESPACE && strpos($tokens[$prev_pos][1], "\n") !== false) {
            $token = '._NL';
        }

        return array(
            array(
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => '.',
                PHPCF_KEY_LINE  => $this->current_line,
            ),
        );
    }

    private function tokenHookConstant(&$tokens, $idx_tokens, $i_value)
    {
        $this->current_line = $i_value[2];
        $value = strtolower($i_value[1]);
        if (!isset($this->constants[$value])) {
            $value = $i_value[1];
        } else if ($value != $i_value[1]) {
            $this->sniffMessage('Reserved word "' . $value . '" must be written in lowercase');
        }

        return array(
            array(
                PHPCF_KEY_CODE  => $idx_tokens[$i_value[0]],
                PHPCF_KEY_TEXT  => $value,
                PHPCF_KEY_LINE  => $this->current_line,
            )
        );
    }

    private function isLongComma(&$tokens, $max_length, $remember_positions = false)
    {
        static $last_long_position = -1;

        $curr_pos = key($tokens) - 1;
        $len = 0;
        $depth = 0;
        // getting first right non-whitespace token length (only if it is not )
        for ($i = $curr_pos + 1; $i < count($tokens); $i++) {
            $tok = $tokens[$i];
            if (is_array($tok)) {
                if ($tok[0] === T_WHITESPACE) {
                    if (strpos($tok[0], "\n") !== false) break;
                    continue;
                }

                $len += strlen($tok[1]);
            } else {
                if ($tok == ')') break;
                $len += strlen($tok);
            }

            break;
        }

        // going backwards to see if there already is more than 120 symbols in line
        for ($i = $curr_pos; $i > 0; $i--) {
            // we can remember that which comma was long and
            // count the next long comma only starting from the previous long one
            if ($i == $last_long_position) break;
            $tok = $tokens[$i];

            // we should stop when line beginning is reached or in another special case described below
            if (is_array($tok)) {
                if (strpos($tok[0], "\n") !== false) break;
                if ($tok[0] === T_WHITESPACE) continue;

                $len += strlen($tok[1]);
            } else {
                if ($tok == ',')      $len += 2;
                else if ($tok == '(') $depth--;
                else if ($tok == ')') $depth++;
                else                  $len += strlen($tok);
            }

            // found opening "(" before reaching max length, which means it is something like this:
            // array( 'something', some_func($argument100, $argument200), 'something else' )
            //                              ^ our brace  ^ our comma
            // such comma must not be long even though the length before newline can be higher than max.
            // comma must be directly related to the array (or argument list), but in this case it does not
            if ($depth < 0) return false;
            if ($depth == 0 && $len >= $max_length) break;
        }

        $is_long = $len >= $max_length;
        if ($remember_positions && $is_long) $last_long_position = $curr_pos;
        return $is_long;
    }

    private function tokenHookComma(&$tokens, $idx_tokens, $i_value)
    {
        $token = ',';
        if ($this->isLongComma($tokens, PHPCF_LONG_EXPRESSION_LENGTH, true)) $token .= '_LONG';

        return array(
            array(
                PHPCF_KEY_CODE => $token,
                PHPCF_KEY_TEXT => ',',
                PHPCF_KEY_LINE => $this->current_line,
            )
        );
    }

    private $token_hook_names = array(
        // hook should return all parsed tokens
        T_START_HEREDOC       => 'tokenHookHeredoc',
        '.'                   => 'tokenHookConcat',
        '"'                   => 'tokenHookDblStr',
        '('                   => 'tokenHookOpenBrace',
        '+'                   => 'tokenHookCheckUnary',
        '-'                   => 'tokenHookCheckUnary',
        '&'                   => 'tokenHookCheckUnary',
        ','                   => 'tokenHookComma',
        T_STATIC              => 'tokenHookStatic',
        T_OPEN_TAG            => 'tokenHookOpenTag',
        T_INC                 => 'tokenHookIncrement',
        T_DEC                 => 'tokenHookIncrement',
        T_WHITESPACE          => 'tokenHookWhiteSpace',
        T_ELSE                => 'tokenHookElse',
        T_ELSEIF              => 'tokenHookElse',
        T_COMMENT             => 'tokenHookComment',
        T_STRING              => 'tokenHookConstant',
        T_VAR                 => 'tokenHookClassdef',
        T_PUBLIC              => 'tokenHookClassdef',
        T_PROTECTED           => 'tokenHookClassdef',
        T_PRIVATE             => 'tokenHookClassdef',
        T_CONST               => 'tokenHookClassdef',
        T_FINAL               => 'tokenHookClassdef',
    );

    private function prepareTokens($body)
    {
        $tokens = token_get_all($body);

        $list_tokens = preg_grep('/^T_/', array_keys(get_defined_constants()));
        $idx_tokens = array();
        foreach ($list_tokens as $i_token) {
            $idx_tokens[constant($i_token)] = $i_token;
        }

        $this->tokens = array();
        $in_nowdoc = $in_heredoc = $in_string = false;
        $heredoc_value = $string_value = '';
        // iterate array manually so that we can read several tokens in hooks and auto-advance position
        reset($tokens);
        while (list(, $i_value) = each($tokens)) {
            $tok = is_array($i_value) ? $i_value[0] : $i_value;
            if (isset($this->token_hook_names[$tok])) {
                $method_name = $this->token_hook_names[$tok];
                $result = $this->$method_name($tokens, $idx_tokens, $i_value);
                foreach ($result as $parsed_token) {
                    $this->tokens[] = $parsed_token;
                }
                continue;
            }

            if (is_array($i_value)) {
                $this->current_line = $i_value[2];
                $this->tokens[] = array(
                    PHPCF_KEY_CODE => $idx_tokens[$i_value[0]],
                    PHPCF_KEY_TEXT => $i_value[1],
                    PHPCF_KEY_LINE => $this->current_line,
                );
                $this->current_line += substr_count($i_value[1], "\n");
            } else {
                $this->tokens[] = array(
                    PHPCF_KEY_CODE => $i_value,
                    PHPCF_KEY_TEXT => $i_value,
                    PHPCF_KEY_LINE => $this->current_line,
                );
            }
        }
    }

    public function dumpStruct()
    {
        echo str_repeat("=", 100) . "\n";
        $i = 0;
        ob_start();
        foreach ($this->tokens as $i_data) {
            printf("%5d: ", $i);
            echo $i_data[PHPCF_KEY_CODE] . " " . $this->humanWhiteSpace($i_data[PHPCF_KEY_TEXT], true);
            if (isset($i_data[PHPCF_KEY_TOKEN_LENGTH])) {
                echo " " . $i_data[PHPCF_KEY_TOKEN_LENGTH];
            }
            echo "\n";
            $i++;
        }
        ob_end_flush();
    }

    public function dump()
    {
        echo str_repeat("=", 100) . "\n";
        $i = 0;
        $n_exec = count($this->exec);
        while ($i < $n_exec) {
            $i_exec = $this->exec[$i];
            if (is_string($i_exec)) {
                echo sprintf("%5d", $i) . ': ' . $this->humanWhiteSpace($i_exec, true) . "\n";
                $i++;
            } else {
                $list_exec = array();
                while (!is_string($i_exec)) {
                    if (is_array($i_exec)) {
                        foreach ($i_exec as $i_byte) {
                            $list_exec[] = $this->getHumanReadableExecByte($i_byte);
                        }
                    } else {
                        $list_exec[] = $this->getHumanReadableExecByte($i_exec);
                    }
                    $i++;
                    $i_exec = $this->exec[$i];
                }
                echo sprintf("%5d", $i) . ': PHPCF_EXEC = {' . implode(', ', $list_exec) . "}\n";
            }
        }
    }

    private function getHumanReadableExecByte($byte)
    {
        static $map = array(
            PHPCF_EX_DELETE_SPACES         => 'PHPCF_EX_DELETE_SPACES',
            PHPCF_EX_DELETE_SPACES_STRONG  => 'PHPCF_EX_DELETE_SPACES_STRONG',
            PHPCF_EX_SHRINK_SPACES         => 'PHPCF_EX_SHRINK_SPACES',
            PHPCF_EX_SHRINK_SPACES_STRONG  => 'PHPCF_EX_SHRINK_SPACES_STRONG',
            PHPCF_EX_SHRINK_NLS            => 'PHPCF_EX_SHRINK_NLS',
            PHPCF_EX_SHRINK_NLS_STRONG     => 'PHPCF_EX_SHRINK_NLS_STRONG',
            PHPCF_EX_SHRINK_NLS_2          => 'PHPCF_EX_SHRINK_NLS_2',
            PHPCF_EX_CHECK_NL              => 'PHPCF_EX_CHECK_NL',
            PHPCF_EX_CHECK_NL_STRONG       => 'PHPCF_EX_CHECK_NL_STRONG',
            PHPCF_EX_INCREASE_INDENT       => 'PHPCF_EX_INCREASE_INDENT',
            PHPCF_EX_DECREASE_INDENT       => 'PHPCF_EX_DECREASE_INDENT',
            PHPCF_EX_NL_OR_SPACE           => 'PHPCF_EX_NL_OR_SPACE',
            PHPCF_EX_DO_NOT_TOUCH_ANYTHING => 'PHPCF_EX_DO_NOT_TOUCH_ANYTHING'
        );

        if (empty($map[$byte])) return 'UNKNOWN - "' . $byte . '"';

        return $map[$byte];
    }

    private function getHumanReadableExecSequence($sequence)
    {
        $parts = array();

        foreach ($sequence as $v) {
            if (is_int($v)) $parts[] = $this->getHumanReadableExecByte($v);
            else            $parts[] = $this->humanWhiteSpace($v);
        }

        return implode(', ', $parts);
    }

    public function sanitizeWhiteSpace($s)
    {
        return strtr($s, $this->white_space_map);
    }

    public function exec($lines = null)
    {
        if ($this->isDebug()) {
            $this->dumpStruct();
            $this->dump();
            echo str_repeat("=", 100) . "\n";
        }

        if (!count($this->exec)) return ''; // empty file

        $exec_ctx = array();
        $exec_sequence = array();
        $out = '';
        foreach ($this->exec as $i => $i_exec) {
            $ctx = $this->exec_ctx[$i];
            $line = $this->tokens[$ctx['current_pos']][PHPCF_KEY_LINE];

            if (is_string($i_exec)) {
                if ($ctx['whitespace']) {
                    if (!isset($lines) || isset($lines[$line])) {
                        $exec_sequence[] = $this->sanitizeWhiteSpace($i_exec);
                    } else {
                        $exec_sequence[] = $i_exec;
                    }
                    $exec_ctx[] = $ctx;
                } else {
                    if (!empty($exec_sequence)) {
                        $out .= $this->execSequence($exec_sequence, $exec_ctx, $line, $lines);
                        $exec_sequence = array();
                        $exec_ctx = array();
                    }
                    $out .= $i_exec;
                }
            } elseif (is_array($i_exec)) {
                foreach ($i_exec as $i) {
                    $exec_sequence[] = $i;
                    $exec_ctx[] = $ctx;
                }
            } else {
                $exec_sequence[] = $i_exec;
                $exec_ctx[] = $ctx;
            }
        }
        if (!empty($exec_sequence)) {
            $out .= $this->execSequence($exec_sequence, $exec_ctx, $line, $lines);
        }

        return $out;
    }

    static $exec_methods = array(
        PHPCF_EX_CHECK_NL_STRONG       => 'execCheckNewline',
        PHPCF_EX_CHECK_NL              => 'execCheckNewline',
        PHPCF_EX_DELETE_SPACES_STRONG  => 'execDeleteSpaces',
        PHPCF_EX_DELETE_SPACES         => 'execDeleteSpaces',
        PHPCF_EX_SHRINK_SPACES         => 'execShrinkSpaces',
        PHPCF_EX_SHRINK_SPACES_STRONG  => 'execShrinkSpaces',
        PHPCF_EX_SHRINK_NLS_2          => 'execShrinkNewlines2',
        PHPCF_EX_SHRINK_NLS            => 'execShrinkNewlines',
        PHPCF_EX_SHRINK_NLS_STRONG     => 'execShrinkNewlines',
        PHPCF_EX_NL_OR_SPACE           => 'execNewlineOrSpace',
        PHPCF_EX_DO_NOT_TOUCH_ANYTHING => 'execNothing',
    );

    private function execCheckNewline($in)
    {
        if (substr_count($in, "\n") >= 2) return "\n\n"; // allow 2 new lines
        return "\n";
    }

    private function execDeleteSpaces($in)
    {
        return '';
    }

    private function execShrinkSpaces($in)
    {
        return ' ';
    }

    private function execShrinkNewlines($in)
    {
        return "\n";
    }

    private function execShrinkNewlines2($in)
    {
        return "\n\n";
    }

    private function execNewlineOrSpace($in)
    {
        if (strpos($in, "\n") !== false) return "\n";
        return ' ';
    }

    private function execNothing($in)
    {
        return $in;
    }

    private function execSequence($sequence, $exec_ctx, $line, $lines)
    {
        $c = array();
        $in = '';
        $out = '';
        $context = array(
            'descr'       => 'correct indentation level',
            'current_pos' => $exec_ctx[0] ? $exec_ctx[0]['current_pos'] : null,
        );

        for ($i = 0; $i < count($sequence); $i++) {
            if (is_int($sequence[$i])) {
                if (PHPCF_EX_INCREASE_INDENT == $sequence[$i]) {
                    $this->indent_level++;
                } elseif (PHPCF_EX_DECREASE_INDENT == $sequence[$i]) {
                    if ($this->indent_level > 0) {
                        $this->indent_level--;
                    }
                } else {
                    $c[$sequence[$i]] = $exec_ctx[$i] ? $exec_ctx[$i] : 1;
                }
            } else {
                $in .= $sequence[$i];
            }
        }

        // account for ignoring lines
        if (isset($lines) && !isset($lines[$line])) return $in;

        if (count($c)) {
            // the executors with less value have higher precedence
            $min_key = min(array_keys($c));
            $action = self::$exec_methods[$min_key];
            $out = $this->$action($in);
            $context = $c[$min_key];
        } else {
            $out = $in;
        }

        if (false !== strpos($out, "\n")) {
            $out = rtrim($out, ' ');
            $out .= $this->getIndentString();
        }

        if ($in !== $out) {
            $this->sniff($in, $out, $context, $sequence);
        }

        return $out;
    }

    function humanWhiteSpace($str, $color = false)
    {
        static $whitespace_map, $colored_whitespace_map;
        if (!isset($whitespace_map)) {
            $whitespace_map = array("\n" => '\n', "\r" => '\r', "\t" => '\t', " " => ' ');
            $colored_whitespace_map = array("\n" => '\n', "\r" => '\r', "\t" => '\t', " " => '·');
            foreach ($colored_whitespace_map as &$v) {
                $v = "\033[38;5;246m" . $v . "\033[0m";
            }
            unset($v);
        }

        return strtr($str, $this->color && $color ? $colored_whitespace_map : $whitespace_map);
    }

    private function msg($message)
    {
        fwrite(STDERR, $message . "\n");
    }

    private function sniffMessage($message)
    {
        if (!PHPCF_SNIFF) return;
        $this->sniff_exit_code = 1;

        $this->sniff_errors[$message][] = $this->current_line;

        // $this->msg(sprintf("line%6d) %s\n\n", $this->current_line, $message));
    }

    private function sniff($in, $out, $context, $sequence)
    {
        if (!PHPCF_SNIFF) return;

        $this->sniff_exit_code = 1;
        $current_pos = $context['current_pos'];

        $this->sniff_errors[$context['descr']][] = $this->tokens[$current_pos][PHPCF_KEY_LINE];
    }

    function printSnifferMessages($lines_opt)
    {
        if (!PHPCF_SNIFF || !count($this->sniff_errors)) return;

        if (PHPCF_SUMMARY) {
            if ($this->sniff_errors) {
                $this->msg('Total errors: ' . array_sum(array_map('count', $this->sniff_errors)));
            }
        } else {
            if (count($this->sniff_errors) && isset($lines_opt)) {
                $this->msg('    Checking with --lines=' . $lines_opt . "\n");
            }

            foreach ($this->sniff_errors as $text => $lines) {
                $lines = array_unique($lines);
                $text = '    Expecting ' . lcfirst($text);
                $wrapped_lines = wordwrap(implode(' ', $lines), 70, "\n         ");
                $msg = sprintf("%s:\n       line%s %s\n", $text, count($lines) > 1 ? 's' : '', $wrapped_lines);
                $this->msg($msg);
            }
        }
    }

    private function next()
    {
        if ($this->current_pos + 1 >= count($this->tokens)) {
            return false;
        }

        $this->current_pos++;
        $this->setContext();
        return $this->tokens[$this->current_pos];
    }

    private function setContext()
    {
        // $this->debug("==== FSM->setContext called for " . $this->getCode());
        $this->FSM->transit($this->tokens[$this->current_pos][PHPCF_KEY_CODE]);
        $this->context = $this->FSM->getState();
        // $this->debug("==== FSM->setContext sets new context to " . $this->context);
    }

    private function initControls($controls)
    {
        $this->controls = array();
        foreach ($controls as $key => $data) {
            $list_keys = explode(' ', $key);
            if (empty($list_keys)) {
                $list_keys = array($key);
            }

            foreach ($list_keys as $i_key) {
                foreach ($data as $j_key => $j_data) {
                    $j_list_keys = explode(' ', $j_key);
                    if (empty($j_list_keys)) {
                        $j_list_keys = array($j_key);
                    }
                    foreach ($j_list_keys as $k) {
                        $this->controls[$i_key][$k] = $j_data;
                    }
                }
            }
        }
    }

    private function getContext()
    {
        $rules = $this->FSM->current_rules;
        return array(
            'current_pos' => $this->current_pos,
            'whitespace'  => $this->tokens[$this->current_pos][PHPCF_KEY_CODE] === 'T_WHITESPACE',
        );
    }

    public function process()
    {
        if (!$this->tokens) return; // empty file

        $this->setContext();
        do {
            $cur_token = $this->tokens[$this->current_pos];
            $i_code = $cur_token[PHPCF_KEY_CODE];
            $i_text = $cur_token[PHPCF_KEY_TEXT];

            if ($this->isDebug()) {
                $debug_code = sprintf("%30s", $i_code);
                $whitespaces = str_repeat(' ', max(0, 30 - strlen($i_text)));
                $msg  = $debug_code . "     " . $this->humanWhiteSpace($i_text, true) . $whitespaces;
                $msg .= "     " . $this->FSM->getStackPath();

                $this->debug($msg);
            }

            $rule_context = $this->getContext();

            if (empty($this->controls[$i_code])) {
                $this->exec[] = $i_text;
                $this->exec_ctx[] = $rule_context;
                continue;
            }

            $i_controls = $this->controls[$i_code];
            $i_context_controls = false;
            if (!empty($i_controls[$this->context])) {
                $i_context_controls = $i_controls[$this->context];
            } elseif (!empty($i_controls[PHPCF_KEY_ALL])) {
                $i_context_controls = $i_controls[PHPCF_KEY_ALL];
            }

            if (!empty($i_context_controls)) {
                $this->processControls($i_context_controls, $rule_context);
            } else {
                $this->exec[] = $i_text;
                $this->exec_ctx[] = $rule_context;
            }
        } while ($this->next());
    }

    private function isWhiteSpaceAligned($pos)
    {
        if (!isset($this->tokens[$pos][PHPCF_KEY_CODE])) return false;
        return $this->tokens[$pos][PHPCF_KEY_CODE] == 'T_WHITESPACE_ALIGNED';
    }

    function processControls($controls, $context)
    {
        $i = count($this->exec);
        if (!empty($controls[PHPCF_KEY_LEFT]) && !$this->isWhiteSpaceAligned($this->current_pos - 1)) {
            $c = $controls[PHPCF_KEY_LEFT];
            $this->exec_ctx[$i] = $context + array('descr' => $controls[PHPCF_KEY_DESCR_LEFT], 'from' => PHPCF_KEY_LEFT);
            if (is_array($c)) {
                $this->exec[$i] = array();
                foreach ($c as $i_c) $this->exec[$i][] = $i_c;
            } else {
                $this->exec[$i] = array($c);
            }
            $i++;
        }

        $this->exec_ctx[$i] = $context;
        $this->exec[$i] = $this->tokens[$this->current_pos][PHPCF_KEY_TEXT];

        if (!empty($controls[PHPCF_KEY_RIGHT]) && !$this->isWhiteSpaceAligned($this->current_pos + 1)) {
            $i++;
            $c = $controls[PHPCF_KEY_RIGHT];
            $this->exec_ctx[$i] = $context + array('descr' => $controls[PHPCF_KEY_DESCR_RIGHT], 'from' => PHPCF_KEY_RIGHT);
            if (is_array($c)) {
                $this->exec[$i] = array();
                foreach ($c as $i_c) $this->exec[$i][] = $i_c;
            } else {
                $this->exec[$i] = $c;
            }
        }
    }

    function getIndentString()
    {
        return str_repeat($this->indent_char, $this->indent_level * $this->indent_width);
    }
}

$casts = 'T_INT_CAST T_DOUBLE_CAST T_BOOL_CAST T_STRING_CAST T_ARRAY_CAST T_OBJECT_CAST T_UNSET_CAST';

$controls = array(
    '{' => array(
        'CTX_INLINE_BRACE' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "{" in "->{...}" expression',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after "{" in "->{...}" expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ),
        'CTX_FUNCTION_D CTX_CLASS_D CTX_CLASS_METHOD_D' => array(
            PHPCF_KEY_DESCR_LEFT => 'New line before "{" in function/method/class declaration',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
            PHPCF_KEY_DESCR_RIGHT => 'Indent and 1 or 2 newlines with proper indent after "{"',
            PHPCF_KEY_RIGHT => array(PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS),
        ),
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'One space before "{"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'Indent and 1 or 2 newlines with proper indent after "{"',
            PHPCF_KEY_RIGHT => array(PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS),
        ),
    ),
    '}' => array(
        'CTX_INLINE_BRACE' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "}" in "->{...}" expression',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after "}" in "->{...}" expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ),
        'CTX_CASE_END_OF_BLOCK' => array(
            PHPCF_KEY_DESCR_LEFT => 'Unindent and 1 or 2 newlines with proper indent after "}"',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS),
            PHPCF_KEY_DESCR_RIGHT => '1 or 2 newlines with proper indent after "}"',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ),
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'Unindent and 1 or 2 newlines with proper indent after "}"',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS),
            PHPCF_KEY_DESCR_RIGHT => '1 or 2 newlines with proper indent after "}"',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ),
    ),
    'T_IF T_FOR T_FOREACH T_DO T_ELSE_INLINE T_ELSEIF_INLINE T_TRY' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => '1 or 2 newlines with proper indent before control statements',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            PHPCF_KEY_DESCR_RIGHT => 'One space after control statements',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ),
    ),
    'T_AS T_ELSEIF T_ELSE T_CATCH' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'One space before as, elseif, else, catch',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after as, elseif, else, catch',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        )
    ),
    'T_WHILE' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => '1 or 2 newlines with proper indent before "while"',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "while"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ),
        'CTX_WHILE_AFTER_DO' => array(
            PHPCF_KEY_DESCR_LEFT => '1 or 2 newlines with proper indent before "while" in "do/while"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "while" in "do/while"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ),
    ),
    'T_CLASS T_INTERFACE' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => '2 newlines with proper indent before "class/interface"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS_2,
            PHPCF_KEY_DESCR_RIGHT => 'One space after class/interface name',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ),
    ),
    'T_EXTENDS T_IMPLEMENTS' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'One space before "extends/implements"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "extends/implements"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        )
    ),
    'T_STATIC T_VAR T_PUBLIC T_PRIVATE T_PROTECTED T_CONST T_FINAL T_ABSTRACT' => array(
        'CTX_CLASS' => array(
            PHPCF_KEY_DESCR_LEFT => '1 or 2 newlines with proper indent before class properties/methods declarations',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "final", "abstract", "public" etc.',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ),
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_RIGHT => 'One space after "final", "abstract", "public" etc.',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ),
    ),
    'T_STATIC_NL T_VAR_NL T_PUBLIC_NL T_PRIVATE_NL T_PROTECTED_NL T_CONST_NL T_FINAL_NL T_ABSTRACT_NL' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_RIGHT => 'New line and indent after end of property declaration',
            PHPCF_KEY_RIGHT => array(PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS),
        ),
    ),
    'T_OBJECT_OPERATOR T_DOUBLE_COLON' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "::" and "->"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after "::" and "->"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ),
    ),
    '( (_LONG' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "("',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after "("',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ),
        'CTX_GENERIC_PARENTHESIS' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "("',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after "("',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ),
        'CTX_LONG_PARENTHESIS CTX_ARRAY_LONG_PARENTHESIS' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "("',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'Newline after "(" in long expression',
            PHPCF_KEY_RIGHT => array(PHPCF_EX_INCREASE_INDENT, PHPCF_EX_CHECK_NL)
        ),
    ),
    ')' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ")"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ")"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ),
        'CTX_GENERIC_PARENTHESIS' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ")"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ")"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ),
        'CTX_LONG_PAR_END' => array(
            PHPCF_KEY_DESCR_LEFT => 'Unindent and newline before ")" in long expression',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_CHECK_NL),
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after ")" in long expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ),
        'CTX_INLINE_EXPR_NL_END' => array(
            PHPCF_KEY_DESCR_LEFT => 'Unindent and no whitespace before ")" in end of long expression',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DELETE_SPACES_STRONG),
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after ")" in long expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ),
        'CTX_LONG_EXPR_NL_END' => array(
            PHPCF_KEY_DESCR_LEFT => 'Unindent and newline before ")" in long expression',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DECREASE_INDENT, PHPCF_EX_CHECK_NL),
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after ")" in long expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        )
    ),
    ';' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ";"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => '1 or 2 newlines with proper indent after ";"',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ),
        'CTX_FOR_PARENTHESIS' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ";"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ";" in for()',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ),
        'CTX_INLINE_EXPR_NL_END' => array(
            PHPCF_KEY_DESCR_LEFT => 'Unindent and no whitespace before ")" in end of long expression',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DELETE_SPACES_STRONG),
            PHPCF_KEY_DESCR_RIGHT => '1 or 2 newlines with proper indent after ";"',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ),
        'CTX_CLASS_CONST_NL_END CTX_CLASS_VARIABLE_D_NL_END' => array(
            PHPCF_KEY_DESCR_LEFT => 'Unindent and no whitespace before ";" in end of long property definition',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DELETE_SPACES_STRONG),
            PHPCF_KEY_DESCR_RIGHT => '1 or 2 newlines with proper indent after ";"',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ),
    ),
    'T_AND_EQUAL T_CONCAT_EQUAL T_DIV_EQUAL T_IS_EQUAL T_IS_GREATER_OR_EQUAL '
        . 'T_IS_NOT_EQUAL T_IS_SMALLER_OR_EQUAL T_MINUS_EQUAL T_MOD_EQUAL T_MUL_EQUAL '
        . 'T_OR_EQUAL T_PLUS_EQUAL T_SL_EQUAL T_SR_EQUAL T_XOR_EQUAL T_COALESCE_EQUAL '
        . '= + & - * ^ % / ? | < > . T_IS_IDENTICAL T_IS_NOT_IDENTICAL T_IS_EQUAL T_IS_NOT_EQUAL '
        . 'T_LOGICAL_AND T_BOOLEAN_AND T_LOGICAL_OR T_BOOLEAN_OR T_LOGICAL_XOR' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'One space before binary operators (= < > * . etc) ',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after binary operators (= < > * . etc) ',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        )
    ),
    'T_DOUBLE_ARROW' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'One space before "=>"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "=>"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ),
        'CTX_INLINE_EXPR_NL_END CTX_LONG_EXPR_NL_END' => array(
            PHPCF_KEY_DESCR_LEFT => 'One space before "=>"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "=>"',
            PHPCF_KEY_RIGHT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_SPACES_STRONG),
        )
    ),
    '+_UNARY -_UNARY &_UNARY ! @ $ ~' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace before unary: + - & ! @ $',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ),
    ),
    '._NL' => array(
        'CTX_INLINE_FIRST_NL CTX_LONG_FIRST_NL' => array(
            PHPCF_KEY_DESCR_LEFT => 'Single space before "." in multiline concatenation',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'Indent and newline after "." in long expression',
            PHPCF_KEY_RIGHT => array(PHPCF_EX_INCREASE_INDENT, PHPCF_EX_CHECK_NL)
        ),
        'CTX_INLINE_EXPR_NL CTX_LONG_EXPR_NL' => array(
            PHPCF_KEY_DESCR_LEFT => 'Single space before "." in multiline concatenation',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'Same indent and newline after "."  in long expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ),
    ),
    ',' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ","',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ),
        'CTX_CLASS_CONST_D_NL CTX_CLASS_VARIABLE_D_NL' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => '1 newline with proper indent after "," in property definition',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ),
        'CTX_LONG_PARENTHESIS' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => '1 newline with proper indent after "," in long expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ),
        'CTX_ARRAY_LONG_PARENTHESIS' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'Either newline or space with proper indent after "," in long array',
            PHPCF_KEY_RIGHT => PHPCF_EX_NL_OR_SPACE,
        ),
    ),
    ',_LONG' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ","',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ),
        'CTX_CLASS_CONST_D_NL CTX_CLASS_VARIABLE_D_NL' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => '1 newline with proper indent after "," in property definition',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS_STRONG,
        ),
        'CTX_LONG_PARENTHESIS' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => '1 newline with proper indent after "," in long expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ),
        'CTX_ARRAY_LONG_PARENTHESIS' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => '1 newline with proper indent after "," in item list in array',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ),
    ),
    '[' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "["',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after "["',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ),
    ),
    ']' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "]"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
        ),
    ),
    ':' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ":"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after ":"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ),
        'CTX_CASE_COLON' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ":" in "case:"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'Indent and 1 or 2 newlines with proper indent after "case:"',
            PHPCF_KEY_RIGHT => array(PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS),
        ),
        'CTX_CASE_MULTI_COLON' => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ":" in "case:" (multi colon)',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'Indent and 1 or 2 newlines with proper indent after "case:" (multi colon)',
            PHPCF_KEY_RIGHT => array(PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS),
        ),
        'CTX_TERNARY_OPERATOR' => array(
            PHPCF_KEY_DESCR_LEFT => 'One space before ":" in ternary operator ( ... ? ... : ... )',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ":" in ternary operator ( ... ? ... : ... )',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ),
    ),
    'T_SWITCH' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => '1 or 2 newlines with proper indent before "switch"',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "switch"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ),
    ),
    'T_CASE' => array(
        'CTX_CASE_FIRST_D' => array(
            PHPCF_KEY_DESCR_LEFT => '1 newline with proper indent before first "case/default"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "case"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES
        ),
        'CTX_CASE_D' => array(
            PHPCF_KEY_DESCR_LEFT => '2 newlines with proper indent before "case"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS_2,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "case"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES
        ),
        'CTX_CASE_MULTI_D' => array(
            PHPCF_KEY_DESCR_LEFT => '1 newline with proper indent before repeated "case"',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS),
            PHPCF_KEY_DESCR_RIGHT => 'One space after "case"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES
        ),
        'CTX_NOBREAK_CASE_D' => array(
            PHPCF_KEY_DESCR_LEFT => '2 newlines with proper indent before "case" without break',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS_2),
            PHPCF_KEY_DESCR_RIGHT => 'One space after "case"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ),
    ),
    'T_DEFAULT' => array(
        'CTX_CASE_FIRST_D' => array(
            PHPCF_KEY_DESCR_LEFT => '1 newline with proper indent before first "case/default"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
            PHPCF_KEY_DESCR_RIGHT => 'No spaces after "default"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES
        ),
        'CTX_CASE_D' => array(
            PHPCF_KEY_DESCR_LEFT => '2 newlines with proper indent before "default"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS_2,
            PHPCF_KEY_DESCR_RIGHT => 'No spaces after "default"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES
        ),
        'CTX_CASE_MULTI_D' => array(
            PHPCF_KEY_DESCR_LEFT => '1 newline with proper indent before repeated "default"',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS),
            PHPCF_KEY_DESCR_RIGHT => 'No spaces after "default"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES
        ),
        'CTX_NOBREAK_CASE_D' => array(
            PHPCF_KEY_DESCR_LEFT => '2 newlines with proper indent before "default" without break',
            PHPCF_KEY_LEFT => array(PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS_2),
            PHPCF_KEY_DESCR_RIGHT => 'No spaces after "default"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES
        ),
    ),
    'T_BREAK' => array(
        // PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        // breaks the following construct: "break 3;"
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "break"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
        ),
        'CTX_IF CTX_ELSEIF CTX_ELSE' => array(
            PHPCF_KEY_DESCR_LEFT => '1 space before "break" in oneline "if/else/elseif"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ),
        'CTX_CASE_BREAK' => array(
            PHPCF_KEY_DESCR_LEFT => '1 or 2 newlines with proper indent before "break"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
            PHPCF_KEY_DESCR_RIGHT => 'Indent and no whitespace before break',
            PHPCF_KEY_RIGHT => array(PHPCF_EX_DECREASE_INDENT),
        ),
    ),
    'T_ECHO T_RETURN' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_RIGHT => 'One space after "echo" and "return"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ),
    ),
    'T_FUNCTION' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_RIGHT => 'One space after "function"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ),
    ),
    // $a--, $b++
    'T_INC_RIGHT T_DEC_RIGHT' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespaces in "$c++" and type casts',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
        ),
    ),
    // --$a, --$b
    'T_INC_LEFT T_DEC_LEFT ' . $casts => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_RIGHT => 'No whitespaces in "++$c" and type casts',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ),
    ),
    /* DO NOT CHANGE rules for comments unless you really know what you are doing */
    'T_SINGLE_LINE_COMMENT' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'One space in comment after expression',
            PHPCF_KEY_LEFT => PHPCF_EX_DO_NOT_TOUCH_ANYTHING,
            PHPCF_KEY_DESCR_RIGHT => 'New line after single-line comment',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL_STRONG,
        )
    ),
    'T_SINGLE_LINE_COMMENT_ALONE' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_RIGHT => 'New line after single-line comment',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL_STRONG,
        ),
    ),
    /* / DO NOT CHANGE */
    'T_OPEN_TAG' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_RIGHT => 'New line after opening tag',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL_STRONG,
        )
    )
);

$fsm_parenthesis_rules = array(
    '(_LONG' => array('CTX_LONG_PARENTHESIS'),
    '('      => array('CTX_GENERIC_PARENTHESIS'),
);

$fsm_inline_rules = $fsm_parenthesis_rules + array(
    '?'                 => array('CTX_TERNARY_BEGIN'),
    'T_OBJECT_OPERATOR' => array('CTX_INLINE_BRACE_BEGIN'),
    '$'                 => array('CTX_INLINE_BRACE_BEGIN'),
    '._NL'              => array('CTX_INLINE_FIRST_NL'),
    'T_ARRAY'           => array('CTX_ARRAY'),
);

$fsm_generic_code_rules = array(
    'T_CLOSE_TAG'       => 'CTX_DEFAULT',
    'T_FINAL'           => array('CTX_CLASS_D'),
    'T_ABSTRACT'        => array('CTX_CLASS_D'),
    'T_CLASS'           => array('CTX_CLASS_D'),
    'T_INTERFACE'       => array('CTX_CLASS_D'),
    'T_FUNCTION'        => array('CTX_FUNCTION_D'),
    'T_SWITCH'          => array('CTX_SWITCH'),
    'T_WHILE'           => array('CTX_WHILE'),
    'T_FOREACH'         => array('CTX_FOREACH'),
    'T_FOR'             => array('CTX_FOR'),
    'T_DO'              => array('CTX_DO'),
    'T_IF'              => array('CTX_IF'),
    'T_ELSEIF'          => array('CTX_ELSEIF'),
    'T_ELSE'            => array('CTX_ELSE'),
    'T_CASE'            => array('CTX_CASE_FIRST_D'),
    'T_DEFAULT'         => array('CTX_CASE_FIRST_D'),
) + $fsm_inline_rules;

$fsm_generic_code_block_rules = $fsm_generic_code_rules + array('}' => -1, '{' => array('CTX_GENERIC_BLOCK'));
$fsm_context_rules_switch = array(
    'CTX_SWITCH' => array(
        '{' => 'CTX_GENERIC_BLOCK',
    ),
    'CTX_SWITCH_BLOCK' => array(
        'T_CASE'    => array('CTX_CASE_D'),
        'T_DEFAULT' => array('CTX_CASE_D'),
    ) + $fsm_generic_code_block_rules,
    'CTX_CASE_D CTX_CASE_FIRST_D CTX_NOBREAK_CASE_D' => array(
        ':' => 'CTX_CASE_MULTI_COLON',
    ),
    'CTX_CASE_MULTI_D' => array(
        ':' => 'CTX_CASE_MULTI_COLON',
    ),
    'CTX_CASE_MULTI_COLON' => array(
        'T_CASE' => 'CTX_CASE_MULTI_D',
        'T_DEFAULT' => 'CTX_CASE_MULTI_D',
        'T_BREAK' => 'CTX_CASE_BREAK',
        PHPCF_KEY_ALL => 'CTX_CASE',
    ),
    'CTX_CASE' => array(
        'T_CASE'    => 'CTX_NOBREAK_CASE_D',
        'T_DEFAULT' => 'CTX_NOBREAK_CASE_D',
        'T_BREAK'   => 'CTX_CASE_BREAK',
        '}' => array(PHPCF_CTX_NOW => 'CTX_CASE_END_OF_BLOCK', PHPCF_CTX_NEXT => -2),
    ) + $fsm_generic_code_block_rules,
    'CTX_CASE_BREAK' => array(
        ';' => array(PHPCF_CTX_NOW => -1, PHPCF_CTX_NEXT => 'CTX_SWITCH_BLOCK',),
    ),
);

$fsm_context_rules_loops = array(
    'CTX_WHILE' => array(
        '{' => 'CTX_GENERIC_BLOCK',
        ';' => -1
    ) + $fsm_inline_rules,
    'CTX_FOREACH' => array(
        '{' => 'CTX_GENERIC_BLOCK',
        ';' => -1
    ) + $fsm_inline_rules,
    'CTX_FOR' => array(
        '('      => 'CTX_FOR_PARENTHESIS',
        '(_LONG' => 'CTX_FOR_PARENTHESIS',
    ),
    'CTX_DO' => array(
        '{' => array('CTX_GENERIC_BLOCK'),
        'T_WHILE' => 'CTX_WHILE_AFTER_DO',
        PHPCF_KEY_ALL => -1
    ),
    'CTX_WHILE_AFTER_DO' => array(
        ';' => -1,
    ),
);

$fsm_context_rules_parenthesis = array(
    'CTX_FOR_PARENTHESIS' => array(
        ')' => -1,
    ) + $fsm_inline_rules,
    'CTX_GENERIC_PARENTHESIS' => array(
        ')' => -1,
    ) + $fsm_inline_rules,
    'CTX_LONG_PARENTHESIS CTX_ARRAY_LONG_PARENTHESIS' => array(
        ')'     => array(PHPCF_CTX_NOW => 'CTX_LONG_PAR_END', PHPCF_CTX_NEXT => -1),
        '._NL'  => array('CTX_LONG_FIRST_NL'),
    ) + $fsm_inline_rules,
);

$fsm_context_rules_conditions = array(
    'CTX_IF CTX_ELSEIF' => array(
        '(_LONG' => array('CTX_GENERIC_PARENTHESIS'),
        '{'      => 'CTX_GENERIC_BLOCK',
        ';'      => -1,
    ) + $fsm_inline_rules,
    'CTX_ELSE' => array(
        '{' => 'CTX_GENERIC_BLOCK',
        ';' => -1,
    ),
    'CTX_TERNARY_BEGIN' => array(
        ':' => array(PHPCF_CTX_NOW => 'CTX_TERNARY_OPERATOR', PHPCF_CTX_NEXT => -1),
    ) + $fsm_inline_rules,
);

$fsm_context_rules_array = array(
    'CTX_ARRAY' => array(
        '(_LONG' => 'CTX_ARRAY_LONG_PARENTHESIS',
        '('      => 'CTX_GENERIC_PARENTHESIS',
    ),
);

$fsm_context_rules_class = array(
    'CTX_CLASS_D' => array(
        '{' => array(PHPCF_CTX_NOW => 'CTX_CLASS_D', PHPCF_CTX_NEXT => 'CTX_CLASS'),
    ),
    'CTX_CLASS' => array(
        'T_PUBLIC'            => array('CTX_CLASS_DEF'),
        'T_CONST'             => array('CTX_CLASS_DEF'),
        'T_PRIVATE'           => array('CTX_CLASS_DEF'),
        'T_PROTECTED'         => array('CTX_CLASS_DEF'),
        'T_STATIC'            => array('CTX_CLASS_DEF'),
        'T_CONST'             => array('CTX_CLASS_DEF'),
        'T_FINAL'             => array('CTX_CLASS_DEF'),
        'T_ABSTRACT'          => array('CTX_CLASS_DEF'),
        'T_PUBLIC_NL'         => array('CTX_CLASS_DEF_NL'),
        'T_CONST_NL'          => array('CTX_CLASS_DEF_NL'),
        'T_PRIVATE_NL'        => array('CTX_CLASS_DEF_NL'),
        'T_PROTECTED_NL'      => array('CTX_CLASS_DEF_NL'),
        'T_STATIC_NL'         => array('CTX_CLASS_DEF_NL'),
        'T_CONST_NL'          => array('CTX_CLASS_DEF_NL'),
        'T_FINAL'             => array('CTX_CLASS_DEF_NL'),
        'T_VAR'               => array('CTX_CLASS_VARIABLE_D'),
        'T_FUNCTION'          => array('CTX_CLASS_METHOD_D'),
        '}'                   => -1,
    ),
    'CTX_CLASS_DEF' => array(
        'T_FUNCTION'      => 'CTX_CLASS_METHOD_D',
        'T_CONST'         => 'CTX_CLASS_CONST_D',
        'T_VARIABLE'      => 'CTX_CLASS_VARIABLE_D',
        'T_PUBLIC_NL'     => 'CTX_CLASS_DEF_NL',
        'T_CONST_NL'      => 'CTX_CLASS_DEF_NL',
        'T_PRIVATE_NL'    => 'CTX_CLASS_DEF_NL',
        'T_PROTECTED_NL'  => 'CTX_CLASS_DEF_NL',
        'T_STATIC_NL'     => 'CTX_CLASS_DEF_NL',
        'T_CONST_NL'      => 'CTX_CLASS_DEF_NL',
        'T_FINAL'         => 'CTX_CLASS_DEF_NL',
    ),
    'CTX_CLASS_DEF_NL' => array(
        'T_FUNCTION'  => 'CTX_CLASS_METHOD_D_NL',
        'T_STRING'    => 'CTX_CLASS_CONST_D_NL',
        'T_VARIABLE'  => 'CTX_CLASS_VARIABLE_D_NL',
    ),
    'CTX_CLASS_CONST_D' => array(
        'T_STRING' => 'CTX_CLASS_CONST',
    ),
    'CTX_CLASS_CONST_D_NL' => array(
        'T_STRING' => 'CTX_CLASS_CONST_NL',
    ),
    'CTX_CLASS_CONST' => array(
        ';' => -1,
    ),
    'CTX_CLASS_CONST_NL' => array(
        ';' => array(PHPCF_CTX_NOW => 'CTX_CLASS_CONST_NL_END', PHPCF_CTX_NEXT => -1)
    ),
    'CTX_CLASS_VARIABLE_D' => array(
        ';' => -1,
    ) + $fsm_inline_rules,
    'CTX_CLASS_VARIABLE_D_NL' => array(
        ';' => array(PHPCF_CTX_NOW => 'CTX_CLASS_VARIABLE_D_NL_END', PHPCF_CTX_NEXT => -1)
    ) + $fsm_inline_rules,
    'CTX_CLASS_METHOD_D CTX_CLASS_METHOD_D_NL' => array(
        '{' => array(PHPCF_CTX_NOW => 'CTX_CLASS_METHOD_D', PHPCF_CTX_NEXT => 'CTX_CLASS_METHOD'),
    ),
    'CTX_FUNCTION_D' => array(
        '{' => array(PHPCF_CTX_NOW => 'CTX_FUNCTION_D', PHPCF_CTX_NEXT => 'CTX_FUNCTION'),
    ),
    'CTX_CLASS_METHOD' => array('}' => -1) + $fsm_generic_code_block_rules,
    'CTX_FUNCTION' => $fsm_generic_code_block_rules,
);

$fsm_context_rules = array(
    0 => 'CTX_DEFAULT',
    'CTX_DEFAULT' => array(
        'T_OPEN_TAG'           => 'CTX_PHP',
        'T_OPEN_TAG_WITH_ECHO' => 'CTX_PHP',
    ),
    'CTX_PHP' => $fsm_generic_code_rules,
    'CTX_INLINE_BRACE_BEGIN' => array(
        '{'           => 'CTX_INLINE_BRACE',
        PHPCF_KEY_ALL => -1,
    ),
    'CTX_INLINE_BRACE' => array(
        '}' => array(PHPCF_CTX_NOW => 'CTX_INLINE_BRACE', PHPCF_CTX_NEXT => -1),
    ),
    'CTX_GENERIC_BLOCK' => $fsm_generic_code_block_rules,
    'CTX_LONG_FIRST_NL CTX_LONG_EXPR_NL' => array(
        '._NL' => 'CTX_LONG_EXPR_NL',
        'T_DOUBLE_ARROW' => array(PHPCF_CTX_NOW => 'CTX_INLINE_EXPR_NL_END', PHPCF_CTX_NEXT => -1),
        ')'              => array(PHPCF_CTX_NOW => 'CTX_LONG_EXPR_NL_END',   PHPCF_CTX_NEXT => -2),
    ) + $fsm_inline_rules,
    'CTX_INLINE_FIRST_NL CTX_INLINE_EXPR_NL' => array(
        '._NL'           => 'CTX_INLINE_EXPR_NL',
        ')'              => array(PHPCF_CTX_NOW => 'CTX_INLINE_EXPR_NL_END', PHPCF_CTX_NEXT => -1),
        ';'              => array(PHPCF_CTX_NOW => 'CTX_INLINE_EXPR_NL_END', PHPCF_CTX_NEXT => -1),
        'T_DOUBLE_ARROW' => array(PHPCF_CTX_NOW => 'CTX_INLINE_EXPR_NL_END', PHPCF_CTX_NEXT => -1),
    ) + $fsm_inline_rules,
);

$fsm_context_rules += $fsm_context_rules_parenthesis;
$fsm_context_rules += $fsm_context_rules_conditions;
$fsm_context_rules += $fsm_context_rules_loops;
$fsm_context_rules += $fsm_context_rules_switch;
$fsm_context_rules += $fsm_context_rules_array;
$fsm_context_rules += $fsm_context_rules_class;

// print_r($fsm_context_rules);

$options = array(
    'debug'   => 0,
    'quiet'   => 0,
    'summary' => 0,
    'check'   => 0,
    'preview' => 0,
    'apply'   => 0,
    'lines'   => null,
);

foreach ($argv as $k => $v) {
    if ($v === '--debug' || $v === '-d') {
        unset($argv[$k]);
        $options['debug'] = 1;
    }

    if ($v === '--quiet' || $v === '-q') {
        unset($argv[$k]);
        $options['quiet'] = 1;
    }

    if ($v === '--summary' || $v === '-s') {
        unset($argv[$k]);
        $options['summary'] = 1;
    }

    $arg = '--lines=';
    if (substr($v, 0, strlen($arg)) == $arg) {
        $lines_str = substr($argv[$k], strlen($arg));
        $options['lines'] = $lines_str;
        unset($argv[$k]);
    }
}

$argv = array_values($argv);
$argc = count($argv);

if ($argc < 3 || !isset($options[$argv[1]])) {
    if ($argc > 1 && !isset($options[$argv[1]])) {
        fwrite(STDERR, "ERROR: Unknown command: " . $argv[1] . "\n\n");
    }

    fwrite(STDERR, "Usage: $argv[0] [<flags>] <command> <filename> [ ... <filename>]\n\n");
    fwrite(STDERR, "Flags:\n");
    fwrite(STDERR, "  --debug     turn on debug mode\n");
    fwrite(STDERR, "  --quiet     do not print status messages\n");
    fwrite(STDERR, "  --summary   show only number of formatting error messages (if any)\n");
    fwrite(STDERR, "  --lines=... comma-separated list of line numbers to format instead of all file\n\n");
    fwrite(STDERR, "Commands:\n");
    fwrite(STDERR, "  check    just check a file and report about problems with non-zero exit code\n");
    fwrite(STDERR, "  apply    format file, overwrite it and print report\n");
    fwrite(STDERR, "  preview  show diff between original and suggested format and print report\n");
    exit(1);
}

$options[$argv[1]] = 1;

define('PHPCF_DEBUG',   $options['debug']);
define('PHPCF_SNIFF',   true);
define('PHPCF_APPLY',   $options['apply']);
define('PHPCF_PREVIEW', $options['preview']);
define('PHPCF_SUMMARY', $options['summary']);

function phpcf_format_file($filename, $fsm_context_rules, $controls, $options, $lock = false)
{
    if ($lock) $fp = fopen($lock, 'w+');

    $F = new PHPCodeFormatter($filename, $fsm_context_rules, $controls);
    $F->process();
    $lines = null;
    if (isset($options['lines'])) {
        $lines = strlen($options['lines']) ? array_flip(explode(',', $options['lines'])) : array();
    }
    $formatted = $F->exec($lines);

    if ($lock) flock($fp, LOCK_EX);
    if ($F->sniff_exit_code) fwrite(STDERR, "Errors for $filename:\n");

    $F->printSnifferMessages($options['lines']);

    if (PHPCF_PREVIEW) {
        $tmpnam = tempnam('/tmp', 'phpcf');
        if (!$tmpnam) return false;
        if (!file_put_contents($tmpnam, $formatted)) return false;
        system('diff -u ' . escapeshellarg($filename) . ' ' . $tmpnam . ' | cdiff');
        if (file_exists($tmpnam)) unlink($tmpnam);
    }

    if (PHPCF_APPLY) {
        $success = file_put_contents($filename, $formatted);

        if (!$options['quiet'] && $success !== false) {
            echo "$filename formatted successfully\n";
        }

        if ($success === false) {
            fwrite(STDERR, "Could not format $filename\n");
            return false;
        }
    }

    if (PHPCF_SNIFF) {
        if (!$F->sniff_exit_code && !$options['quiet'] && !PHPCF_APPLY) {
            echo "$filename is OK\n";
        }
    }

    if ($options['check'] && $F->sniff_exit_code) return false;

    return true;
}

if (function_exists('pcntl_fork')) {
    $lock = tempnam('/tmp', 'phpcf-lock');
    $children = 0;

    for ($i = 2; $i < $argc; $i++) {
        $filename = $argv[$i];
        $pid = pcntl_fork();
        if ($pid > 0)  $children++;
        if ($pid == 0) exit(phpcf_format_file($filename, $fsm_context_rules, $controls, $options, $lock) ? 0 : 1);
    }

    $fp = fopen($lock, 'w+');

    $children_status = 0;
    $success = true;
    for ($i = 0; $i < $children; $i++) {
        pcntl_wait($children_status);
        if (pcntl_wexitstatus($children_status)) $success = false;
    }

    fclose($fp);
    unlink($lock);
} else {
    $success = true;
    for ($i = 2; $i < $argc; $i++) {
        $filename = $argv[$i];
        if (!phpcf_format_file($filename, $fsm_context_rules, $controls, $options)) $success = false;
    }
}

exit($success ? 0 : 1);
