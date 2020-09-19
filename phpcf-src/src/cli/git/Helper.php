<?php
/**
 * Collector for messages and debug
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli\Git;

abstract class Helper
{
    const DEBUG = false;
    const INVALID_STATUS = -1;

    /**
     * @throws \RuntimeException
     * @return string
     */
    public static function currentBranch()
    {
        $code = self::exec('symbolic-ref -q HEAD', $out, $err);
        if ($code != 0) {
            throw new \RuntimeException("Branch detect failed: " . $err);
        }
        $ref_full = trim($out);
        if (!$ref_full) {
            throw new \RuntimeException("Branch detect failed");
        }
        $branches = explode('/', $ref_full); // refs/heads/branch_name
        return end($branches);
    }

    /**
     * @param array $files
     * @throws \RuntimeException
     * @return array
     */
    public static function getFileStatus(array $files)
    {
        $code = self::exec("status --untracked-files=all --porcelain --ignored=traditional " . implode(" ", array_map('escapeshellarg', $files)), $out, $err);
        if ($code) {
            throw new \RuntimeException("Failed to get status: {$err}");
        }
        $retval = array_fill_keys($files, self::INVALID_STATUS);
        foreach (explode("\n", trim($out)) as $line) {
            if (empty($line)) {
                continue;
            }
            list($status, $file) = explode(" ", trim($line), 2);
            $retval[trim($file)] = $status;
        }
        return $retval;
    }

    /**
     * Get list of lines that were changed in specified commits range.
     * By default uncommited changes are returned
     *
     * @param $filename
     * @param string $commits
     * @param mixed $git_file_status
     * @throws \RuntimeException
     * @return array|bool
     */
    public static function changedLines($filename, $commits = '^HEAD', $git_file_status = self::INVALID_STATUS)
    {
        $blame_check = (strpos($git_file_status, 'A') === false && $git_file_status !== '?' && $git_file_status !== '??');

        if (!$blame_check) {
            return range(1, count(file($filename)) + 1);
        }

        $cmd = 'git blame -sl ' . $commits . ' -- ' . escapeshellarg($filename) . ' 2>&1';
        if (self::DEBUG) {
            echo $cmd, PHP_EOL;
        }
        // exec here, because proc_open causes infinite wait for input
        exec($cmd, $out, $code);
        $out = implode(PHP_EOL, $out);
        if ($code) {
            if (strpos($out, 'no such path') !== false) {
                return range(1, count(file($filename)) + 1);
            }
            throw new \RuntimeException("Blame fail: " . $out);
        }

        $out = explode(PHP_EOL, trim($out));
        $line_numbers = [];
        foreach ($out as $ln) {
            if ($ln[0] == '^') {
                continue;
            }
            if (!preg_match('/(\\d+)\\)/s', $ln, $matches)) {
                continue;
            }
            $line_numbers[] = intval($matches[1]);
        }

        return $line_numbers;
    }

    /**
     * get commits argument for 'git log' that will exclude origin/$branch and origin/master
     * @param $branch
     * @throws \RuntimeException
     * @return mixed
     */
    public static function commitsArg($branch)
    {
        static $args = [];
        static $origin_exists = null;
        if (!isset($args[$branch])) {
            // origin presence check
            if (null === $origin_exists) {
                $code = self::exec("remote", $out, $err);
                if ($code) {
                    throw new \RuntimeException("Failed to determine origin existence: " . $err);
                }
                $out = trim($out);
                $origin_exists = !empty($out);
            }
            $remote_branch = $origin_exists ? "^origin/$branch" : "";
            
            // check if origin/$branch exists
            $retval = self::exec("show-ref --verify -q refs/remotes/origin/$branch", $out, $err);
            $origin_branch = $retval ? '' : $remote_branch;
            $cmd = "$branch $origin_branch";

            if ($origin_exists) {
                // compare with remote master
                $cmd .= " ^origin/master ";
            } else {
                // compare with local master
                $cmd .= " ^master ";
            }

            $cmd .= " --no-merges";

            $args[$branch] = $cmd;
        }

        return $args[$branch];
    }

    /**
     * @param $cmd
     * @param $out
     * @param $err
     * @throws \RuntimeException
     * @return int exit code
     */
    public static function exec($cmd, &$out, &$err)
    {
        $cmd = "git {$cmd} 2>&1";
        if (self::DEBUG) {
            echo $cmd, PHP_EOL;
        }

        $err = exec($cmd, $out, $code);
        $out = implode(PHP_EOL, $out);

        return $code;
    }
}
