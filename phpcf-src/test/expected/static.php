<?php

class Something
{
    static $protected = false;

    static function helloworld()
    {
        static $var;
        echo 'Hello world!';

        if (true) {
            static::helloworld();
            static::$protected = true;
        }
    }

    static public function wrongKeywordsOrder() {}

    static protected function wrongKeywordsOrder1() {}

    static private function wrongKeywordsOrder2() {}

    public static function rightKeywordsOrder() {}

    protected static function rightKeywordsOrder1() {}

    private static function rightKeywordsOrder2() {}
}

Something::helloworld();
