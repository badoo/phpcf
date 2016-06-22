<?php

    $a =
        "<table cellpadding=4 align=center>\n" .
        (
        $this->CHECK_NAME == 'CreditPackNew' || $this->CHECK_NAME == 'CreditPackRenew' ?
            "<td><b>CreditPacks by operator (SMS)</td>".
            "<td><b>CreditPacks by country</td>"                                   :
            "<td><b>SPP by country</td>"
        ) .
        "</tr>\n";
