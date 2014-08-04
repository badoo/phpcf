<?php
/**
 * Test for formatting new instance member access
 * (new Class)->test();
 */

(new Test)->run();

// this comment needed to prevent space-alignment hack (eliminate line mutual dependency)
(new Test($a, $b))->run();
