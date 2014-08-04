<?php

// simple
$a=[];

// next line
$b=
    [];

// no spaces
$c=[1,2,3];

// extra spaces
$d=[   1  ,   2  ,   3];

// dereferenced call
$e = [ 1, 2  , 3  ][2];

// passed as param in function
$f = functionCall([   1,2 ,3 ] , [ 4  ]);

// simply somewhere
[1,2,  3345]   ;

// nested
[ [1,2   ,3] ,[4,5,6]   ];

if (true) {
    [123,456];
}
[345];

array('a', [234, 345345, 8837673, 789], 'b');

array('a', [func()?['b']:['c']]);