<?php
$something = $User ? ($em['id'] == $User->getField('manager_id') ? 'selected' : '') : '';

$a = $b ?: $c;
// leave compatibility with old behaviour as well
$a = $b ? : $c;
