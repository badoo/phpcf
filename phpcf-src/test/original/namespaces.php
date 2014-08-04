<?php
namespace Billing\Pmdb;

require_once __DIR__ . '/../../../../test.helper.php';

use  \Badoo_Billing_TestCase;

class FlowTest extends Badoo_Billing_TestCase
{
    public function test_getTableName()
    {
        $func = function() use ($a) {
            echo 'Hello world!';
        };
        $this->assertEquals(BILLING_DB_PMDB . '.flow', Flow::getTableName());
    }
}
