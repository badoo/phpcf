<?php
// you need to modify $fsm_context_rules that is presented as array( CTX_NAME => array( TOKEN_NAME => RULE ) )
// we will add context "CTX_NAMESPACE_D" for namespace definition

// add CTX_NAMESPACE_D to stack when see token T_NAMESPACE in CTX_PHP (default PHP code context)
$fsm_context_rules['CTX_PHP']['T_NAMESPACE'] = ['CTX_NAMESPACE_D'];

// define context switch rules for our context 'namespace definition' (rules are very simple):
$fsm_context_rules['CTX_NAMESPACE_D'] = [
    ';' => -1,  // when see ";", pop current context and return to CTX_PHP
];
