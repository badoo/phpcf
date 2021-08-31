<?php
/**
 * Formatter test suite
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
require_once __DIR__ . '/../src/init.php';

class PHPCFTest extends \PHPUnit\Framework\TestCase
{
    private $Formatters = [];
    private static $debug = false;

    const ORIGINAL = "/original/";
    const EXPECTED = "/expected/";

    public static function setUpBeforeClass() : void
    {
        $last = array_pop($_SERVER['argv']);
        self::$debug = ($last == '--debug');
    }

    protected function setUp() : void
    {
        chdir(dirname(dirname(__DIR__)));
    }

    /**
     * Data provider with files from test/original directory
     * @return array
     */
    public function providerFiles()
    {
        $files = [];
        $dh = opendir(__DIR__ . self::ORIGINAL);
        while ($file = readdir($dh)) {
            if ($file[0] == '.') {
                continue;
            }

            // support for major version change
            if (substr($file, 1, 1) == '-') {
                if (PHP_MAJOR_VERSION < substr($file, 0, 1)) {
                    continue;
                }
            }

            $files[$file] = [$file];
        }
        closedir($dh);
        return $files;
    }

    /**
     * Test the implementation behaviour
     *
     * @dataProvider providerFiles
     *
     * @param string $file
     */
    public function testFormatting($file)
    {
        $expected_content = null;
        $expected = __DIR__ . self::EXPECTED . $file;
        if (!file_exists($expected)) {
            $this->fail("File {$expected} does not exists");
        } else if (false === ($expected_content = file_get_contents($expected))) {
            $this->fail("Failed to get content of {$expected}");
        }

        $original = __DIR__ . self::ORIGINAL . $file;

        $Formatter = $this->getFormatter(false);
        $FormatResult = $Formatter->formatFile($original);
        $this->assertNull($FormatResult->getError()); // we expect no error

        // Debug
        if (self::$debug) {
            if ($expected_content !== $FormatResult->getContent()) {
                fwrite(STDERR, "Debug for file '{$file}':\n");
                $DebugFormatter = $this->getFormatter(true);
                $DebugFormatter->formatFile($original);
            }
        }

        $this->assertEquals($expected_content, $FormatResult->getContent());
    }

    /**
     * @param bool $debug
     * @return \Phpcf\Formatter
     */
    private function getFormatter($debug)
    {
        if (!isset($this->Formatters[(int)$debug])) {
            $Options = new \Phpcf\Options();
            $Options->toggleSniff(true);
            $Options->setDebug($debug);
            $this->Formatters[(int)$debug] = new \Phpcf\Formatter($Options);
        }

        return $this->Formatters[(int)$debug];
    }

    /**
     * Test, that issues ends with correct message
     */
    public function testColumnIssue()
    {
        /**
         * Map of lines => columns
         */
        $expectations = [
            2 => 3,
            3 => 10,
            4 => 11,
            5 => 13,
            6 => 12
        ];
        
        $Formatter = $this->getFormatter(false);
        $Result = $Formatter->formatFile(__DIR__ . self::ORIGINAL . 'columns.php');
        $issues = $Result->getIssues();
        $this->assertNotEmpty($issues);
        foreach ($expectations as $line => $column)
        {
            $message = current($issues);
            next($issues);
            $this->assertStringEndsWith("line $line column $column", $message);
        }
    }
}
