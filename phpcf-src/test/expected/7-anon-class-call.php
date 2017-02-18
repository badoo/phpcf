<?php
/**
 * Support for anon class, passed as argument
 */

call(
    new class extends \DateTime {
        protected $property = 10;
        function hello()
        {
            return "hello";
        }
    },
    new class extends \DateTime {
        protected $property = 10;
        function hello()
        {
            return "hello";
        }
    }
);

class Test
{
    public function test($Logger)
    {
        $Logger->call(
            new class extends \DateTime {
                protected $property = 10;
                function hello()
                {
                    return "hello";
                }
            }
        );

        $Logger->call(
            new class extends \DateTime {
                protected $property = 10;
                function hello()
                {
                    return new class extends \DateTime {
                        function wowow() : int
                        {
                            return 42;
                        }
                    };
                }
            }
        );
    }
}
