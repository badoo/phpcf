phpcf
=====

Formatter was created in order to basically change the whitespace characters: line breaks, indentation, spaces around operators, etc. Thus, phpcf not replace any other similar utilities, such as the aforementioned PHP Code Sniffer and PHP Coding Standards Fixer (http://cs.sensiolabs.org) by Fabien Potentsera. It complements them, doing the "dirty work" for the correct placement of spaces and line breaks in the file. It is important to note that our utility allows for the formatting of the original file and change only those spaces that do not conform to the selected standard (unlike some other solutions that are first removed all whitespace tokens, and then start formatting).

The utility is extensible and supports an arbitrary set of styles. It can be quite easy to define your formatting style that will implement a different standard, different from ours (coding standard in our company is very close to the PSR).

Example (command "php apply <filename>" specified file formats, and "phpcf check <filename>" check formatting and returns a non-zero exit-code in case of unformatted fragments):

```
$ cat minifier.php
<?php
$tokens=token_get_all(file_get_contents($argv[1]));$contents='';foreach($tokens as $tok){if($tok[0]===T_WHITESPACE||$tok[0]===T_COMMENT)continue;if($tok[0]===T_AS||$tok[0]===T_ELSE)$contents.=' '.$tok[1].' '; else $contents.=is_array($tok)?$tok[1]:$tok;}echo$contents."\n";

$ phpcf apply minifier.php
minifier.php formatted successfully

$ cat minifier.php
<?php
$tokens = token_get_all(file_get_contents($argv[1]));
$contents = '';
foreach ($tokens as $tok) {
    if ($tok[0] === T_WHITESPACE || $tok[0] === T_COMMENT) continue;
    if ($tok[0] === T_AS || $tok[0] === T_ELSE) $contents .= ' ' . $tok[1] . ' ';
    else $contents .= is_array($tok) ? $tok[1] : $tok;
}
echo $contents . "\n";

$ phpcf check minifier.php; echo $?
minifier.php does not need formatting
0
```


In addition to formatting the whole file, our utility is also able to format the file. To do this, you need to specify the range of line numbers separated by a colon:

```
$ cat zebra.php
<?php
echo "White "."strip".PHP_EOL;
echo "Black "."strip".PHP_EOL; // not formatted
echo "Arse".PHP_EOL;

$ phpcf apply zebra.php:1-2,4
zebra.php formatted successfully

$ cat zebra.php
<?php
echo "White " . "strip" . PHP_EOL;
echo "Black "."strip".PHP_EOL; // not formatted
echo "Arse" . PHP_EOL;

$ phpcf check zebra.php
zebra.php issues:
        Expected one space before binary operators (= < > * . etc)   on line 3 column 14
        Expected one space after binary operators (= < > * . etc)   on line 3 column 15
        ...

$ echo $?
1
```

Even though there is a utility written in PHP, the file format of most runs in a split second. But we have a large repository of code and many, so we wrote an extension that, when connected, increases the productivity of a hundred times: all our repository 2 million lines formatted for 8 seconds on the "notebook» Core i7. To use the extension is required to collect it from the directory "ext /", set to include "enable_dl = On" in php.ini or register it as extension.

I would like to emphasize once again that the php cf above all whitespace changes and knows how to make a simple conversion of the code, for example, to replace a short opening tag on long or remove the last closing tag of the file. In addition, phpcf can automatically correct the Cyrillic alphabet in the names of functions in the English characters. Also not start moving expression aligned manually via gaps. This is because of the architecture - formatter operates as a state machine with rules set by the user, rather than as a set of "zahardkozhennyh" substitutions (formatter comes with a "default config" relevant to our formatting rules). Therefore, if you want to automatically replace "var" in the "public" or similar items, we recommend to pay attention to PHP-CS-Fixer - he pays little attention to whitespace (unlike phpcf), but is able to rewrite tokens.
