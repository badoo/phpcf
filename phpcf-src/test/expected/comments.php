<?php
// forbidden comment starting with '#'
// single-line comment with indentation level 0
echo 'Hello world 1!';

echo 'Hello world 2!';  // single-line comment after a statement with indentation level 0

if ($hello) { // another single-line comment with indent level 0 and spaces after it 
    // single-line comment with indent level > 0
//    echo 'Hello world 3!'; // this line must be preserved as-is

    do_something();

    do_something_other(); // single-line comment with indent level > 0 after statement

    echo 'I am weasel';
}
