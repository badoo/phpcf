<?php
/**
 * Functional test runner
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
require_once __DIR__ . '/../src/init.php';

class PhpCfFunctional extends PHPUnit_Framework_TestCase
{
    const ORIGINAL = "/original/";
    const EXPECTED = "/expected/";

    private static $folder;
    private $folder_final;

    private $use_pure = null;

    public static function setUpBeforeClass()
    {
        while (!self::$folder) {
            $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid();
            if (!file_exists($temp)) {
                $mkdir = mkdir($temp, 0777);
                if (!$mkdir) {
                    throw new \RuntimeException("Failed to create temp dir '$temp'");
                }
                self::$folder = $temp;
            }
        }
    }

    public static function tearDownAfterClass()
    {
        if (self::$folder) {
            exec("rm -rf " . escapeshellarg(self::$folder));
        }
    }

    public static function delTree($dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file") && !is_link($dir)) ? self::delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    private function getExecPath()
    {
        $cmd = 'php ' . realpath(__DIR__ . '/../phpcf.php');
        if ($this->use_pure) {
            $cmd .= ' -p ';
        }
        return $cmd;
    }

    private function callFormatter($cmd)
    {
        $cmd = $this->getExecPath() . "  " . escapeshellarg($cmd);
        $proc = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

        if (!$proc) {
            throw new \RuntimeException("proc_open failed");
        }
        $code = null;
        while (true) {
            $status = proc_get_status($proc);
            if (!$status) {
                proc_terminate($proc);
                throw new \RuntimeException("Failed to get process status");
            }
            if (!$status['running']) {
                $out = stream_get_contents($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                $code = $status['exitcode'];
                break;
            }
        }

        if (null === $code) {
            throw new \RuntimeException("Failed to execute '{$cmd}' - unknown result");
        }

        return ['out' => $out, 'err' => $err, 'code' => $code];
    }

    protected function setUp()
    {
        $this->initRepo();
    }

    private function initRepo()
    {
        chdir(self::$folder);
        exec("rm -rf ./*");
        $cmd = "cp -R " . escapeshellarg(__DIR__ . '/functional') . " " . escapeshellarg(self::$folder . DIRECTORY_SEPARATOR) . ' 2>&1';
        $err = exec($cmd, $output, $code);
        if ($code) {
            $this->fail("Failed to prepare dir: $err");
        }

        $this->folder_final = self::$folder . '/functional/';
        $cmd = "cd " . escapeshellarg($this->folder_final) . ' && git init 2>&1 && git add -A && git commit -a -m "Initial" --no-edit';
        exec($cmd, $out, $code);
        if ($code != 0) {
            $this->fail("Initialization failed");
        }
        chdir($this->folder_final);
    }

    /**
     * Provider for implementation selection
     */
    public function providerImplementation()
    {
        return [
            [true], // use native-php  
            [false],  // use extension
        ];
    }

    /**
     * Test, that initial commit does not contain anything to format
     * @dataProvider providerImplementation 
     */
    public function testClear($impl)
    {
        $this->use_pure = $impl;
        $initial_result = $this->callFormatter("check-git");
        $this->assertEquals(0, $initial_result['code']);
        // no any ouput on empty directory
    }

    /**
     * Test apply of dirty file
     * @dataProvider providerImplementation
     */
    public function testDirty($impl)
    {
        $this->use_pure = $impl;
        copy(__DIR__ . '/functional_dirty/Test_dirty.php', $this->folder_final . '/Test.php'); // make file dirty
        $res = $this->callFormatter('check-git');
        $this->assertEquals(1, $res['code']); // has errors
        $this->assertStringMatchesFormat("Test.php issues:%a", $res['out']);
        $res = $this->callFormatter("apply-git");
        $this->assertEquals(0, $res['code']);
        $this->assertStringMatchesFormat("Test.php formatted successfully%a", $res['out']);
        $res = $this->callFormatter("check-git");
        $this->assertEquals(0, $res['code']); // all right
    }

    /**
     * 1.) Make commit in master with unformatted file
     * 2.) Change part of file
     * 3.) Run check-git, apply-git, check-git
     * 4.) Make sure, file is identical to expected (partially formatted)
     * @dataProvider providerImplementation
     */
    public function testPartialDirty($impl)
    {
        $this->use_pure = $impl;
        copy(__DIR__ . '/functional_dirty/Test_new_commit.php', $this->folder_final . '/Test.php'); // make file dirty
        $cmd = 'git commit -a -m "second commit" --no-edit 2>&1';
        $err = exec($cmd, $out, $code);
        if ($code) {
            $this->fail("Failed to create commit: $err");
        }

        $cmd = "git branch new_branch 2>&1 && git checkout new_branch 2>&1";
        exec($cmd, $out, $code);
        if ($code) {
            $this->fail("Failed to create new branch");
        }
        copy(__DIR__ . '/functional_dirty/Test_after_commit.php', $this->folder_final . '/Test.php'); // make file dirty
        
        // formatter see differences
        $res = $this->callFormatter('check-git');
        $this->assertEquals(1, $res['code']);
        $this->assertStringMatchesFormat('Test.php issues:%a', $res['out']);

        // apply only to changed
        $res = $this->callFormatter('apply-git');
        $this->assertEquals(0, $res['code']);
        $this->assertStringMatchesFormat('Test.php formatted successfully%a', $res['out']);
        
        // re-check
        $res = $this->callFormatter('check-git');
        $this->assertEquals(0, $res['code']);
        $this->assertStringMatchesFormat('Test.php does not need formatting%a', $res['out']);
        
        // check for content equality (partially formatted)
        $this->assertFileEquals(__DIR__ . '/functional_dirty/Test_new_branch_formatted.php', $this->folder_final . '/Test.php');
    }
}
