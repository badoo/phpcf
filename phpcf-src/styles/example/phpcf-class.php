<?php
/* Class must be named as PHPCF_<Style> */
namespace Phpcf\Impl;

class PHPCF_Example extends Formatter
{
    protected function init()
    {
        parent::init();
        // add our custom hook for T_NAMESPACE: we will just echo it :)
        $this->token_hook_callbacks[T_NAMESPACE] = 'tokenHookNamespace';
    }

    /*
    We can do basically everything we want in a hook, as it is just a PHP method.

    Params:
        $idx_tokens = array( T_NAMESPACE => 'T_NAMESPACE', ... );
        $i_value = token from token_get_all():
            it is ';', '.' and other single-character strings
            or array(
                0 => T_...,  // token code
                1 => '...',  // token text context
                2 => ...,    // token line
            )

    Token hook can operate the following properties:
        current_line:
            You need to set this property if token contains token line (is_array)
            or you can read the current line value from this property otherwise
        tokens:
            An array that is iterated using each(). It contains result of token_get_all().
            You can get next token index using key($this->tokens)                  (ex. tokenHookBinary),
                or you can skip next tokens by iterating manually using while/each (ex. tokenHookStr).

    Return value:

        return array of parsed tokens: return array([<parsed_token1>, [..., <parsed_tokenN>]]);

        - empty array means that supplied token must be deleted
        - <parsed_tokenI> = array(
            PHPCF_KEY_CODE => 'T_...', // name of token (you can even invent new tokens and use them in format rules)
            PHPCF_KEY_TEXT => '...',   // contents of token
            PHPCF_KEY_LINE => ...,     // line number of the token (should correspond to source token)
        )
    */
    protected function tokenHookNamespace($idx_tokens, $i_value)
    {
        // we will just echo that we encountered a token and return it, parsed
        $this->current_line = $i_value[2]; // set current line for tokens that do not have line information
        $this->sniffMessage("Encountered '$i_value[1]'");

        return [
            [
                PHPCF_KEY_CODE => $idx_tokens[$i_value[0]],
                PHPCF_KEY_TEXT => $i_value[1],
                PHPCF_KEY_LINE => $this->current_line,
            ],
        ];
    }
}
