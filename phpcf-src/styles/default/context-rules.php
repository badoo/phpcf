<?php
$fsm_nl_tokens = '._NL T_OBJECT_OPERATOR_NL T_BOOLEAN_AND_NL T_BOOLEAN_OR_NL T_LOGICAL_AND_NL T_LOGICAL_OR_NL';

$fsm_parenthesis_rules = [
    '( (_LONG' => ['CTX_GENERIC_PARENTHESIS'],
    '(_EMPTY'  => ['CTX_EMPTY_BLOCK_END'],
];

$fsm_inline_rules = $fsm_parenthesis_rules + [
    '?'                       => ['CTX_TERNARY_BEGIN'],
    'T_COALESCE'              => ['NOW' => ['CTX_TERNARY_OPERATOR'], 'NEXT' => -1],
    'T_OBJECT_OPERATOR $'     => ['CTX_INLINE_BRACE_BEGIN'],
    $fsm_nl_tokens            => ['CTX_INLINE_FIRST_NL'],
    'T_ARRAY'                 => ['CTX_ARRAY'],
    '['                       => ['CTX_GENERIC_SQUARE_PARENTHESIS'],
    'T_ARRAY_SHORT'           => ['CTX_ARRAY_SHORT'],
    'T_ARRAY_SHORT_ML'        => ['CTX_ARRAY_SHORT_ML'],
    'T_ANONFUNC'              => ['CTX_ANONFUNC_D'],
    'T_ANONFUNC_LONG'         => ['CTX_ANONFUNC_LONG_D'],
    'T_FUNCTION_NAME T_UNSET' => ['CTX_FUNCTION_CALL_BEGIN'],
    'T_DOUBLE_COLON'          => ['CTX_DOUBLE_COLON'],
    'T_NEW'                   => ['CTX_NEW'],
];

$fsm_generic_code_rules = [
    'T_FINAL T_ABSTRACT T_CLASS T_INTERFACE T_TRAIT' => ['CTX_CLASS_D'],
    'T_CLOSE_TAG'       => 'CTX_DEFAULT',
    'T_FUNCTION'        => ['CTX_FUNCTION_D'],
    'T_SWITCH'          => ['CTX_SWITCH'],
    'T_WHILE'           => ['CTX_WHILE'],
    'T_FOREACH'         => ['CTX_FOREACH'],
    'T_FOR'             => ['CTX_FOR'],
    'T_DO'              => ['CTX_DO'],
    'T_IF'              => ['CTX_IF'],
    'T_ELSEIF'          => ['CTX_ELSEIF'],
    'T_ELSE'            => ['CTX_ELSE'],
    'T_CASE T_DEFAULT'  => ['CTX_CASE_FIRST_D'],
    'T_DOUBLE_COLON'    => ['CTX_DOUBLE_COLON'],
] + $fsm_inline_rules;

$fsm_generic_code_block_rules = $fsm_generic_code_rules + [
    '}' => -1,
    '{' => ['CTX_GENERIC_BLOCK'],
    '{_EMPTY' => ['CTX_EMPTY_BLOCK_END'],
];
$fsm_context_rules_switch = [
    'CTX_SWITCH' => [
        '{' => 'CTX_GENERIC_BLOCK',
    ],
    'CTX_SWITCH_BLOCK' => [
        'T_CASE T_DEFAULT'    => ['CTX_CASE_D'],
    ] + $fsm_generic_code_block_rules,
    'CTX_CASE_D CTX_CASE_FIRST_D CTX_NOBREAK_CASE_D CTX_CASE_MULTI_D' => [
        ': ;' => ['NOW' => 'CTX_SWITCH_KEYWORD', 'NEXT' => 'CTX_CASE_MULTI_COLON'],
    ],
    'CTX_CASE_MULTI_COLON' => [
        'T_CASE T_DEFAULT' => 'CTX_CASE_MULTI_D',
        'T_BREAK' => 'CTX_CASE_BREAK',
        '}' => ['NOW' => 'CTX_CASE_END_OF_BLOCK', 'NEXT' => -2],
        PHPCF_KEY_ALL => 'CTX_CASE',
    ] + $fsm_generic_code_block_rules,
    'CTX_CASE' => [
        'T_CASE T_DEFAULT' => 'CTX_NOBREAK_CASE_D',
        'T_BREAK'          => 'CTX_CASE_BREAK',
        '}'                => ['NOW' => 'CTX_CASE_END_OF_BLOCK', 'NEXT' => -2],
    ] + $fsm_generic_code_block_rules,
    'CTX_CASE_BREAK' => [
        ';' => ['NOW' => -1, 'NEXT' => 'CTX_SWITCH_BLOCK',],
    ],
];

$fsm_context_rules_loops = [
    'CTX_WHILE' => [
        '{' => 'CTX_GENERIC_BLOCK',
        '{_EMPTY' => 'CTX_EMPTY_BLOCK_END',
        ';' => -1
    ] + $fsm_inline_rules,
    'CTX_FOREACH' => [
        '{' => 'CTX_GENERIC_BLOCK',
        '{_EMPTY' => 'CTX_EMPTY_BLOCK_END',
        '; :' => -1,
    ] + $fsm_inline_rules,
    'CTX_FOR' => [
        '( (_LONG (_EMPTY' => 'CTX_FOR_PARENTHESIS',
    ],
    'CTX_DO' => [
        '{' => ['CTX_GENERIC_BLOCK'],
        'T_WHILE' => 'CTX_WHILE_AFTER_DO',
        PHPCF_KEY_ALL => -1
    ],
    'CTX_WHILE_AFTER_DO' => [
        ';' => -1,
    ],
];

$fsm_context_rules_parenthesis = [
    'CTX_FOR_PARENTHESIS' => [
        ')' => -1,
    ] + $fsm_inline_rules,
    'CTX_GENERIC_PARENTHESIS' => [
        ')' => -1,
    ] + $fsm_inline_rules,
    'CTX_ARRAY_LONG_PARENTHESIS' => [
        ')'            => ['NOW' => 'CTX_LONG_PAR_END', 'NEXT' => -1],
        $fsm_nl_tokens => ['CTX_LONG_FIRST_NL'],
    ] + $fsm_inline_rules,
];

$fsm_context_rules_square_parenthesis = [
    'CTX_GENERIC_SQUARE_PARENTHESIS' => [
        ']' => -1,
    ] + $fsm_inline_rules,
    'CTX_ARRAY_SHORT_ML_SQUARE_PARENTHESIS' => [
        ']' => ['NOW' => 'CTX_LONG_PAR_END', 'NEXT' => -1],
        $fsm_nl_tokens => ['CTX_LONG_FIRST_NL'],
    ] + $fsm_inline_rules,
];

$fsm_context_rules_conditions = [
    'CTX_IF CTX_ELSEIF' => [
        '(_LONG'  => ['CTX_GENERIC_PARENTHESIS'],
        '{'       => 'CTX_GENERIC_BLOCK',
        '{_EMPTY' => 'CTX_EMPTY_BLOCK_END',
        ': ;'     => -1,
    ] + $fsm_inline_rules,
    'CTX_ELSE' => [
        'T_IF'    => 'CTX_ELSEIF',
        '{'       => 'CTX_GENERIC_BLOCK',
        '{_EMPTY' => 'CTX_EMPTY_BLOCK_END',
        ': ;'     => -1,
    ],
    'CTX_TERNARY_BEGIN' => [
        ':' => ['NOW' => 'CTX_TERNARY_OPERATOR', 'NEXT' => -1],
        $fsm_nl_tokens => 'CTX_TERNARY_BEGIN',
    ] + $fsm_inline_rules,
    'CTX_TERNARY_OPERATOR_H' => [
        PHPCF_KEY_ALL => ['NOW' => 'CTX_TERNARY_OPERATOR', 'NEXT' => -1],
    ],
];

$fsm_context_rules_array = [
    'CTX_ARRAY' => [
        '(_LONG'  => 'CTX_ARRAY_LONG_PARENTHESIS',
        '('       => 'CTX_GENERIC_PARENTHESIS',
        '(_EMPTY' => 'CTX_EMPTY_BLOCK_END',
    ],
    // array like [1,2,3]
    'CTX_ARRAY_SHORT' => [
        ']' => ['NOW' => 'CTX_ARRAY_SHORT', 'NEXT' => -1],
    ] + $fsm_inline_rules,
    // multiline short array
    'CTX_ARRAY_SHORT_ML' => [
        '[' => ['CTX_ARRAY_SHORT_ML_SQUARE_PARENTHESIS'],
        'T_ARRAY_SHORT' => ['CTX_ARRAY_SHORT'],
        'T_ARRAY_SHORT_ML' => ['CTX_ARRAY_SHORT_ML'],
        ']' => ['NOW' => 'CTX_ARRAY_SHORT_ML', 'NEXT' => -1],
        $fsm_nl_tokens => ['CTX_LONG_FIRST_NL'],
    ] + $fsm_inline_rules,
];

$fsm_context_rules_class = [
    'CTX_CLASS_D' => [
        '{' => ['NOW' => 'CTX_CLASS_D', 'NEXT' => 'CTX_CLASS'],
        '{_EMPTY' => 'CTX_CLASS_EMPTY',
    ],
    'CTX_CLASS_D_ANON' => [
        '{' => ['NOW' => 'CTX_CLASS_D_ANON', 'NEXT' => 'CTX_CLASS'],
        '(' => ['NOW' => ['CTX_FUNCTION_CALL_BEGIN'], 'NEXT' => 'CTX_FUNCTION_PARAMS'],
        '{_EMPTY' => 'CTX_CLASS_EMPTY',
    ],
    'CTX_CLASS_EMPTY' => [
        '}' => ['NOW' => 'CTX_CLASS_EMPTY', 'NEXT' => -1],
    ],
    'CTX_CLASS' => [
        'T_PUBLIC T_PRIVATE T_PROTECTED T_STATIC' => ['CTX_CLASS_DEF'],
        'T_CONST'             => ['CTX_CLASS_CONST_D'],
        'T_FINAL T_ABSTRACT'  => ['CTX_CLASS_DEF'],
        'T_CONST_NL'          => ['CTX_CLASS_CONST_DEF_NL'],
        'T_PUBLIC_NL T_PRIVATE_NL T_PROTECTED_NL T_STATIC_NL T_FINAL_NL' => ['CTX_CLASS_DEF_NL'],
        'T_VAR'               => ['CTX_CLASS_VARIABLE_D'],
        'T_FUNCTION'          => ['CTX_CLASS_METHOD_D'],
        '}'                   => -1,
        'T_USE'               => ['CTX_TRAIT_USE_D'], // trait use in class
    ],
    // trait "use" inside class
    'CTX_TRAIT_USE_D' => [
        '{'         => ['CTX_TRAIT_USE'], //non-empty resolving block
        '{_EMPTY'   => 'CTX_TRAIT_USE_EMPTY', // empty resolving block
        ';'         => -1, // abscent resolving block
    ],
    // conflict resolve section inside use block
    'CTX_TRAIT_USE' => [
        '}' => ['NOW' => 'CTX_TRAIT_USE', 'NEXT' => -2]
    ],
    // empty conflict resolve section
    'CTX_TRAIT_USE_EMPTY' => [
        '}' => ['NOW' => 'CTX_TRAIT_USE_EMPTY', 'NEXT' => -1],
    ],
    'CTX_CLASS_DEF' => [
        'T_FUNCTION'      => 'CTX_CLASS_METHOD_D',
        'T_CONST'         => 'CTX_CLASS_CONST_D',
        'T_VARIABLE'      => 'CTX_CLASS_VARIABLE_D',
        'T_CONST_NL'      => 'CTX_CLASS_CONST_DEF_NL',
        'T_PUBLIC_NL T_PRIVATE_NL T_PROTECTED_NL T_STATIC_NL T_FINAL_NL' => 'CTX_CLASS_DEF_NL',
    ],
    'CTX_CLASS_DEF_NL' => [
        'T_FUNCTION'  => 'CTX_CLASS_METHOD_D_NL',
        'T_STRING'    => 'CTX_CLASS_CONST_D_NL',
        'T_VARIABLE'  => 'CTX_CLASS_VARIABLE_D_NL',
    ],
    'CTX_CLASS_CONST_DEF_NL' => [
        'T_STRING' => 'CTX_CLASS_CONST_D_NL',
        // PHP7 allow to use some keywords as constant name
        'T_ARRAY T_FUNCTION T_ANONFUNC T_LIST T_INCLUDE T_DEFAULT' => 'CTX_CLASS_CONST_D_NL',
    ],
    'CTX_CLASS_CONST_D' => [
        'T_STRING' => 'CTX_CLASS_CONST',
        // PHP7 allow to use some keywords as constant name
        'T_ARRAY T_FUNCTION T_ANONFUNC T_LIST T_INCLUDE T_DEFAULT' => 'CTX_CLASS_CONST',
    ],
    'CTX_CLASS_CONST_D_NL' => [
        'T_STRING' => 'CTX_CLASS_CONST_NL',
        // PHP7 allow to use some keywords as constant name
        'T_ARRAY T_FUNCTION T_LIST T_INCLUDE T_DEFAULT' => 'CTX_CLASS_CONST_NL',
        ';' => ['NOW' => 'CTX_CLASS_CONST_NL_END', 'NEXT' => -1]
    ] + $fsm_inline_rules,
    'CTX_CLASS_CONST' => [
        'T_DOUBLE_COLON' => ['CTX_DOUBLE_COLON'],
        ';' => -1,
    ] + $fsm_inline_rules,
    'CTX_CLASS_CONST_NL' => [
        'T_DOUBLE_COLON' => ['CTX_DOUBLE_COLON'],
        ';' => ['NOW' => 'CTX_CLASS_CONST_NL_END', 'NEXT' => -1]
    ] + $fsm_inline_rules,
    'CTX_CLASS_VARIABLE_D' => [
        ';' => -1,
    ] + $fsm_inline_rules,
    'CTX_CLASS_VARIABLE_D_NL' => [
        ';' => ['NOW' => 'CTX_CLASS_VARIABLE_D_NL_END', 'NEXT' => -1]
    ] + $fsm_inline_rules,
    'CTX_CLASS_METHOD_D' => [
        '(_LONG' => 'CTX_CLASS_METHOD_LONG_D',
        ';' => -1,
        '{' => ['NOW' => 'CTX_CLASS_METHOD_D', 'NEXT' => 'CTX_CLASS_METHOD'],
        '{_EMPTY' => 'CTX_CLASS_METHOD_EMPTY',
        ':' => ['CTX_METHOD_RETURN_D'],
    ] + $fsm_inline_rules,
    'CTX_METHOD_RETURN_D' => [
        ';' => -2,
        '{' => ['NOW' => -1, 'NEXT' => 'CTX_CLASS_METHOD'],
        '{_EMPTY' => ['REPLACE' => [-2, ['CTX_EMPTY_BLOCK_END']]],
    ],
    'CTX_CLASS_METHOD_D_NL' => [
        '(_LONG' => 'CTX_CLASS_METHOD_LONG_D',
        ';' => -1,
        '{' => ['NOW' => 'CTX_CLASS_METHOD_D', 'NEXT' => 'CTX_CLASS_METHOD'],
        '{_EMPTY' => 'CTX_CLASS_METHOD_EMPTY',
        ':' => ['CTX_METHOD_RETURN_D']
    ] + $fsm_inline_rules,
    'CTX_CLASS_METHOD_LONG_D' => [
        '( (_EMPTY' => ['CTX_METHOD_LONG_D_PAR'],
        ';' => -1,
        '{ {_EMPTY' => ['NOW' => 'CTX_CLASS_METHOD_LONG_D', 'NEXT' => 'CTX_CLASS_METHOD'],
    ] + $fsm_inline_rules,
    'CTX_METHOD_LONG_D_PAR' => [
        ')' => ['NOW' => 'CTX_METHOD_LONG_D_PAR', 'NEXT' => -1],
    ] + $fsm_inline_rules,
    'CTX_FUNCTION_D' => [
        '(_LONG'  => 'CTX_FUNCTION_LONG_D',
        '{'       => ['NOW' => 'CTX_FUNCTION_D', 'NEXT' => 'CTX_FUNCTION'],
        '{_EMPTY' => 'CTX_EMPTY_BLOCK_END',
        ':'       => ['CTX_FUNCTION_RETURN_D'],
    ],
    'CTX_FUNCTION_RETURN_D' => [
        '(_LONG {' => ['NOW' => -1, 'NEXT' => 'CTX_FUNCTION'],
        '{_EMPTY' => ['REPLACE' => [-2, ['CTX_EMPTY_BLOCK_END']]],
    ],
    'CTX_EMPTY_BLOCK_END' => [
        '}' => ['NOW' => 'CTX_EMPTY_BLOCK_END', 'NEXT' => -1],
        ')' => ['NOW' => 'CTX_EMPTY_BLOCK_END', 'NEXT' => -1]
    ] + $fsm_generic_code_block_rules,
    'CTX_FUNCTION_LONG_D' => [
        '{' => ['NOW' => 'CTX_FUNCTION_LONG_D', 'NEXT' => 'CTX_FUNCTION'],
        '( (_EMPTY' => ['CTX_FUNCTION_LONG_D_PAR'],
        '{_EMPTY' => 'CTX_EMPTY_BLOCK_END',
        ':'       => ['CTX_FUNCTION_RETURN_D'],
    ] + $fsm_inline_rules,
    'CTX_FUNCTION_LONG_D_PAR' => [
        ')' => ['NOW' => 'CTX_FUNCTION_LONG_D_PAR', 'NEXT' => -1],
    ] + $fsm_inline_rules,
    'CTX_CLASS_METHOD' => ['}' => -1] + $fsm_generic_code_block_rules,
    'CTX_CLASS_METHOD_EMPTY' => ['}' => ['NOW' => 'CTX_CLASS_METHOD_EMPTY', 'NEXT' => -1]] + $fsm_generic_code_block_rules,
    'CTX_FUNCTION' => $fsm_generic_code_block_rules,
    'CTX_ANONFUNC_D' => [
        '{' => ['NOW' => 'CTX_ANONFUNC_D', 'NEXT' => 'CTX_ANONFUNC'],
        '{_EMPTY' => 'CTX_EMPTY_BLOCK_END',
    ],
    'CTX_ANONFUNC' => [
        '}' => ['NOW' => 'CTX_ANONFUNC_END', 'NEXT' => -1],
    ] + $fsm_generic_code_block_rules,
    'CTX_ANONFUNC_LONG_D' => [
        '{' => ['NOW' => 'CTX_ANONFUNC_LONG_D', 'NEXT' => 'CTX_ANONFUNC_LONG'],
        '{_EMPTY' => 'CTX_EMPTY_BLOCK_END',
    ],
    'CTX_ANONFUNC_LONG' => $fsm_generic_code_block_rules,
    'CTX_FUNCTION_CALL_BEGIN' => [
        '('      => ['NOW' => 'CTX_FUNCTION_CALL_BEGIN', 'NEXT' => 'CTX_FUNCTION_PARAMS'],
        '(_LONG' => ['NOW' => 'CTX_FUNCTION_LONG_CALL_BEGIN', 'NEXT' => 'CTX_FUNCTION_LONG_PARAMS'],
        '(_EMPTY' => 'CTX_FUNCTION_PARAMS',
    ],
    'CTX_FUNCTION_PARAMS' => [
        ')' => ['NOW' => 'CTX_FUNCTION_CALL_END', 'NEXT' => -1],
        //'T_FUNCTION' => ['CTX_ANONFUNC_D'],
    ] + $fsm_inline_rules,
    'CTX_FUNCTION_LONG_PARAMS' => [
        ')'            => ['NOW' => 'CTX_FUNCTION_LONG_PARAMS_END', 'NEXT' => -1],
        //'T_FUNCTION' => ['CTX_ANONFUNC_LONG_D'],
        $fsm_nl_tokens => ['CTX_LONG_FIRST_NL'],
    ] + $fsm_inline_rules,
];

$fsm_context_rules_brace_begin = [
    '{'                       => 'CTX_INLINE_BRACE',
    'T_FUNCTION_NAME T_UNSET' => 'CTX_FUNCTION_CALL_BEGIN',
    PHPCF_KEY_ALL             => -1, // it is not Class expression static call, go away
];

$fsm_context_rules_new = [
    'CTX_NEW' => [
        'T_CLASS' => 'CTX_CLASS_D_ANON',
        'T_FUNCTION_NAME' => 'CTX_FUNCTION_CALL_BEGIN', // new ClassName(...), throw new Exception(...)
        PHPCF_KEY_ALL => -1,
    ],
];

$fsm_context_rules = [
    0 => 'CTX_DEFAULT',
    'CTX_DEFAULT' => [
        'T_OPEN_TAG'           => 'CTX_PHP',
        'T_OPEN_TAG_WITH_ECHO' => 'CTX_PHP',
    ],
    'CTX_PHP' => [
        'T_HALT_COMPILER' => 'CTX_HALT_COMPILER',
    ] + $fsm_generic_code_rules,
    'CTX_INLINE_BRACE_BEGIN' => $fsm_context_rules_brace_begin,
    'CTX_INLINE_BRACE' => [
        '}' => ['NOW' => 'CTX_INLINE_BRACE', 'NEXT' => -1],
    ],
    'CTX_GENERIC_BLOCK' => $fsm_generic_code_block_rules,
    'CTX_LONG_FIRST_NL CTX_LONG_EXPR_NL' => [
        $fsm_nl_tokens            => 'CTX_LONG_EXPR_NL',
        'T_DOUBLE_ARROW ,'        => ['NOW' => 'CTX_INLINE_EXPR_NL_END', 'NEXT' => -1],
        ', ,_LONG'                => ['NOW' => 'CTX_LONG_EXPR_NL_END',   'NEXT' => -1],
        ') ]'                     => ['NOW' => 'CTX_LONG_EXPR_NL_END',   'NEXT' => -2],
    ] + $fsm_inline_rules,
    'CTX_INLINE_FIRST_NL CTX_INLINE_EXPR_NL' => [
        $fsm_nl_tokens         => 'CTX_INLINE_EXPR_NL',
        'T_DOUBLE_ARROW'       => ['NOW' => 'CTX_INLINE_EXPR_NL_END', 'NEXT' => -1],
        ')'                    => ['NOW' => 'CTX_INLINE_EXPR_NL_END', 'NEXT' => -2],
        ';'                    => ['NOW' => 'CTX_INLINE_EXPR_NL_END', 'NEXT' => -1],
        // this context is currently unused
        ':' => ['REPLACE' => [-2, ['CTX_TERNARY_OPERATOR_H']]]
    ] + $fsm_inline_rules,
    'CTX_DOUBLE_COLON' => [
        // support for class reference Class::class
        'T_CLASS' => ['NOW' => 'CTX_DOUBLE_COLON', 'NEXT' => -1]
    ] + $fsm_context_rules_brace_begin,
];

$fsm_context_rules += $fsm_context_rules_parenthesis;
$fsm_context_rules += $fsm_context_rules_square_parenthesis;
$fsm_context_rules += $fsm_context_rules_conditions;
$fsm_context_rules += $fsm_context_rules_loops;
$fsm_context_rules += $fsm_context_rules_switch;
$fsm_context_rules += $fsm_context_rules_array;
$fsm_context_rules += $fsm_context_rules_new;
$fsm_context_rules += $fsm_context_rules_class;

return $fsm_context_rules;
