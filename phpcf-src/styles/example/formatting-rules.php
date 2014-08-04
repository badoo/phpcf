<?php
// in this example we will add rule about namespaces:
//   - there must be only 1 namespace per line (check for new line before "namespace")
//   - there must be strictly one space after "namespace"
//   - there must be no spaces before ";" in namespace definition
//   - there must be no spaces in namespace name:
//       * no spaces before and after "\" (T_NS_SEPARATOR)
//       * no spaces before and after path segment name (T_STRING)

// formatted example:
//    <?php
//    
//    namespace \Hello\World;
//    namespace \Something\Else;

// you specify formatting by modifying $controls:
// $controls = array( TOKEN_NAME => array( CTX_NAME => rule[, PHPCF_KEY_ALL => rule] ) );

$controls += [
    // rule for both T_NS_SEPARATOR and T_STRING
    'T_NS_SEPARATOR T_STRING' => [
        // in our defined context CTX_NAMESPACE_D
        'CTX_NAMESPACE_D' => [
            // rules described in more details below
            PHPCF_KEY_DESCR_LEFT => 'No spaces before "\" and segment name in namespace definition',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES,
            PHPCF_KEY_DESCR_RIGHT => 'No spaces after "\" and segment name in namespace definition',
            PHPCF_KEY_RIGHT => PHPCF_EX_DELETE_SPACES,
        ],
    ],
    // rule for "namespace"
    'T_NAMESPACE' => [
        // rule for "namespace" in context "CTX_NAMESPACE_D", defined in "context-rules.php"
        'CTX_NAMESPACE_D' => [
            // description of operation that needs to be performed for whitespace to the left of token
            PHPCF_KEY_DESCR_LEFT => '1 or 2 new lines before "namespace"',
            // for whitespace on the left of token, do the following: PHPCF_EX_CHECK_NL (check for "\n" or "\n\n")
            PHPCF_KEY_LEFT => PHPCF_EX_CHECK_NL,
            // description for whitespace on the right
            PHPCF_KEY_DESCR_RIGHT => 'Space after "namespace"',
            // for whitespace on the right, shink whitespace to " " with higher priority than PHPCF_EX_DELETE_SPACES
            // that is defined for "\" (T_NS_SEPARATOR) or segment name (T_STRING) in namespace context
            PHPCF_KEY_RIGHT => PHPCF_EX_SHRINK_SPACES_STRONG,
        ],
    ],
    // there will be no additional rules required for ";", as there already exists the rule that is exactly what we need:
    /*
    ';' => array(
        PHPCF_KEY_ALL => array(
            PHPCF_KEY_DESCR_LEFT => 'No whitespace before ";"',
            PHPCF_KEY_LEFT => PHPCF_EX_DELETE_SPACES_STRONG,
            PHPCF_KEY_DESCR_RIGHT => '1 or 2 newlines after ";"',
            PHPCF_KEY_RIGHT => PHPCF_EX_CHECK_NL,
        ),
        ...
    ),
    */
];
