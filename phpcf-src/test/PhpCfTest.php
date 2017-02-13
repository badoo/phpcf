<?php
/**
 * Formatter test suite
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
require_once __DIR__ . '/../src/init.php';

class PHPCFTest extends PHPUnit_Framework_TestCase
{
    private static $files;

    private $Formatter;

    const ORIGINAL = "/original/";
    const EXPECTED = "/expected/";

    public static function setUpBeforeClass()
    {
        self::getFiles(); // to prevent time-waste on concrete test
    }

    protected function setUp()
    {
        chdir(dirname(dirname(__DIR__)));
    }

    public function toString()
    {
        $class = new ReflectionClass($this);

        $buffer = sprintf(
            '%s::%s',
            $class->name,
            $this->getName(false)
        );

        return $buffer . $this->getDataSetAsString(true);
    }

    /**
     * Test the PHP implementation behaviour
     * @dataProvider providerFiles
     */
    public function testFiles($file)
    {
        $this->execTest($this->getFormatter(), $file);
    }

    private function execTest(\Phpcf\Formatter $Formatter, $file)
    {
        $source_file = $file;

        if (strpos($file, ':') !== false) {
            list($file,) = explode(':', $file);
        }

        $expected_content = null;
        $expected = __DIR__ . self::EXPECTED . $file;
        if (!file_exists($expected)) {
            $this->fail("File {$expected} does not exists");
        } else if (false === ($expected_content = file_get_contents($expected))) {
            $this->fail("Failed to get content of {$expected}");
        }

        $original = __DIR__ . self::ORIGINAL . $source_file;

        $FormatResult = $Formatter->formatFile($original);
        $this->assertNull($FormatResult->getError()); // we expect no error

        $this->assertEquals($expected_content, $FormatResult->getContent());
    }

    /**
     * @return \Phpcf\Formatter
     */
    private function getFormatter()
    {
        if (!$this->Formatter) {
            $this->Formatter = $this->createFormatter();
        }

        return $this->Formatter;
    }

    private function createFormatter()
    {
        $Options = new \Phpcf\Options();
        $Options->toggleSniff(true);
        return new \Phpcf\Formatter($Options);
    }

    /**
     * Data provider with files from test/original directory
     * @return array
     */
    public function providerFiles()
    {
        return self::getFiles();
    }

    /**
     * @return array
     */
    private static function getFiles()
    {
        if (null === self::$files) {
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
            self::$files = $files;
        }

        return self::$files;
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
        
        $Formatter = $this->createFormatter();
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
