<?php
$casts = 'T_INT_CAST T_DOUBLE_CAST T_BOOL_CAST T_STRING_CAST T_ARRAY_CAST T_OBJECT_CAST T_UNSET_CAST';
$binary_operators = 'T_AND_EQUAL T_CONCAT_EQUAL T_DIV_EQUAL T_IS_EQUAL T_IS_GREATER_OR_EQUAL '
    . 'T_IS_NOT_EQUAL T_IS_SMALLER_OR_EQUAL T_MINUS_EQUAL T_MOD_EQUAL T_MUL_EQUAL '
    . 'T_OR_EQUAL T_PLUS_EQUAL T_SL_EQUAL T_SR_EQUAL T_XOR_EQUAL T_COALESCE_EQUAL '
    . '= + & - * ^ % / ? | < > . T_IS_IDENTICAL T_IS_NOT_IDENTICAL T_IS_EQUAL T_IS_NOT_EQUAL '
    . 'T_LOGICAL_AND T_BOOLEAN_AND T_LOGICAL_OR T_BOOLEAN_OR T_LOGICAL_XOR T_SL T_SR';

if (defined('T_SPACESHIP')) {
    $binary_operators .= ' T_SPACESHIP';
}

$onespace = [
    PHPCF_KEY_ALL => [
        PHPCF_KEY_DESCR_LEFT => 'One space before as, elseif, else, catch, insteadof, instanceof, finally',
        PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
        PHPCF_KEY_DESCR_RIGHT => 'One space after as, elseif, else, catch, insteadof, instanceof, finally',
        PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
    ]
];

$controls = [
    '{' => [
        'CTX_INLINE_BRACE' => [
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "{" in "->{...}" expression',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after "{" in "->{...}" expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
        'CTX_FUNCTION_D CTX_FUNCTION_LONG_D CTX_CLASS_METHOD_LONG_D CTX_CLASS_D CTX_CLASS_METHOD_D CTX_TRAIT_USE' => [
            PHPCF_KEY_DESCR_LEFT => '"{" to start on a new line in function/method/class/trait declaration',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "{" on the same line and no empty lines',
            PHPCF_KEY_RIGHT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS],
        ],
        'CTX_ANONFUNC_D' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "{" in short anonymous function',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "{" in short anonymous function',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "{"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "{" on the same line and no empty lines',
            PHPCF_KEY_RIGHT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS],
        ],
    ],
    '}' => [
        'CTX_INLINE_BRACE' => [
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "}" in "->{...}" expression',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'No whitespace after "}" in "->{...}" expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
        'CTX_CASE_END_OF_BLOCK' => [
            PHPCF_KEY_DESCR_LEFT => '"}" to start on a new line',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS],
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "}" on the same line',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ],
        'CTX_ANONFUNC_END' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "}" in short anonymous function',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        'CTX_CLASS_METHOD_EMPTY' => [
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "}" in empty class method',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'New line after "}" in empty method',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL
        ],
        'CTX_CLASS_EMPTY CTX_TRAIT_USE_EMPTY' => [
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "}" in empty class',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'New line after "}" in empty class',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL
        ],
        'CTX_EMPTY_BLOCK_END' => [
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before "}" in "{}"  block',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'New line after "}" in "{}" block',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL
        ],
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => '"}" to start on a new line',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS],
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "}" on the same line',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ],
    ],
    'T_IF T_FOR T_FOREACH T_DO T_ELSE_INLINE T_ELSEIF_INLINE T_TRY T_DECLARE' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'if, for, foreach, etc. to start on a new line',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after if, for, foreach, etc. on the same line and no empty lines',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_AS T_ELSEIF T_ELSE T_CATCH T_INSTEADOF T_INSTANCEOF' => $onespace,
    'T_WHILE' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => '"while" to start on a new line',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "while"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        'CTX_WHILE_AFTER_DO' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "while" in "do/while"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "while" in "do/while"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_CLASS T_INTERFACE T_TRAIT' => [
        'CTX_DOUBLE_COLON CTX_CASE_D CTX_CASE_FIRST_D CTX_CASE_MULTI_D' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before "class" in class reference call',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
        ],
        'CTX_CLASS_D_ANON' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "class" in anon class declaration',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "class" in anon class declaration',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'empty line before "class/interface/trait"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS_2,
            PHPCF_KEY_DESCR_RIGHT => 'One space after class/interface/trait name',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ],
    ],
    'T_EXTENDS T_IMPLEMENTS' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "extends/implements"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "extends/implements"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ]
    ],
    'T_DOC_COMMENT_B4_CLASS T_DOC_COMMENT_B4_TRAIT T_DOC_COMMENT_B4_INTERFACE' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'Class/trait/interface to start on a new line after doc block comment and no empty string before class/trait/interface definition',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS_STRONG,
        ],
    ],
    'T_NAMESPACE' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before "namespace"',
            PHPCF_KEY_LEFT => [PHPCF_EX_SHRINK_SPACES_STRONG, PHPCF_EX_CHECK_NL_STRONG],
            PHPCF_KEY_DESCR_RIGHT => 'One space after "namespace"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ]
    ],
    'T_STATIC T_VAR T_PUBLIC T_PRIVATE T_PROTECTED T_CONST T_FINAL T_ABSTRACT' => [
        'CTX_CLASS' => [
            PHPCF_KEY_DESCR_LEFT => 'class properties/methods declarations to start on a new line',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "final", "abstract", "public" etc.',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        'CTX_TRAIT_USE' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "public", "protected", "private" in trait resolve section',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "public", "protected", "private" in trait resolve section',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'One space after "final", "abstract", "public" etc.',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_STATIC_NL T_VAR_NL T_PUBLIC_NL T_PRIVATE_NL T_PROTECTED_NL T_CONST_NL T_FINAL_NL T_ABSTRACT_NL' => [
        'CTX_TRAIT_USE' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "public", "protected", "private" in trait resolve section',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "public", "protected", "private" in trait resolve section',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'nothing after var, const, etc. on the same line and no empty lines',
            PHPCF_KEY_RIGHT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS],
        ],
    ],
    'T_OBJECT_OPERATOR T_DOUBLE_COLON' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before "::" and "->"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "::" and "->"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
    ],
    '( (_LONG' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "("',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ],
        'CTX_GENERIC_PARENTHESIS' => [
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "("',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
        'CTX_FUNCTION_LONG_CALL_BEGIN CTX_ARRAY_LONG_PARENTHESIS CTX_FUNCTION_LONG_D CTX_CLASS_METHOD_LONG_D' => [
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "(" on the same line in long expression',
            PHPCF_KEY_RIGHT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_CHECK_NL]
        ],
        'CTX_FUNCTION_CALL_BEGIN' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before "(" in function call',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "("',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ]
    ],
    ')' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ")"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ")"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ],
        'CTX_GENERIC_PARENTHESIS' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ")"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ")"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ],
        'CTX_FUNCTION_LONG_PARAMS_END CTX_LONG_PAR_END CTX_FUNCTION_LONG_D CTX_CLASS_METHOD_LONG_D' => [
            PHPCF_KEY_DESCR_LEFT => '")" to start on a new line in long expression',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_CHECK_NL],
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after ")" in long expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
        'CTX_INLINE_EXPR_NL_END' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ")" in end of long expression',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DELETE_SPACES_STRONG],
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after ")" in long expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
        'CTX_LONG_EXPR_NL_END' => [
            PHPCF_KEY_DESCR_LEFT => '")" to start on a new line in long expression',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DECREASE_INDENT, PHPCF_EX_CHECK_NL],
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after ")" in long expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ]
    ],
    ';' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ";"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after ";" on the same line',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ],
        'CTX_FOR_PARENTHESIS' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ";"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ";" in for()',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ],
        'CTX_INLINE_EXPR_NL_END' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ";" in end of long expression',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DELETE_SPACES_STRONG],
            PHPCF_KEY_DESCR_RIGHT => 'nothing after ";" on the same line',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ],
        'CTX_CLASS_CONST_NL_END CTX_CLASS_VARIABLE_D_NL_END' => [
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ";" in end of long property definition',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DELETE_SPACES_STRONG],
            PHPCF_KEY_DESCR_RIGHT => 'nothing after ";" on the same line',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ],
        'CTX_SWITCH_KEYWORD' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ";" in "case;"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "case;" on the same line an no empty lines',
            PHPCF_KEY_RIGHT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS],
        ],
        'CTX_HALT_COMPILER' => [
            PHPCF_KEY_DESCR_LEFT => 'No changes',
            PHPCF_KEY_LEFT => PHPCF_EX_DO_NOT_TOUCH_ANYTHING,
            PHPCF_KEY_DESCR_RIGHT => 'No changes',
            PHPCF_KEY_RIGHT => PHPCF_EX_DO_NOT_TOUCH_ANYTHING,
        ]
    ],
    '?' => [
        'CTX_CLASS_DEF' => [
            PHPCF_KEY_DESCR_RIGHT => 'No space after (?) in typed property declaration',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ],
    ],
    '?_NULLABLE_MARK' => [
        'CTX_FUNCTION_RETURN_D CTX_METHOD_RETURN_D' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before nullable type mark (?) in function/method return hint',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after nullable type mark (?) in function/method return hint',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
        'CTX_FUNCTION_D CTX_FUNCTION_PARAMS CTX_FUNCTION_LONG_PARAMS CTX_ANONFUNC_LONG_D CTX_FUNCTION_LONG_D' => [
            PHPCF_KEY_DESCR_LEFT => 'No changes',
            PHPCF_KEY_LEFT => PHPCF_EX_DO_NOT_TOUCH_ANYTHING,
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after nullable type mark (?) in function/method signature',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
    ],
    $binary_operators => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'One space before binary operators (= < > * . etc) ',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after binary operators (= < > * . etc) ',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_USE' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => '"use" in namespaces to start on a new line',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "use" in namespaces',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        'CTX_ANONFUNC_LONG_D CTX_ANONFUNC_D' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "use" in function declaration',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "use" in function declaration',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_DOUBLE_ARROW' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "=>"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "=>"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        'CTX_INLINE_EXPR_NL_END CTX_LONG_EXPR_NL_END' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "=>"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "=>"',
            PHPCF_KEY_RIGHT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_SPACES_STRONG],
        ]
    ],
    '+_UNARY -_UNARY &_UNARY ! @ $ ~' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after unary: + - & ! @ $',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ],
    ],
    '& &_UNARY' => [
        'CTX_FUNCTION_D CTX_FUNCTION_PARAMS CTX_FUNCTION_LONG_PARAMS CTX_ANONFUNC_LONG_D CTX_FUNCTION_LONG_D' => [
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "&" in function/method signature',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ]
    ],
    '._NL T_BOOLEAN_AND_NL T_BOOLEAN_OR_NL T_LOGICAL_AND_NL T_LOGICAL_OR_NL' => [
        'CTX_INLINE_FIRST_NL CTX_LONG_FIRST_NL' => [
            PHPCF_KEY_DESCR_LEFT => 'operator (. && ||) to start on a new line in multiline expression and no empty lines',
            PHPCF_KEY_LEFT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS],
            PHPCF_KEY_DESCR_RIGHT => 'Single space after operator (. && ||) in multiline expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        'CTX_INLINE_EXPR_NL CTX_LONG_EXPR_NL' => [
            PHPCF_KEY_DESCR_LEFT => 'operator (. && ||) to start on a new line in multiline expression and no empty lines',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
            PHPCF_KEY_DESCR_RIGHT => 'Single space after operator (. && ||) in multiline expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        'CTX_TERNARY_BEGIN CTX_TERNARY_OPERATOR' => [
            PHPCF_KEY_DESCR_LEFT => 'Inside ternary, space only in left',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'Inside ternary, space only in right',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ]
    ],
    'T_OBJECT_OPERATOR_NL' => [
        'CTX_INLINE_FIRST_NL CTX_LONG_FIRST_NL' => [
            PHPCF_KEY_DESCR_LEFT => 'operator "->" to start on a new line in multiline expression and no empty lines',
            PHPCF_KEY_LEFT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS],
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after operator "->" in multiline expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ],
        'CTX_INLINE_EXPR_NL CTX_LONG_EXPR_NL' => [
            PHPCF_KEY_DESCR_LEFT => 'operator "->" to start on a new line in multiline expression and no empty lines',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after operator "->" in multiline expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ],
    ],
    ',' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ","',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ],
        'CTX_CLASS_CONST_D_NL CTX_CLASS_VARIABLE_D_NL CTX_CLASS_CONST_NL' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "," on the same line in property definition and no empty lines',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ],
        'CTX_FUNCTION_LONG_PARAMS CTX_FUNCTION_LONG_D CTX_CLASS_METHOD_LONG_D' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "," on the same line in long expression and no empty lines',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ],
        'CTX_LONG_EXPR_NL_END' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ","',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DELETE_SPACES_STRONG],
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "," on the same line in long expression and no empty lines',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ],
        'CTX_ARRAY_LONG_PARENTHESIS CTX_ARRAY_SHORT_ML' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'Either newline or space after "," in multiline array',
            PHPCF_KEY_RIGHT => PHPCF_EX_NL_OR_SPACE,
        ],
    ],
    ',_LONG' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ","',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ],
        'CTX_CLASS_CONST_D_NL CTX_CLASS_VARIABLE_D_NL CTX_CLASS_CONST_NL' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "," on the same line in property definition and no empty lines',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS_STRONG,
        ],
        'CTX_FUNCTION_LONG_PARAMS CTX_FUNCTION_LONG_D CTX_CLASS_METHOD_LONG_D' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "," on the same line in long expression and no empty lines',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ],
        'CTX_LONG_EXPR_NL_END' => [
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ","',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DELETE_SPACES_STRONG],
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "," on the same line in long expression and no empty lines',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ],
        'CTX_ARRAY_LONG_PARENTHESIS CTX_ARRAY_SHORT_ML' => [
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ","',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "," on the same line in array item list and no empty lines',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ],
    ],
    '[' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before "["',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "["',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ],
    ],
    ']' => [
        'CTX_ARRAY_SHORT_ML' => [
            PHPCF_KEY_DESCR_LEFT => '"]" to start on a new line in short-syntax multiline array',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_CHECK_NL],
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "]" in short-syntax multiline array',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
        'CTX_LONG_EXPR_NL_END' => [
            PHPCF_KEY_DESCR_LEFT => '"]" to start on a new line in long expression',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_DECREASE_INDENT, PHPCF_EX_CHECK_NL],
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "]" in long expression',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before "]"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
        ],
    ],
    ':' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ":"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after ":"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
        'CTX_SWITCH_KEYWORD' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ":" in "case:"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "case:" on the same line an no empty lines',
            PHPCF_KEY_RIGHT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS],
        ],
        'CTX_CASE_MULTI_COLON' => [
            PHPCF_KEY_DESCR_LEFT => 'Nothing before ":" in "case:" (multi colon)',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "case:" on the same line an no empty lines (multi colon)',
            PHPCF_KEY_RIGHT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS],
        ],
        'CTX_TERNARY_OPERATOR CTX_TERNARY_OPERATOR_H' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before ":" in ternary operator ( ... ? ... : ... )',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ":" in ternary operator ( ... ? ... : ... )',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        'CTX_FUNCTION_RETURN_D CTX_METHOD_RETURN_D' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before ":" in function/method return hint',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after ":" in function/method return hint',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_COALESCE' => [
        'CTX_TERNARY_OPERATOR CTX_TERNARY_OPERATOR_H' => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "??"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "??"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_SWITCH' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => '"switch" to start on a new line',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "switch"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_CASE' => [
        'CTX_CASE_FIRST_D' => [
            PHPCF_KEY_DESCR_LEFT => '"case/default" to start on a new line and no empty lines',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "case"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES
        ],
        'CTX_CASE_D' => [
            PHPCF_KEY_DESCR_LEFT => 'empty line before "case"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS_2,
            PHPCF_KEY_DESCR_RIGHT => 'One space after "case"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES
        ],
        'CTX_CASE_MULTI_D' => [
            PHPCF_KEY_DESCR_LEFT => 'repeated "case" to start on a new line and no empty lines',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS],
            PHPCF_KEY_DESCR_RIGHT => 'One space after "case"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES
        ],
        'CTX_NOBREAK_CASE_D' => [
            PHPCF_KEY_DESCR_LEFT => 'empty line before "case" without break',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS_2],
            PHPCF_KEY_DESCR_RIGHT => 'One space after "case"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ],
    ],
    'T_DEFAULT' => [
        'CTX_CASE_FIRST_D' => [
            PHPCF_KEY_DESCR_LEFT => '"case/default" to start on a new line and no empty lines',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "default"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES
        ],
        'CTX_CASE_D' => [
            PHPCF_KEY_DESCR_LEFT => 'empty line before "default"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS_2,
            PHPCF_KEY_DESCR_RIGHT => 'No spaces after "default"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES
        ],
        'CTX_CASE_MULTI_D' => [
            PHPCF_KEY_DESCR_LEFT => 'repeated "default" to start on a new line and no empty lines',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS],
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "default"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES
        ],
        'CTX_NOBREAK_CASE_D' => [
            PHPCF_KEY_DESCR_LEFT => 'empty line before "default" without break',
            PHPCF_KEY_LEFT => [PHPCF_EX_DECREASE_INDENT, PHPCF_EX_SHRINK_NLS_2],
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "default"',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES
        ],
    ],
    'T_BREAK' => [
        // PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        // breaks the following construct: "break 3;"
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => '"break" to start on a new line',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
        ],
        'CTX_IF CTX_ELSEIF CTX_ELSE' => [
            PHPCF_KEY_DESCR_LEFT => '1 space before "break" in oneline "if/else/elseif"',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
        'CTX_CASE_BREAK' => [
            PHPCF_KEY_DESCR_LEFT => '"break" to start on a new line',
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "break"',
            PHPCF_KEY_RIGHT => [PHPCF_EX_DECREASE_INDENT],
        ],
    ],
    'T_ECHO T_RETURN' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'One space after "echo" and "return"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_FUNCTION' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'One space after "function"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ],
    ],
    // $a--, $b++
    'T_INC_RIGHT T_DEC_RIGHT' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'No whitespaces in "$c++" and type casts',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
        ],
    ],
    // --$a, --$b
    'T_INC_LEFT T_DEC_LEFT ' . $casts => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'No whitespaces in "++$c" and type casts',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
    ],
    // --$a, --$b
    'T_ARRAY' => [
        'CTX_ARRAY' => [
            PHPCF_KEY_DESCR_RIGHT => 'No whitespaces in "++$c" and type casts',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
        'CTX_FUNCTION_D' => [
            PHPCF_KEY_DESCR_RIGHT => 'One whitespace after array type-hint',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_ARRAY_HINT' => [
        'CTX_CLASS_METHOD_D CTX_CLASS_METHOD_LONG_D CTX_FUNCTION_D CTX_FUNCTION_LONG_D CTX_FUNCTION_LONG_PARAMS' => [
            PHPCF_KEY_DESCR_RIGHT => 'One whitespace after array type-hint',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_STRING T_CALLABLE' => [
        'CTX_FUNCTION_D' => [
            PHPCF_KEY_DESCR_RIGHT => 'One whitespace after class name or "callable" in type-hint',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_STRING' => [
        'CTX_CLASS_DEF' => [
            PHPCF_KEY_DESCR_RIGHT => 'One whitespace after class property type',
            PHPCF_KEY_RIGHT => PHPCF_EX_SPACE_IF_NLS,
        ],
    ],
    'T_VARIABLE' => [
        'CTX_CLASS_VARIABLE_D' => [
            PHPCF_KEY_DESCR_LEFT => 'One whitespace before property name',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_DECLARE' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'One whitespace after "declare" and no new line',
            PHPCF_KEY_RIGHT => [PHPCF_EX_SHRINK_SPACES_STRONG,],
        ],
    ],
    /* DO NOT CHANGE rules for comments unless you really know what you are doing */
    'T_SINGLE_LINE_COMMENT' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'No changes',
            PHPCF_KEY_LEFT => PHPCF_EX_DO_NOT_TOUCH_ANYTHING,
            PHPCF_KEY_DESCR_RIGHT => 'single-line comment must end with a line break',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL_STRONG,
        ]
    ],
    'T_SINGLE_LINE_COMMENT_ALONE' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'No changes',
            PHPCF_KEY_LEFT => PHPCF_EX_DO_NOT_TOUCH_ANYTHING,
            PHPCF_KEY_DESCR_RIGHT => 'single-line comment must end with a line break',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL_STRONG,
        ],
    ],
    /* / DO NOT CHANGE */

    'T_OPEN_TAG' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'no whitespace before open tag (T_OPEN_TAG in ALL)',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after opening tag on the same line',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL_STRONG,
        ]
    ],

    'T_WHITESPACE' => [
        'CTX_DEFAULT' => [
            PHPCF_KEY_DESCR_RIGHT => 'no whitespace before open tag (T_WHITESPACE in CTX_DEFAULT)',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ],
    ],

    // curly, opening empty block
    '{_EMPTY' => [
        'CTX_FUNCTION_D CTX_FUNCTION_LONG_D CTX_CLASS_METHOD_LONG_D CTX_CLASS_METHOD_D' => [
            PHPCF_KEY_DESCR_LEFT => '"{" to start on a new line in function/method/class declaration',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "{" on the same line and no empty lines',
            PHPCF_KEY_RIGHT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_SHRINK_NLS],
        ],
        'CTX_CLASS_D' => [
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "{" on the same line and no empty lines',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_NLS,
        ],
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_LEFT => 'One space before "{" in empty block',
            PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    'T_ARRAY_SHORT' => [
        'CTX_ARRAY_SHORT' => [
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after "[" in short-syntax array',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
        ],
    ],
    'T_ARRAY_SHORT_ML' => [
        'CTX_ARRAY_SHORT_ML' => [
            PHPCF_KEY_DESCR_RIGHT => 'nothing after "[" on the same line in multiline short-syntax array',
            PHPCF_KEY_RIGHT => [PHPCF_EX_INCREASE_INDENT, PHPCF_EX_CHECK_NL]
        ],
    ],
    'T_FUNCTION_NAME' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'Nothing after function name',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
    ],
    'T_NEW' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'One whitespace after "new"',
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES,
        ],
    ],
    'T_END_HEREDOC' => [
        PHPCF_KEY_ALL => [
            PHPCF_KEY_DESCR_RIGHT => 'New line required after heredoc end',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL_STRONG,
        ],
    ],
];

if (defined('T_FINALLY')) {
    $controls += [
        'T_FINALLY' => $onespace,
        'T_YIELD' => [
            PHPCF_KEY_ALL => [
                PHPCF_KEY_DESCR_LEFT => '"yield" to start on a new line',
                PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
                PHPCF_KEY_DESCR_RIGHT => 'One space after "yield"',
                PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
            ],
        ],
    ];
}

if (defined('T_ELLIPSIS')) {
    $controls += [
        'T_ELLIPSIS' => [
            'CTX_ARRAY_LONG_PARENTHESIS CTX_ARRAY_SHORT_ML' => [
                PHPCF_KEY_DESCR_LEFT => '"..." to start on a new line inside arrays',
                PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_NLS,
                PHPCF_KEY_DESCR_RIGHT => 'No space after "..."',
                PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
            ],
            PHPCF_KEY_ALL => [
                PHPCF_KEY_DESCR_LEFT => 'One space before "..."',
                PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
                PHPCF_KEY_DESCR_RIGHT => 'No space after "..."',
                PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
            ],
        ],
        'T_FROM' => [
            PHPCF_KEY_ALL => [
                PHPCF_KEY_DESCR_LEFT => 'One space before "from"',
                PHPCF_KEY_LEFT => PHPCF_EX_SHRINK_SPACES_STRONG,
                PHPCF_KEY_DESCR_RIGHT => 'One space after "from"',
                PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
            ],
        ],
    ];
}

if (defined('T_FN')) {
    $controls += [
        'T_FN' => [
            PHPCF_KEY_ALL => [
                PHPCF_KEY_DESCR_RIGHT => 'No space after "fn"',
                PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES_STRONG,
            ],
        ],
    ];
}

return $controls;
