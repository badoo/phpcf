<?php
/**
 * Test for empty anon class declaration in any places
 */
$a = new class(10, 20, new \DateTime()) extends \Test\Me implements \I {
    const TEST = 10;
    protected $property = 10;
    public function hello()
    {
        echo "hello";
    }
};

function test()
{
    $a = new class(10, 20, new \DateTime()) extends \Test\Me implements \I {
        const TEST = 10;
        protected $property = 10;
        public function hello()
        {
            echo "hello";
        }
    };
}

class One
{
    function test()
    {
        $a = new class(10, 20, new \DateTime()) extends \Test\Me implements \I {
            const TEST = 10;
            protected $property = 10;
            public function hello()
            {
                echo "hello";
            }
        };
    }
}
