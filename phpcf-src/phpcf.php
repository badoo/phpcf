<?php
/**
 * CLI interface for PHPCF
 * @maintainer Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */

require_once __DIR__ . '/src/init.php';

try {
    exit(Phpcf\Cli\Handler::create()->handle($argv));
} catch (\Exception $Error) {
    fwrite(STDERR, $Error->getMessage() . PHP_EOL);
    exit(1);
}
