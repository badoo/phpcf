<?php
/**
 * @author Yuriy Nasretdinov <y.nasretdinov@corp.badoo.com>
 */

if (!defined('PHPCF_DEFINED')) {
    define('PHPCF_VERSION', "1.0.0");
    define('PHPCF_DEFINED', 1);
    define('PHPCF_MAX_SNIFF_LINES', 15);
    define('PHPCF_FLAG_DEBUG',       1);
    define('PHPCF_FLAG_QUIET',       2);
    define('PHPCF_FLAG_NO_MESSAGES', 4);

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
    define('PHPCF_EX_DO_NOT_TOUCH_ANYTHING',  100);
    define('PHPCF_EX_CHECK_NL_STRONG',        101);
    define('PHPCF_EX_DELETE_SPACES_STRONG',   102);
    define('PHPCF_EX_SHRINK_SPACES_STRONG',   103);
    define('PHPCF_EX_SHRINK_NLS_STRONG',      104);
    define('PHPCF_EX_SHRINK_NLS_2',           105);  // shrink to "\n\n"
    define('PHPCF_EX_SHRINK_NLS',             106);  // shrink to "\n"
    define('PHPCF_EX_CHECK_NL',               107);  // accept 1 or 2 "\n"
    define('PHPCF_EX_DELETE_SPACES',          108);  // convert to ""
    define('PHPCF_EX_SHRINK_SPACES',          109);  // convert to " "
    define('PHPCF_EX_NL_OR_SPACE',            110);  // accept either "\n" or " " as whitespace
    define('PHPCF_EX_SPACE_IF_NLS',           111);  // insert a space instead of a sequence of newlines

    define('PHPCF_EX_INCREASE_INDENT',        200);
    define('PHPCF_EX_DECREASE_INDENT',        201);

    // constant is used to determine whether or not need to split expression to several lines
    define('PHPCF_LONG_EXPRESSION_LENGTH', 120);

    // custom token definitions are required for 'phpcf.so'
    define('T_STRING_CONTENTS',            10000);
    define('T_HEREDOC_CONTENTS',           10001);
    define('T_SINGLE_LINE_COMMENT',        10002);
    define('T_SINGLE_LINE_COMMENT_ALONE',  10003);
    define('T_ANONFUNC',                   10004);
    define('T_ANONFUNC_LONG',              10005);
    define('T_WHITESPACE_ALIGNED',         10006);
    define('T_FUNCTION_NAME',              10007);
    define('T_TERNARY_GLUED',              10008);
    define('T_EMPTY_BODY_OPEN',            10009);
    define('T_ARRAY_SHORT',                10010);
    define('T_ARRAY_SHORT_ML',             10011);
    define('T_ARRAY_HINT',                 10012);
    define('T_FROM',                       10013);
    // needed, since used in hook
    if (!defined('T_YIELD_FROM')) {
        define('T_YIELD_FROM', 10014);
    }
    // needed, since used in hook
    if (!defined('T_FN')) {
        define('T_FN', 10015);
    }
}
