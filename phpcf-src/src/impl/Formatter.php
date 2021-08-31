<?php
/**
 * PHP implementation for formatter
 * @author Yuriy Nasretdinov <y.nasretdinov@corp.badoo.com>
 */
namespace Phpcf\Impl;

class Formatter implements \Phpcf\IFormatter
{
    const DEBUG_WIDTH_LINE = 10;
    const DEBUG_WIDTH_CODE = 30;
    const DEBUG_WIDTH_TEXT = 30;

    /**
     * @var Fsm
     */
    private $FSM;

    /**
     * @var \Phpcf\Filter\StringAscii filter for non-ascii replacements in string tokens
     */
    private $StringFilter;

    /**
     * Logger for execution
     * @var \Phpcf\ExecStat
     */
    public $Stat;

    /**
     * @var array filter for lines: array(line_num => ...)
     */
    protected $lines = null;

    /**
     * @var int
     */
    protected $current_line = null;

    /**
     * @var array tokens, returned by token_get_all
     */
    protected $tokens = []; // source tokens

    /**
     * @var array parsed tokens
     */
    private $ptokens = [];

    private $exec = [];
    private $exec_ctx = [];

    private $color = false;
    private $indent_level = 0;
    private $indent_sequence = '    ';
    private $indent_width = 4; //length of indent_sequence
    private $max_line_length = 120;
    private $current_pos = 0;
    private $controls = [];
    private $context = false;

    /**
     * @var int number of lines in content
     */
    private $line_count = 0;
    private $last_long_position = -1; // last long comma position for tokenHookComma
    private $sniff_errors = [/* error text => array(lines) */];

    /**
     * Collect messages
     * @var bool
     */
    private $sniff = false;

    private $debug_enabled = false;

    /**
     * Map of token -> callback to execute on it
     * @var array
     */
    protected $token_hook_callbacks = [
        // hook should return all parsed tokens
        T_DOC_COMMENT         => 'tokenHookClassDoc',
        T_START_HEREDOC       => 'tokenHookHeredoc',
        '.'                   => 'tokenHookBinary',
        T_OBJECT_OPERATOR     => 'tokenHookBinary',
        T_BOOLEAN_AND         => 'tokenHookBinary',
        T_BOOLEAN_OR          => 'tokenHookBinary',
        T_LOGICAL_OR          => 'tokenHookBinary',
        T_LOGICAL_AND         => 'tokenHookBinary',
        \T_LOGICAL_XOR         => 'tokenHookBinary',
        '"'                   => 'tokenHookStr',
        '`'                   => 'tokenHookStr',
        '('                   => 'tokenHookOpenBrace',
        '+'                   => 'tokenHookCheckUnary',
        '-'                   => 'tokenHookCheckUnary',
        '&'                   => 'tokenHookCheckUnary',
        ','                   => 'tokenHookComma',
        '?'                   => 'tokenHookTernaryBegin',
        T_STATIC              => 'tokenHookStatic',
        T_STRING              => 'tokenHookTString',
        T_OPEN_TAG            => 'tokenHookOpenTag',
        T_CLOSE_TAG           => 'tokenHookCloseTag',
        T_INC                 => 'tokenHookIncrement',
        T_DEC                 => 'tokenHookIncrement',
        T_WHITESPACE          => 'tokenHookWhiteSpace',
        T_ELSE                => 'tokenHookElse',
        T_ELSEIF              => 'tokenHookElse',
        T_COMMENT             => 'tokenHookComment',
        T_VAR                 => 'tokenHookClassdef',
        T_PUBLIC              => 'tokenHookClassdef',
        T_PROTECTED           => 'tokenHookClassdef',
        T_PRIVATE             => 'tokenHookClassdef',
        T_CONST               => 'tokenHookClassdef',
        T_FINAL               => 'tokenHookClassdef',
        T_FUNCTION            => 'tokenHookFunction',
        '{'                   => 'tokenHookOpenCurly',
        '['                   => 'tokenHookSquareBracket',
        T_ARRAY               => 'tokenHookArray',
        T_VARIABLE            => 'tokenHookVariable',
        T_YIELD_FROM          => 'tokenHookYieldFrom',
        T_INLINE_HTML         => 'tokenHookInlineHTML',
    ];

    /**
     * Map of operation's aliases to real operations
     * @var array
     */
    private static $exec_methods = [
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
        PHPCF_EX_SPACE_IF_NLS          => 'execSpaceIfNls',
    ];

    /**
     * Map of constant values to their human-readable descriptions
     * @var array
     */
    private static $exec_names = [
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
    ];

    /**
     * @param array $ctx_rules
     * @param array $formatting_rules
     * @param \Phpcf\ExecStat $Stat
     */
    public final function __construct(array $ctx_rules, array $formatting_rules, \Phpcf\ExecStat $Stat)
    {
        \Phpcf\Helper::loadExtension('tokenizer');

        $this->initControls($formatting_rules);
        $this->FSM = new Fsm($ctx_rules);
        $this->FSM->setExecStat($Stat);
        $this->Stat = $Stat;
        $this->Stat->setCallback([$this, 'printAllContextMessages']);
        $this->init();
    }

    /**
     * Constructor hook
     */
    protected function init() {}

    /**
     * Collect messages of badly formatted sequences
     * @param bool $flag
     * @return void
     */
    public function setSniffMessages($flag)
    {
        $this->sniff = (bool)$flag;
    }

    /**
     * Issues from last format execution
     * @return string[]
     */
    public function getIssues()
    {
        return $this->Stat->getIssues();
    }

    /**
     * @inheritdoc
     */
    public function setMaxLineLength($width)
    {
        if (!is_int($width)) {
            throw new \InvalidArgumentException("Non-int max width given");
        }
        if ($width <= 0) {
            throw new \InvalidArgumentException("Negative width given");
        }
        $this->max_line_length = $width;
    }

    /**
     * @inheritdoc
     */
    public function setTabSequence($sequence)
    {
        if (!is_string($sequence)) {
            throw new \InvalidArgumentException("Non-string tabulation char sequence given");
        }
        if (empty($sequence)) {
            throw new \InvalidArgumentException("Empty tabulation char sequence given");
        }
        $this->indent_sequence = $sequence;
        $this->indent_width = strlen($sequence);
    }

    /**
     * @inheritdoc
     */
    public function format($content, array $user_lines)
    {
        $this->flush(); // ALL other should be performed below
        $this->line_count = substr_count($content, "\n");
        $this->lines = empty($user_lines) ? null : $user_lines;
        $this->prepareTokens($content);
        $this->process();
        $retval = $this->exec();
        return $retval;
    }

    /**
     * @todo flush FSM
     */
    private function flush()
    {
        $this->last_long_position = -1;
        $this->line_count = 0;
        $this->lines = $this->current_line = null;
        $this->current_pos = 0;
        $this->tokens = $this->ptokens = $this->exec = $this->exec_ctx = $this->sniff_errors = [];
        $this->FSM->flush();
        $this->Stat->flush();
    }

    /**
     * Install's custom filter for rewriting strings
     * @param \Phpcf\Filter\StringAscii $Filter
     */
    public function setStringFilter(\Phpcf\Filter\StringAscii $Filter = null)
    {
        $this->StringFilter = $Filter;
    }

    /**
     * Parse each sniffed message
     */
    public function printAllContextMessages()
    {
        foreach ($this->sniff_errors as $i => $context) {
            $this->printContextMessage($context);
        }
    }

    protected final function sniffMessage($message)
    {
        if (!$this->sniff) {
            return;
        }

        if ($this->shouldIgnoreLine($this->current_line)) {
            return;
        }
        $this->sniff_errors[] = ['line' => $this->current_line, 'descr' => $message];
    }

    /**
     * @param int $line
     * @return bool
     */
    protected function shouldIgnoreLine($line)
    {
        if (isset($this->lines) && !isset($this->lines[$line])) {
            return true;
        }

        return false;
    }

    private function countExprLength($found_first_bracket = false, $max_length)
    {
        $length = 0;
        $depth = $found_first_bracket ? 1 : 0;
        $current_pos = key($this->tokens);

        // not using next() as do not want to move array pointer
        $cnt = count($this->tokens);
        for ($i = $current_pos; $i < $cnt; $i++) {
            $token = $this->tokens[$i];

            if (!$found_first_bracket) {
                if ($token == '(' || $token == '[') {
                    $found_first_bracket = true;
                } else {
                    continue;
                }
            }

            // allow having long array definitions if user has decided that there should be an array in long form
            if ($token[0] === T_WHITESPACE && strpos($token[1], "\n") !== false) {
                return $max_length + 1;
            }

            if (is_array($token)) $length += strlen($token[1]);
            else                  $length += strlen($token);

            if ($length > $max_length) break;

            if ($token === '(' || $token === '[') $depth++;
            if ($token === ')' || $token === ']') $depth--;
            if ($depth <= 0) break;
        }

        return $length;
    }

    /**
     * @param mixed $token
     * @return bool
     */
    protected function shouldIgnoreToken($token)
    {
        return in_array($token, [T_WHITESPACE, T_COMMENT]);
    }

    /**
     * @param mixed $token
     * @return bool
     */
    protected function isAllowedKeywordToken($token)
    {
        return in_array($token, [T_ARRAY, T_FUNCTION, T_LIST, T_INCLUDE, T_DEFAULT]);
    }

    public function setDebugEnabled($flag)
    {
        $this->debug_enabled = (bool)$flag;
        $this->FSM->setIsDebug($this->debug_enabled);
    }

    /**
     * Check, if between opening brace and closing brace only empty tokens are present,
     * changes token type to {_EMPTY
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookOpenCurly($idx_tokens, $i_value)
    {
        return [
            [
                PHPCF_KEY_CODE  => $this->isEmptyBody('}') ? '{_EMPTY' : $i_value,
                PHPCF_KEY_TEXT  => $i_value,
                PHPCF_KEY_LINE  => $this->current_line,
            ]
        ];
    }

    /**
     * Check, if a block is empty.
     * The flag of emptiness, is nothing in block, except spaces
     * @param string $closing_symbol token to be found
     * @return bool flag of being empty
     */
    private function isEmptyBody($closing_symbol)
    {
        $found = false;
        $next_pos = key($this->tokens);

        for ($i = $next_pos; $i < count($this->tokens); $i++) {
            $tok = $this->tokens[$i];
            if ($tok == $closing_symbol) {
                $found = true;
                break;
            }
            // empty symbol found - skip
            else if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) {
                continue;
            }
            // body is not empty
            else {
                break;
            }
        }
        return $found;
    }

    /* text inside HEREDOCs is tokenized in PHP by default, which is not what we need at all */
    protected function tokenHookHeredoc($idx_tokens, $i_value)
    {
        $processed_tokens = [];
        $heredoc_value = '';

        $this->current_line = $i_value[2];
        $processed_tokens[] = [
            PHPCF_KEY_CODE  => $idx_tokens[$i_value[0]],
            PHPCF_KEY_TEXT  => $i_value[1],
            PHPCF_KEY_LINE  => $this->current_line,
        ];

        while ($i_value = current($this->tokens)) {
            next($this->tokens);
            if ($i_value[0] === T_END_HEREDOC) {
                $this->current_line = $i_value[2];
                $processed_tokens[] = [
                    PHPCF_KEY_CODE => 'T_HEREDOC_CONTENTS',
                    PHPCF_KEY_TEXT => $heredoc_value,
                    PHPCF_KEY_LINE => $this->current_line,
                ];
                $heredoc_end_token = [
                    PHPCF_KEY_CODE  => $idx_tokens[$i_value[0]],
                    PHPCF_KEY_TEXT  => $i_value[1],
                    PHPCF_KEY_LINE  => $this->current_line,
                ];
                $i_value = current($this->tokens);
                next($this->tokens);
                if ($i_value && ($i_value[0] === ';')) {
                    $heredoc_end_token[PHPCF_KEY_TEXT] .= ';';
                }
                $processed_tokens[] = $heredoc_end_token;
                break;
            }

            if (is_array($i_value)) {
                $heredoc_value .= $i_value[1];
                $this->current_line = $i_value[2];
            } else {
                $heredoc_value .= $i_value;
            }
        }

        return $processed_tokens;
    }

    /* text inside "strings" (and `strings`) is also tokenized in PHP by default: we do not care about these */
    protected function tokenHookStr($idx_tokens, $i_value)
    {
        $q = $i_value;
        $quote_token = [
            PHPCF_KEY_CODE  => $q,
            PHPCF_KEY_TEXT  => $q,
            PHPCF_KEY_LINE  => $this->current_line,
        ];

        $string_value = '';
        $processed_tokens = [];

        $processed_tokens[] = $quote_token;

        while ($i_value = current($this->tokens)) {
            next($this->tokens);
            if ($i_value === $q) {
                $processed_tokens[] = [
                    PHPCF_KEY_CODE  => 'T_STRING_CONTENTS',
                    PHPCF_KEY_TEXT  => $string_value,
                    PHPCF_KEY_LINE  => $this->current_line,
                ];
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

    /**
     * Hook rewrites token to (_LONG, or to (_EMPTY, if necessary
     */
    protected function tokenHookOpenBrace($idx_tokens, $i_value)
    {
        $length = $this->countExprLength(true, PHPCF_LONG_EXPRESSION_LENGTH);
        $token = '(';
        if ($length >= PHPCF_LONG_EXPRESSION_LENGTH) $token = '(_LONG';

        $can_change_tokens = !$this->shouldIgnoreLine($this->current_line);

        if ($can_change_tokens && $this->isEmptyBody(')')) {
            $token = '(_EMPTY';
        }

        return [
            [
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => '(',
                PHPCF_KEY_LINE  => $this->current_line,
            ]
        ];
    }

    /**
     * Hook rewrites T_YIELD_FROM into T_YIELD and T_FROM
     */
    protected function tokenHookYieldFrom($idx_tokens, $i_value)
    {
        $can_change_tokens = !$this->shouldIgnoreLine($this->current_line);

        if (!$can_change_tokens) {
            return [
                [
                    PHPCF_KEY_CODE => $i_value[0],
                    PHPCF_KEY_TEXT => $i_value[1],
                    PHPCF_KEY_LINE => $this->current_line,
                ]
            ];
        } else {
            return [
                [
                    PHPCF_KEY_CODE => 'T_YIELD',
                    PHPCF_KEY_TEXT => 'yield',
                    PHPCF_KEY_LINE => $this->current_line,
                ],
                [
                    PHPCF_KEY_CODE => 'T_WHITESPACE',
                    PHPCF_KEY_TEXT => ' ',
                    PHPCF_KEY_LINE => $this->current_line,
                ],
                [
                    PHPCF_KEY_CODE => 'T_FROM',
                    PHPCF_KEY_TEXT => 'from',
                    PHPCF_KEY_LINE => $this->current_line,
                ],
            ];
        }
    }

    /**
     * Re-write array to T_ARRAY_HINT
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    private function tokenHookArray($idx_tokens, $i_value)
    {
        $type = 'T_ARRAY';

        $curr_pos = key($this->tokens) - 1;
        for ($i = $curr_pos + 1; $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];
            if (is_array($token) && $this->shouldIgnoreToken($token[0])) {
                continue;
            } else if ('&' === $token
                || (is_array($token)
                    && (T_VARIABLE === $token[0] || (defined('T_ELLIPSIS') && T_ELLIPSIS === $token[0])))) {
                // '&' is reference in 'array &$variable'
                $type = 'T_ARRAY_HINT';
                break;
            } else {
                break;
            }
        }

        return [
            [
                PHPCF_KEY_CODE => $type,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    /**
     * Rewrite variable to function name in closure invocation $foo()
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookVariable($idx_tokens, $i_value)
    {
        $type = 'T_VARIABLE';
        $next_pos = key($this->tokens);

        for ($i = $next_pos; $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];
            if (is_array($token) && $this->shouldIgnoreToken($token[0])) {
                continue;
            } else if ($token === '(') {
                $type = 'T_FUNCTION_NAME';
            }
            break;
        }

        return [
            [
                PHPCF_KEY_CODE => $type,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    // rewrite [ to T_ARRAY_SHORT(_ML), if necessary
    protected function tokenHookSquareBracket($idx_tokens, $i_value)
    {
        $type = '[';
        // if previous token is one of this, than it is not an array
        static $non_array_tokens = [
            ']'  => 1, // $a[1][2]
            ')'  => 1, // function array dereferencing
            T_VARIABLE => 1, // $a['key'] is not short array
            T_CONSTANT_ENCAPSED_STRING => 1, // string dereferencing
        ];

        $current_pos = key($this->tokens) - 1;
        for ($i = $current_pos - 1; $i > 0; $i--) {
            $token = $this->tokens[$i];
            if (is_array($token) && $this->shouldIgnoreToken($token[0])) {
                continue;
            } else if (!is_array($token) && isset($non_array_tokens[$token])) {
                break;
            } else if (is_array($token) && isset($non_array_tokens[$token[0]])) {
                break;
            } else {
                $type = 'T_ARRAY_SHORT';
                break;
            }
        }

        // multiline support
        if ($type === 'T_ARRAY_SHORT') {
            $length = $this->countExprLength(true, PHPCF_LONG_EXPRESSION_LENGTH);
            if ($length >= PHPCF_LONG_EXPRESSION_LENGTH) {
                $type .= '_ML'; // multiline array
            }
        }

        return [
            [
                PHPCF_KEY_CODE => $type,
                PHPCF_KEY_TEXT => '[',
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    protected function tokenHookCheckUnary($idx_tokens, $i_value)
    {
        static $left_expression_symbols = [']', ')', T_LNUMBER, T_DNUMBER, T_VARIABLE, T_STRING,];

        $is_unary = false;
        $current_pos = key($this->tokens) - 1; // token position is already pointing to next token

        // token is unary if there is no expression on the left
        for ($i = $current_pos - 1; $i > 0; $i--) {
            $tok = $this->tokens[$i];
            if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) continue;
            if (is_array($tok)) $tok = $tok[0];

            if (!in_array($tok, $left_expression_symbols, true)) {
                $is_unary = true;
            }
            break;
        }

        $token = $i_value . ($is_unary ? '_UNARY' : '');

        return [
            [
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => $i_value,
                PHPCF_KEY_LINE  => $this->current_line,
            ]
        ];
    }

    // interpret "static" in "static::HELLO" as normal text (T_STRING) instead of keyword (T_STATIC)
    protected function tokenHookStatic($idx_tokens, $i_value)
    {
        static $right_normal_context_symbols = [
            T_VARIABLE, // static $var; protected static $var;
            T_PROTECTED, T_PUBLIC, T_PRIVATE, T_FINAL, T_VAR, T_ABSTRACT, // static protected $var;
            T_FUNCTION, // static function() { ... }
        ];
        static $bad_right_tokens = [T_PUBLIC, T_PRIVATE, T_PROTECTED];

        $is_normal_context = false;
        $next_pos = key($this->tokens);
        $this->current_line = $i_value[2];

        for ($i = $next_pos; $i < count($this->tokens); $i++) {
            $tok = $this->tokens[$i];
            if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) continue;
            if (in_array($tok[0], $right_normal_context_symbols, true)) $is_normal_context = true;
            break;
        }

        if ($is_normal_context) return $this->tokenHookClassdef($idx_tokens, $i_value);

        return [
            [
                PHPCF_KEY_CODE => 'T_STRING',
                PHPCF_KEY_TEXT => 'static',
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    /**
     * Check if there is class definition after doc block
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookClassDoc($idx_tokens, $i_value)
    {
        $token = $idx_tokens[$i_value[0]];
        $next_pos = key($this->tokens);
        $this->current_line = $i_value[2];

        if (isset($this->tokens[$next_pos]) && $this->tokens[$next_pos][0] === T_WHITESPACE) {
            $possible_class_pos = $next_pos + 1;
        } else {
            $possible_class_pos = $next_pos;
        }
        if (isset($this->tokens[$possible_class_pos])) {
            switch ($this->tokens[$possible_class_pos][0]) {
                case T_CLASS:
                    $token .= "_B4_CLASS";
                    break;

                case T_TRAIT:
                    $token .= "_B4_TRAIT";
                    break;

                case T_INTERFACE:
                    $token .= "_B4_INTERFACE";
                    break;
            }
        }
        return [
            [
                PHPCF_KEY_CODE => $token ,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    /**
     * check for newline after "const", "private", etc and check if it is something like this:
     * const
     *     CONST1 = "Something",
     *     CONST2 = "Another thing";
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookClassdef($idx_tokens, $i_value)
    {
        $token = $idx_tokens[$i_value[0]];
        $this->current_line = $i_value[2];

        $next_pos = key($this->tokens);
        $is_property_def = false;
        $is_newline = false; // whether or not definition is on a new line

        for ($i = $next_pos; $i < count($this->tokens); $i++) {
            $tok = $this->tokens[$i];

            // look for whitespace, comment, variable or constant name after, e.g. "private"
            // if nothing was found, then it is end of property def (or real property def did not begin)
            if ($tok[0] === T_VARIABLE || $tok[0] === T_STRING || $tok[0] === T_NS_SEPARATOR) {
                $is_property_def = true;
            } else if (($i_value[0] === T_CONST) && $this->isAllowedKeywordToken($tok[0])) {
                // PHP7 allow to use some keywords as constant name
                $is_property_def = true;
            } else if ($tok[0] !== T_COMMENT && $tok[0] !== T_WHITESPACE) {
                break;
            }

            if (strpos($tok[1], "\n") !== false) {
                $is_newline = true;
            }
        }

        if ($is_property_def && $is_newline) $token .= '_NL';

        return [
            [
                PHPCF_KEY_CODE => $token,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    protected function appendWhiteSpace(&$return_tokens, $text = "\n")
    {
        $next_token = current($this->tokens);
        if ($next_token[0] !== T_WHITESPACE) {
            $return_tokens[] = [
                PHPCF_KEY_CODE  => 'T_WHITESPACE',
                PHPCF_KEY_TEXT  => $text,
                PHPCF_KEY_LINE  => $this->current_line,
            ];
        } else {
            $el = &$this->tokens[key($this->tokens)];
            $el[1] = $text . $el[1];
            $el[2] -= substr_count($text, "\n");
        }
    }

    /**
     * only long "<?php" open tags are allowed by our rules
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookOpenTag($idx_tokens, $i_value)
    {
        $this->current_line = $i_value[2];

        $can_change_tokens = !$this->shouldIgnoreLine($this->current_line);
        if ($can_change_tokens) {
            $open_tag = "<?php";
            if (rtrim($i_value[1]) !== $open_tag) $this->sniffMessage('Only "<?php" is allowed as open tag');
        } else {
            $open_tag = rtrim($i_value[1]);
        }

        $whitespace = substr($i_value[1], strlen($open_tag));

        $ret = [
            [
                PHPCF_KEY_CODE => 'T_OPEN_TAG',
                PHPCF_KEY_TEXT => $open_tag,
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];

        $this->appendWhiteSpace($ret, $whitespace);
        return $ret;
    }

    /**
     * Check for closing tag at the end of file
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookCloseTag($idx_tokens, $i_value)
    {
        $this->current_line = $i_value[2];
        $can_change_tokens = !$this->shouldIgnoreLine($this->current_line);

        if ($can_change_tokens) {
            $is_eof = true;
            $can_delete = true;
            $toknum = count($this->tokens);
            $next_pos = key($this->tokens);

            if ($next_pos) {
                for ($i = $next_pos; $i < $toknum; $i++) {
                    $tok = $this->tokens[$i];
                    if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) continue;
                    if ($tok[0] === T_INLINE_HTML && trim($tok[1]) == '') continue;
                    $is_eof = false;
                    break;
                }
                $curr_pos = $next_pos - 1;
            } else {
                $curr_pos = $toknum - 1;
            }

            if ($is_eof) {
                $prev_pos = $curr_pos - 1;
                if ($this->tokens[$prev_pos][0] === T_WHITESPACE) $prev_pos--;
                if ($this->tokens[$prev_pos] !== ';' && $this->tokens[$prev_pos] !== '}') {
                    $this->sniffMessage('Expected either ";" or "}" before closing tag for it to be safely deleted');
                    $can_delete = false;
                }
            }

            if ($is_eof && $can_delete) {
                $this->sniffMessage('No close tag allowed at the end of file');
                return [];
            }
        }

        return [
            [
                PHPCF_KEY_CODE => $idx_tokens[$i_value[0]],
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    /**
     * T_INC => T_INC_LEFT || T_INC_RIGHT ( T_INC_LEFT in case "++$a", T_INC_RIGHT in case "$a++")
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookIncrement($idx_tokens, $i_value)
    {
        $next_pos = key($this->tokens);
        $is_left = false;

        if ($next_pos) {
            for ($i = $next_pos; $i < count($this->tokens); $i++) {
                $tok = $this->tokens[$i];
                if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) continue;
                // in situations like '++ $a' there would be variable on the right, so the token is positioned left
                if ($tok[0] === T_VARIABLE) $is_left = true;
                break;
            }
        }

        $token = $idx_tokens[$i_value[0]] . ($is_left ? '_LEFT' : '_RIGHT');
        $this->current_line = $i_value[2];

        return [
            [
                PHPCF_KEY_CODE => $token,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    /**
     * @param $line_pos
     * @param $line_length
     * @param $whitespace_length
     * @return bool
     */
    private function isLineAligned($line_pos, $line_length, $whitespace_length)
    {
        $other_line_length = 0;

        for ($i = $line_pos; $i < count($this->tokens); $i++) {
            $tok = $this->tokens[$i];
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
            } else if ($other_line_length == $line_length && $this->tokens[$i - 1][0] === T_WHITESPACE) {
                // aligned by tokens!
                return true;
            } else if ($other_line_length > $line_length) {
                // no chance
                break;
            }
        }

        return false;
    }

    /**
     * @param $prev_line_pos
     * @param $line_length
     * @param $whitespace_length
     * @return bool
     */
    private function isPrevLineAligned($prev_line_pos, $line_length, $whitespace_length)
    {
        for ($i = $prev_line_pos; $i >= 0; $i--) { // find beginning of line
            $tok = $this->tokens[$i];
            if (!is_array($tok)) continue;
            $is_str = $tok[0] === T_CONSTANT_ENCAPSED_STRING || $tok[0] === T_ENCAPSED_AND_WHITESPACE || $tok[0] === T_COMMENT;
            if (!$is_str && strpos($tok[1], "\n") !== false) break;
        }

        if ($i < $prev_line_pos) {
            return $this->isLineAligned($i + 1, $line_length, $whitespace_length);
        }

        return false;
    }

    /**
     * @param $next_line_pos
     * @param $line_length
     * @param $whitespace_length
     * @return bool
     */
    private function isNextLineAligned($next_line_pos, $line_length, $whitespace_length)
    {
        for ($i = $next_line_pos; $i < count($this->tokens); $i++) { // find beginning of line
            $tok = $this->tokens[$i];
            if (!is_array($tok)) continue;
            $is_str = $tok[0] === T_CONSTANT_ENCAPSED_STRING || $tok[0] === T_ENCAPSED_AND_WHITESPACE;
            if (!$is_str && strpos($tok[1], "\n") !== false) break;
        }

        if ($i > $next_line_pos) {
            while ($i < count($this->tokens)) {
                $token = $this->tokens[$i];
                if (is_array($token) && $token[0] === T_WHITESPACE) {
                    break;
                }
                ++$i;
            }
            return $this->isLineAligned($i + 1, $line_length, $whitespace_length);
        }

        return false;
    }

    /**
     * find aligned expressions
     * @param $i_value
     * @return bool
     */
    private function tokensIsWhiteSpaceAligned($i_value)
    {
        $next_pos = key($this->tokens);

        // either it's EOF or whitespace has tabs/newlines or it has length less than 2
        if (!$next_pos || !preg_match('/^  +$/s', $i_value[1])) {
            return false;
        }

        // search for beginning of line and count length of line before this whitespace
        $whitespace_length = $line_length = 0;
        for ($i = $next_pos; $i >= 0; $i--) {
            $tok = $this->tokens[$i];
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
        if ($i > 0)       $is_aligned = $this->isPrevLineAligned($i - 1, $line_length, $whitespace_length);
        if (!$is_aligned) $is_aligned = $this->isNextLineAligned($next_pos, $line_length, $whitespace_length);

        return $is_aligned;
    }

    /**
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookWhiteSpace($idx_tokens, $i_value)
    {
        // fix for bug with wrong line numbers of tokens like "{" which do not have line number assotiated with them
        $this->current_line = $i_value[2] + substr_count($i_value[1], "\n");
        if ($this->tokensIsWhiteSpaceAligned($i_value)) {
            $token = 'T_WHITESPACE_ALIGNED';
        } else {
            $token = 'T_WHITESPACE';
        }

        return [
            [
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => $i_value[1],
                PHPCF_KEY_LINE  => $this->current_line,
            ]
        ];
    }

    /**
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookElse($idx_tokens, $i_value)
    {
        $token = $idx_tokens[$i_value[0]];
        $this->current_line = $i_value[2];
        $next_pos = key($this->tokens);
        $has_block_before = false;

        for ($i = $next_pos - 2; $i > 0; $i--) {
            $tok = $this->tokens[$i];
            if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) continue;
            if ($tok === '}') $has_block_before = true;
            break;
        }

        if (!$has_block_before) $token .= '_INLINE';

        return [
            [
                PHPCF_KEY_CODE => $token,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    /**
     * "// comment\n" is split into 2 tokens "// comment" and "\n", and "\n" is prepended to previous whitespace token
     * if present. This split allows not to take easy cases with single-line comments into account
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookComment($idx_tokens, $i_value)
    {
        $token = $idx_tokens[$i_value[0]];
        $this->current_line = $i_value[2];
        $can_change_tokens = !$this->shouldIgnoreLine($this->current_line);
        if ($can_change_tokens) {
            if ($i_value[1][0] == '#') {
                $this->sniffMessage("Comments starting with '#' are not allowed");
                $i_value[1] = '//' . substr($i_value[1], 1);
            }
        }

        if ($i_value[1][0] === '#' || substr($i_value[1], 0, 2) === '//') {
            // if previous token is whitespace with line feed, then it means that this comment takes whole line
            $prev_pos = key($this->tokens) - 2; // otherwise it is appended to some expression like this one
            if ($prev_pos > 1) {
                $prev_tok = $this->tokens[$prev_pos];
                if ($prev_tok[0] === T_WHITESPACE && strpos($prev_tok[1], "\n") !== false) {
                    $token = 'T_SINGLE_LINE_COMMENT_ALONE';
                } else {
                    $token = 'T_SINGLE_LINE_COMMENT';
                }
            } else {
                $token = 'T_SINGLE_LINE_COMMENT';
            }

            $i_value[1] = rtrim($i_value[1], "\n");
        }

        $ret = [
            [
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => $i_value[1],
                PHPCF_KEY_LINE  => $this->current_line,
            ],
        ];

        if ($token == 'T_COMMENT') return $ret; // do not touch multiline comments

        $this->appendWhiteSpace($ret);

        return $ret;
    }

    /**
     * Rename T_STRING to T_FUNCTION_NAME if it is a function/method name:
     * Valid cases:  call_something()   $this->call_something()   function call_something()
     * Invalid cases:  self::MY_CONST   do_something(MY_CONST)  etc
     * If string filter is present => apply it
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookTString($idx_tokens, $i_value)
    {
        $token = $idx_tokens[$i_value[0]];
        $this->current_line = $i_value[2];
        $value = $i_value[1];

        if ($value === 'fn') {
            return [
                [
                    PHPCF_KEY_CODE  => 'T_FN',
                    PHPCF_KEY_TEXT  => $value,
                    PHPCF_KEY_LINE  => $this->current_line,
                ],
            ];
        }

        for ($i = key($this->tokens); $i < count($this->tokens); $i++) {
            $tok = $this->tokens[$i];
            if (is_array($tok) && $this->shouldIgnoreToken($tok[0])) continue;
            if ($tok === '(') $token = 'T_FUNCTION_NAME';
            break;
        }

        if ($this->StringFilter) {
            $filtered = $this->StringFilter->filter($value);
            $value = $filtered[0];

            if (!empty($filtered[1])) {
                $this->sniffMessage($filtered[1]);
            }
        }

        return [
            [
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => $value,
                PHPCF_KEY_LINE  => $this->current_line,
            ],
        ];
    }

    /**
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookBinary($idx_tokens, $i_value)
    {
        $next_pos = key($this->tokens);
        $prev_pos = key($this->tokens) - 2;
        $prev_prev_pos = $prev_pos - 1;
        $token = is_array($i_value) ? $idx_tokens[$i_value[0]] : $i_value;

        if ($this->tokens[$prev_prev_pos][0] === \T_FUNCTION) {
            // allow methods names like and/or
            $token = 'T_FUNCTION_NAME';
        } else if ($this->tokens[$prev_prev_pos][0] === \T_STRING && in_array($this->tokens[$prev_pos][0], [\T_DOUBLE_COLON, \T_OBJECT_OPERATOR], true)) {
            // allow static calls for methods with name and, or, etc
            $token = 'T_FUNCTION_NAME';
        } else if ($this->tokens[$next_pos][0] === T_WHITESPACE && strpos($this->tokens[$next_pos][1], "\n") !== false) {
            $token .= '_NL';
        } else if ($this->tokens[$prev_pos][0] === T_WHITESPACE && strpos($this->tokens[$prev_pos][1], "\n") !== false) {
            $token .= '_NL';
        }

        return [
            [
                PHPCF_KEY_CODE  => $token,
                PHPCF_KEY_TEXT  => is_array($i_value) ? $i_value[1] : $i_value,
                PHPCF_KEY_LINE  => $this->current_line,
            ],
        ];
    }

    /**
     * @param $max_length
     * @param bool $remember_positions
     * @return bool
     */
    private function isLongComma($max_length, $remember_positions = false)
    {
        $curr_pos = key($this->tokens) - 1;
        $len = 0;
        $depth = 0;
        // getting first right non-whitespace token length (only if it is not )
        for ($i = $curr_pos + 1; $i < count($this->tokens); $i++) {
            $tok = $this->tokens[$i];
            if (is_array($tok)) {
                if ($tok[0] === T_WHITESPACE) {
                    // if there already is a new line after comma, we will keep it and remember its position
                    if (strpos($tok[1], "\n") !== false) {
                        if ($remember_positions) $this->last_long_position = $curr_pos;
                        return true;
                    }
                    continue;
                }

                $len += strlen($tok[1]);
            } else {
                if ($tok == ')' || $tok == ']') break;
                $len += strlen($tok);
            }

            break;
        }

        // going backwards to see if there already is more than 120 symbols in line
        for ($i = $curr_pos; $i > 0; $i--) {
            // we can remember that which comma was long and
            // count the next long comma only starting from the previous long one
            if ($i == $this->last_long_position) break;
            $tok = $this->tokens[$i];

            // we should stop when line beginning is reached or in another special case described below
            if (is_array($tok)) {
                if (strpos($tok[1], "\n") !== false) break;
                if ($tok[0] === T_WHITESPACE) continue;

                $len += strlen($tok[1]);
            } else {
                if ($tok == ',')      $len += 2;
                else if ($tok == '(') $depth--;
                else if ($tok == ')') $depth++;
                else if ($tok == '[') $depth++;
                else if ($tok == ']') $depth--;
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
        if ($remember_positions && $is_long) $this->last_long_position = $curr_pos;
        return $is_long;
    }

    /**
     * Hook to rewrite comma into new line, if expression is too long
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookComma($idx_tokens, $i_value)
    {
        $token = ',';
        if ($this->isLongComma($this->max_line_length, true)) {
            $token .= '_LONG';
        }

        return [
            [
                PHPCF_KEY_CODE => $token,
                PHPCF_KEY_TEXT => ',',
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    /**
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    protected function tokenHookFunction($idx_tokens, $i_value)
    {
        $this->current_line = $i_value[2];
        $next_pos = key($this->tokens);
        $toknum = count($this->tokens);

        $is_anonymous = true;
        $found_arguments_begin = false;
        $found_open_bracket = false;

        $length = 0;
        $depth = 0;

        for ($i = $next_pos; $i < $toknum; $i++) {
            $tok = $this->tokens[$i];

            if (!$found_open_bracket) {
                if (is_array($tok)) {
                    $length += strlen($tok[1]);
                    if ($this->shouldIgnoreToken($tok)) continue;
                    // function doSomethingGood(
                    //          ^ T_STRING     ^ arguments begin here
                    if (\in_array($tok[0], [T_STRING, \T_LOGICAL_AND, \T_LOGICAL_OR, \T_LOGICAL_XOR], true) && !$found_arguments_begin) {
                        $is_anonymous = false;
                    } else if (!$found_arguments_begin && $this->isAllowedKeywordToken($tok[0])) {
                        // PHP7 allow to use some keywords as function name
                        $is_anonymous = false;
                    }
                } else if (!$found_open_bracket) {
                    $length += strlen($tok);
                    if ($tok === '(') $found_arguments_begin = true;
                    if ($tok === '{') {
                        $found_open_bracket = true;
                        $depth = 1;
                    }
                }
            } else {
                if (is_array($tok)) {
                    $length += strlen($tok[1]);
                    // if someone wants to put function declaration on several lines, so be it
                    if (strpos($tok[1], "\n") !== false) {
                        $length = PHPCF_LONG_EXPRESSION_LENGTH;
                        break;
                    }
                } else {
                    $length += strlen($tok);
                    if ($tok === '{') $depth++;
                    if ($tok === '}') $depth--;

                    if ($depth <= 0) break;
                }
            }
        }

        $token = 'T_FUNCTION';
        if ($is_anonymous) {
            if ($length >= PHPCF_LONG_EXPRESSION_LENGTH) {
                $token = 'T_ANONFUNC_LONG';
            } else {
                $token = 'T_ANONFUNC';
            }
        }

        return [
            [
                PHPCF_KEY_CODE => $token,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    /* Glue "?:" into a single token */
    private function tokenHookTernaryBegin($idx_tokens, $i_value)
    {
        if (current($this->tokens) === ':') {
            next($this->tokens);
            return [
                [
                    PHPCF_KEY_CODE => '?:',
                    PHPCF_KEY_TEXT => '?:',
                    PHPCF_KEY_LINE => $this->current_line,
                ]
            ];
        }

        // going backwards to find ( or ,
        $curr_pos = key($this->tokens) - 2;
        $is_nullable_type_mark = false;
        for ($i = $curr_pos; $i > 0; $i--) {
            $tok = $this->tokens[$i];

            if (is_array($tok)) {
                $tok = $tok[0];
            }

            if ($this->shouldIgnoreToken($tok)) {
                continue;
            }

            if (in_array($tok, ['(', ',', ':'])) {
                $is_nullable_type_mark = true;
            }

            break;
        }

        if ($is_nullable_type_mark) {
            return [
                [
                    PHPCF_KEY_CODE => '?_NULLABLE_MARK',
                    PHPCF_KEY_TEXT => '?',
                    PHPCF_KEY_LINE => $this->current_line,
                ]
            ];
        }

        return [
            [
                PHPCF_KEY_CODE => '?',
                PHPCF_KEY_TEXT => '?',
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    /**
     * Rewrite whitespace-only HTML to T_WHITESPACE so that we can apply formatting rules to it
     *
     * Do not do this for internal blocks
     *
     * @param $idx_tokens
     * @param $i_value
     * @return array
     */
    private function tokenHookInlineHTML($idx_tokens, $i_value)
    {
        $this->current_line = $i_value[2];
        $token = is_array($i_value) ? $idx_tokens[$i_value[0]] : $i_value;

        $curr_pos = key($this->tokens) - 1;

        $is_internal = false;

        // going backwards to find T_CLOSE_TAG
        for ($i = $curr_pos; $i > 0; $i--) {
            $tok = $this->tokens[$i];

            if ($tok[0] === T_CLOSE_TAG) {
                $is_internal = true;
                break;
            }
        }

        if (!$is_internal) {
            if (preg_match('/^\\s+$/s', $i_value[1])) {
                $token = 'T_WHITESPACE';
            }
        }

        return [
            [
                PHPCF_KEY_CODE => $token,
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            ]
        ];
    }

    /**
     * Parse $body into tokens, rewrite them, etc.
     * @param string $body
     */
    private function prepareTokens($body)
    {
        $this->tokens = token_get_all($body);
        $this->ptokens = [];

        $list_tokens = preg_grep('/^T_/', array_keys(get_defined_constants()));
        $idx_tokens = [];
        foreach ($list_tokens as $i_token) {
            $idx_tokens[constant($i_token)] = $i_token;
        }

        $in_nowdoc = $in_heredoc = $in_string = false;
        $heredoc_value = $string_value = '';
        // iterate array manually so that we can read several tokens in hooks and auto-advance position
        reset($this->tokens);
        while ($i_value = current($this->tokens)) {
            next($this->tokens);
            $tok = is_array($i_value) ? $i_value[0] : $i_value;
            if (isset($this->token_hook_callbacks[$tok])) {
                $method_name = $this->token_hook_callbacks[$tok];
                $result = $this->$method_name($idx_tokens, $i_value);
                foreach ($result as $parsed_token) {
                    $this->ptokens[] = $parsed_token;
                }
                continue;
            }

            if (is_array($i_value)) {
                $this->current_line = $i_value[2];
                $this->ptokens[] = [
                    PHPCF_KEY_CODE => $idx_tokens[$i_value[0]],
                    PHPCF_KEY_TEXT => $i_value[1],
                    PHPCF_KEY_LINE => $this->current_line,
                ];
                $this->current_line += substr_count($i_value[1], "\n");
            } else {
                $this->ptokens[] = [
                    PHPCF_KEY_CODE => $i_value,
                    PHPCF_KEY_TEXT => $i_value,
                    PHPCF_KEY_LINE => $this->current_line,
                ];
            }
        }
    }

    /**
     * Get name of executor by code
     * @param $byte
     * @return string
     */
    private function getHumanReadableExecName($byte)
    {
        if (empty(self::$exec_names[$byte])) {
            return 'UNKNOWN - "' . $byte . '"';
        }

        return self::$exec_names[$byte];
    }

    private function getHumanReadableExecSequence($sequence)
    {
        $parts = [];

        foreach ($sequence as $v) {
            if (is_int($v)) {
                $parts[] = $this->getHumanReadableExecName($v);
            } else {
                $parts[] = $this->humanWhiteSpace($v);
            }
        }

        return implode(', ', $parts);
    }

    private function exec()
    {
        $exec_ctx = [];
        $exec_sequence = [];
        $out = '';
        foreach ($this->exec as $i => $i_exec) {
            $ctx = $this->exec_ctx[$i];
            $line = $this->ptokens[$ctx['current_pos']][PHPCF_KEY_LINE];

            if (is_string($i_exec)) {
                if ($ctx['whitespace']) {
                    $exec_sequence[] = $i_exec;
                    $exec_ctx[] = $ctx;
                } else {
                    if (!empty($exec_sequence)) {
                        $out .= $this->execSequence($exec_sequence, $exec_ctx, $line);
                        $exec_sequence = [];
                        $exec_ctx = [];
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
            $out .= $this->execSequence($exec_sequence, $exec_ctx, $line);
        }

        return $out;
    }

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

    private function execSpaceIfNls($in)
    {
        if (preg_match('/\n+/', $in)) {
            return ' ';
        }
        return $in;
    }

    private function execSequence($sequence, $exec_ctx, $line)
    {
        $c = [];
        $in = '';
        $context = [
            'descr'       => 'correct indentation level',
            'current_pos' => $exec_ctx[0] ? $exec_ctx[0]['current_pos'] : null,
        ];

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
        if (isset($c[PHPCF_EX_DO_NOT_TOUCH_ANYTHING])) {
            return $in;
        }

        if (count($c)) {
            // the executors with less value have higher precedence
            $min_key = min(array_keys($c));

            // account for ignoring lines
            if ($this->shouldIgnoreLine($c[$min_key]['line'])) {
                return $in;
            }

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

    private function humanWhiteSpace($str, $color = false)
    {
        static $whitespace_map, $colored_whitespace_map;
        if (!isset($whitespace_map)) {
            $whitespace_map = ["\n" => '\n', "\r" => '\r', "\t" => '\t', " " => ' '];
            $colored_whitespace_map = ["\n" => '\n', "\r" => '\r', "\t" => '\t', " " => '·'];
            foreach ($colored_whitespace_map as &$v) {
                $v = "\033[38;5;246m" . $v . "\033[0m";
            }
            unset($v);
        }

        return strtr($str, $this->color && $color ? $colored_whitespace_map : $whitespace_map);
    }

    protected final function sniff($in, $out, $context, $sequence)
    {
        if (!$this->sniff) {
            return;
        }

        $context['in'] = $in;
        $context['out'] = $out;
        $this->sniff_errors[] = $context;
    }

    private function printContextMessage($context)
    {
        $padding = "    ";
        if (!isset($context['current_pos'])) { // error from hook
            $this->Stat->addIssue($padding . "$context[descr] on line $context[line]");
            return;
        }

        $token_pos = $context['current_pos'];
        $in = $context['in'];
        $out = $context['out'];
        if (strlen($in) && isset($context['from'])) {
            if ($context['from'] == PHPCF_KEY_LEFT) {
                $token_pos--;
            } else {
                $token_pos++;
            }
        }

        if ($token_pos < 0) $token_pos = 0;
        else if ($token_pos >= count($this->ptokens)) $token_pos = count($this->ptokens) - 1;

        $token = $this->ptokens[$token_pos];
        $token_line = $token[PHPCF_KEY_LINE];
        // compensate for hack for whitespace lines
        if ($token[PHPCF_KEY_CODE] == 'T_WHITESPACE') $token_line -= substr_count($token[PHPCF_KEY_TEXT], "\n");

        if (!substr_count($out, "\n") || substr_count($out, "\n") != substr_count($in, "\n")) {
            $col = 1;
            for ($i = $token_pos - 1; $i >= 0; $i--) {
                $tok = $this->ptokens[$i];
                $contents = $tok[PHPCF_KEY_TEXT];
                if (strpos($contents, "\n") !== false) {
                    $parts = explode("\n", $contents);
                    $col += strlen(end($parts));
                    break;
                } else {
                    $col += strlen($contents);
                }
            }
            if (!strlen($in) && isset($context['from']) && $context['from'] == PHPCF_KEY_RIGHT) {
                $col += strlen($token[PHPCF_KEY_TEXT]);
            }

            if (substr_count($out, "\n")) {
                $out_parts = explode("\n", $out);
                $indent_msg = " and " . $this->getIndentDescription($out_parts[1]);
            } else {
                $indent_msg = "";
            }

            $reason = "Expected " . lcfirst($context['descr']) . $indent_msg . " on line $token_line column $col";
            $this->Stat->addIssue($padding . $reason);
        } else {
            $out_lines = explode("\n", $out);
            $in_lines = explode("\n", $in);

            foreach ($out_lines as $k => $v) {
                if ($v === $in_lines[$k]) {
                    continue;
                } else if ($k == 0) { // first element is not indent, but spaces at the end of line
                    $ln = $token_line + $k;
                    $reason = "Expected '$v', got '$in_lines[$k]' at the end of line $ln";
                    $this->Stat->addIssue($padding . $reason);
                } else {
                    $ln = $token_line + $k;
                    $col = strlen($in_lines[$k]) + 1;
                    $reason = "Expected " . $this->getIndentDescription($v) . ", got "
                        . $this->getIndentDescription($in_lines[$k]);
                    $this->Stat->addIssue($padding . $reason . " on line $ln column $col");
                }
            }
        }
    }

    /**
     * @param string $str
     * @return string
     */
    private function getIndentDescription($str)
    {
        if (strlen(str_replace(' ', '', $str))) {
            return "'$str'";
        }
        $ln = strlen($str);
        if (!$ln) {
            return "no indent";
        } else if ($ln % $this->indent_width == 0) {
            return "indent level " . ($ln / $this->indent_width);
        }
        return "indent of $ln spaces";
    }

    /**
     * Performs switch to next parsed token
     * @return bool
     */
    private function nextParsedToken()
    {
        if ($this->current_pos + 1 >= count($this->ptokens)) {
            return false;
        }

        $this->current_pos++;
        $this->setupContext();
        return $this->ptokens[$this->current_pos];
    }

    private function setupContext()
    {
        $this->FSM->transit($this->ptokens[$this->current_pos][PHPCF_KEY_CODE]);
        $this->context = $this->FSM->getState();
    }

    /**
     * Parsed given formatting rules into multidimensional array
     * @param array $controls
     */
    private function initControls(array $controls)
    {
        foreach ($controls as $key => $data) {
            $list_keys = explode(' ', $key);
            if (empty($list_keys)) {
                $list_keys = [$key];
            }

            foreach ($list_keys as $i_key) {
                foreach ($data as $j_key => $j_data) {
                    $j_list_keys = explode(' ', $j_key);
                    if (empty($j_list_keys)) {
                        $j_list_keys = [$j_key];
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
        return [
            'current_pos' => $this->current_pos,
            'line'        => $this->ptokens[$this->current_pos][PHPCF_KEY_LINE],
            'whitespace'  => $this->ptokens[$this->current_pos][PHPCF_KEY_CODE] === 'T_WHITESPACE',
        ];
    }

    private function process()
    {
        if (!$this->ptokens) return; // empty file

        $this->setupContext();
        do {
            $cur_token = $this->ptokens[$this->current_pos];
            $i_line = $cur_token[PHPCF_KEY_LINE];
            $i_code = $cur_token[PHPCF_KEY_CODE];
            $i_text = $cur_token[PHPCF_KEY_TEXT];

            if ($this->debug_enabled) {
                $sp = '     ';
                $debug_line = sprintf('%' . self::DEBUG_WIDTH_LINE . 's', $i_line);
                $debug_code = sprintf('%' . self::DEBUG_WIDTH_CODE . 's', $i_code);
                $debug_text = $this->humanWhiteSpace($i_text, true);
                $whitespaces = str_repeat(' ', max(0, self::DEBUG_WIDTH_TEXT - strlen($debug_text)));

                $msg  = $debug_line . $sp . $debug_code . $sp . $debug_text . $whitespaces . $sp . $this->FSM->getStackPath();

                fwrite(STDERR, $msg . PHP_EOL);
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
        } while ($this->nextParsedToken());

        $this->FSM->finalize();
        $final_state = $this->FSM->getStackPath();

        $valid_states = [
            'CTX_PHP'           => true,
            'CTX_DEFAULT'       => true,
            'CTX_HALT_COMPILER' => true,
        ];

        if (!isset($valid_states[$final_state])) {
            throw new \RuntimeException("Internal formatter error: final state must be CTX_PHP, CTX_DEFAULT or CTX_HALT_COMPILER, got '$final_state'");
        }
    }

    /**
     * @param $pos
     * @return bool
     */
    private function isWhiteSpaceAligned($pos)
    {
        if (!isset($this->ptokens[$pos][PHPCF_KEY_CODE])) {
            return false;
        }
        return $this->ptokens[$pos][PHPCF_KEY_CODE] == 'T_WHITESPACE_ALIGNED';
    }

    /**
     * @param array $controls
     * @param $context
     */
    private function processControls(array $controls, $context)
    {
        $i = count($this->exec);
        if (!empty($controls[PHPCF_KEY_LEFT]) && !$this->isWhiteSpaceAligned($this->current_pos - 1)) {
            $c = $controls[PHPCF_KEY_LEFT];
            $this->exec_ctx[$i] = $context + [
                'descr' => $controls[PHPCF_KEY_DESCR_LEFT], 'from' => PHPCF_KEY_LEFT
            ];
            if (is_array($c)) {
                $this->exec[$i] = [];
                foreach ($c as $i_c) $this->exec[$i][] = $i_c;
            } else {
                $this->exec[$i] = [$c];
            }
            $i++;
        }

        $this->exec_ctx[$i] = $context;
        $this->exec[$i] = $this->ptokens[$this->current_pos][PHPCF_KEY_TEXT];

        if (!empty($controls[PHPCF_KEY_RIGHT]) && !$this->isWhiteSpaceAligned($this->current_pos + 1)) {
            $i++;
            $c = $controls[PHPCF_KEY_RIGHT];
            $this->exec_ctx[$i] = $context + [
                'descr' => $controls[PHPCF_KEY_DESCR_RIGHT], 'from' => PHPCF_KEY_RIGHT
            ];
            if (is_array($c)) {
                $this->exec[$i] = [];
                foreach ($c as $i_c) $this->exec[$i][] = $i_c;
            } else {
                $this->exec[$i] = $c;
            }
        }
    }

    /**
     * @return string
     */
    private function getIndentString()
    {
        return str_repeat($this->indent_sequence, $this->indent_level);
    }
}
