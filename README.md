phpcf
=====

The formatter was created to basically only modify whitespaces, for example line feed, tabs, spaces, etc. It means that phpcf does not replace similar utilities like PHP Code Sniffer (phpcs) or PHP Coding Standards Fixer (http://cs.sensiolabs.org) by Fabien Potencier. The utility supplements others and does all the "dirty work" with whitespace characters. It is worth noting that our utility respects the initial file formatting and only changes whitespace characters that do not follow the chosen ruleset (some utilities remove all whitespace first and reconstruct file from scratch, which is not necessarily what people want).

Our utility is extensible and supports arbitrary style sets. You can define your own formatting style pretty easily to replace Badoo formatting standard that is a bit different from PSR.

Below is a little usage example.
 - "phpcf apply <filename>" formats the specified file
 - "phpcf check <filename>" checks that formatting is correct and returns non-zero exit code when file is not formatted properly

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

Our utility is also capable of formatting part of file. To do so, you need to specify line number ranges after a colon:

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

It is worth noting again that phpcf is designed to only change whitespace characters and to do the most simple tasks such as:
 - replacing "<?" with "<?php"
 - removing extra closing tag from the end of file
 - change cyrillic letters to english in function names
 - expressions that are aligned manually using spaces are not touched

The formatter works as a finite state machine with rules that are set by user instead of using hard-coded replacements. We supply the default config that follows Badoo formatting rules. So if you would like to get automatic "var" -> "public" replacement or similar we suggest looking at PHP-CS-Fixer. The latter does not really touch whitespace characters but it can do much more sophisticated replacements than our utility.
