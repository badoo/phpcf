<?php
/**
 * Null coalesce multiline
 */

$a =
    "<table cellpadding=4 align=center>\n" .
    (
    $this->CHECK_NAME == 'CreditPackNew' || $this->CHECK_NAME == 'CreditPackRenew' ??
        "<td><b>CreditPacks by operator (SMS)</td>"
    ) .
    "</tr>\n";