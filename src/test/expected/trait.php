<?php
trait SampleTrait
{
    /**
     * @var int this is trait variable
     */
    protected $trait_variable = 1;

    public function test($a, $b)
    {
        $b = 10; // test
    }
}

trait TraitTwo {}

trait TraitThree
{
    public function test($a, $b)
    {
        $b = 20;
    }
}

class Impl
{
    use SampleTrait, TraitTwo;
}

class ImplConflict
{
    use SampleTrait, TraitThree {}
}

class ImplResolved
{
    use SampleTrait, TraitThree
    {
        TraitThree::test insteadof SampleTrait;
        SampleTrait::test as testNew;
        test as private test;
    }
}
