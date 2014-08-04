<?php
abstract class Something extends OtherThing
{
    const C1 = "Something", C2 = "Other things";
    private static $hello = "Hello";
    abstract function someThing();
}

class SomethingTwo extends OtherThing
{
    const
        C1 = "Something",
        C2 = "Another thing";

    private static
        $helloo = "Hello",
        $world = array("world");
}

class EmptySomething extends OtherThing {}
