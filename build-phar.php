<?php
/**
 * Generate standalone PHAR-archive
 * 
 * @category Misc
 * @package  Phpcf
 * @author   Sergey Aksenov <sergeax@gmail.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/badoo/phpcf
 */

$pharFile = 'build/phpcf.phar';
$pharName = basename($pharFile);

if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile, 0, $pharName);
$phar->setSignatureAlgorithm(Phar::SHA512);
$phar->startBuffering();

$phar->setStub(
    '<?php Phar::mapPhar();' .
    'include "phar://' . $pharName . '/phpcf.php";' .
    '__HALT_COMPILER(); ?>'
);

$phar->buildFromDirectory('phpcf-src/', '/^((?!\/test\/|\/example\/).)*$/');

$phar->compressFiles(Phar::GZ);
$phar->stopBuffering();
unset($phar);

echo "PHAR archive '{$pharFile}' has been generated";
exit(0);
