<?php

namespace Acme\User;

// Typed properties
class User
{
    public  int  $id;

    protected
        static
            string
                $name;

    private    \Acme\User\User   $UserA;
        private
            \Acme\User\User
            $UserB;
}

class Multiple
{
    public
        int
            $a = 1,
            $b = 2;

    public
        \Acme\User\User
            $c = 1,
            $d = 2;
}

class FnMultiline
{
    public function test($problems) : array
    {
        return array_map( fn (\Deploy\AutoMerge\IdentifiableProblem $Problem) => $Problem->getId(),
            $this->ProblemFilter->getOutdatedProblems($problems)
        );
    }
}

class NullableProperties
{
    private ?\Acme\User\User $UserA;
    private ? \Acme\User\User  $UserB;
    private static ?\Acme\User\User $UserStatic;
    private ? \Acme\User\User  $UserC;
}

// Arrow functions
$factor = 10;
$nums = array_map( fn ($n) => $n * pow(2, $factor),  fn ($n) => $n * pow(2, $factor));

// Null coalescing assignment operator
$array['key']  ??=  pow(2, 3);

// Unpacking inside arrays
$parts = ['apple', 'pear'];

$fruits = [
    'banana', ... convert($parts),
    'watermelon',
];
