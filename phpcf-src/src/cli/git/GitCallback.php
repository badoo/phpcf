<?php
/**
 * Base action for git integration
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli\Git;

class GitCallback
{
    /**
     * @var string
     */
    private $branch;

    public function __invoke()
    {
        return $this->handleInternal(func_get_arg(0), func_get_arg(1));
    }

    /**
     * @param \Phpcf\Cli\Ctx $Ctx
     * @param \Phpcf\Options $Options
     * @throws \RuntimeException
     * @return null
     */
    protected function handleInternal(\Phpcf\Cli\Ctx $Ctx, \Phpcf\Options $Options)
    {
        if (!$Options->isQuiet()) {
            fwrite(STDERR, "Note: you need to do 'git fetch' before using any *-git commands to update origin/* branches." . PHP_EOL);
        }

        $this->branchDetect();

        if (!$Ctx->getFiles()) {
            $files = [];

            $commits_arg = Helper::commitsArg($this->getBranch());

            $code = Helper::exec("log --pretty='format:%H' $commits_arg", $out, $err);
            if ($code) {
                throw new \RuntimeException("Failed to get commits: {$err}");
            }

            $commits = str_replace(PHP_EOL, ' ', trim($out));

            if ($commits) {
                $code = Helper::exec(
                    'show --pretty="format:" --name-only ' . $commits . '  -- | sort -u',
                    $files,
                    $err
                );
                if ($code) {
                    throw new \RuntimeException("Failed to get commits: {$err}");
                }
                $files = explode(PHP_EOL, trim($files));
            }

            $code = Helper::exec(
                'diff --name-only -- ',
                $diff,
                $err
            );
            $diff = explode(PHP_EOL, trim($diff));
            if ($code) {
                throw new \RuntimeException("Failed to get diff: {$err}");
            }

            $code = Helper::exec(
                'diff --cached --name-only -- ',
                $diff_cached,
                $retval
            );

            if ($code) {
                throw new \RuntimeException("Failed to get diff: {$err}");
            }

            $diff_cached = explode(PHP_EOL, trim($diff_cached));

            $files = array_unique(array_merge($files, $diff, $diff_cached));

            foreach ($files as $k => $f) {
                if (!file_exists($f) || !in_array(pathinfo($f, PATHINFO_EXTENSION), ["php", "phtml", "inc"])) {
                    unset($files[$k]);
                }
            }

            $file_statuses = Helper::getFileStatus($files);
            foreach ($files as $file) {
                $code = Helper::exec('diff -- ' . escapeshellarg($file), $out, $err);
                if ($code) {
                    throw new \RuntimeException("Failed to get diff for file {$file}: $err");
                }
                $commits_arg = Helper::commitsArg(null);
                $lines = Helper::changedLines($file, $commits_arg, $file_statuses[$file]);
                $lines = implode(',', $lines);

                $Ctx->removeFile($file);
                if ($lines) {
                    $file .= ':' . $lines;
                }

                if ($lines || ($file_statuses[$file] !== 'M' && $file_statuses[$file] !== Helper::INVALID_STATUS)) {
                    $Ctx->addFile($file);
                }
            }
        } else {
            $files = $Ctx->getFiles();
            $file_statuses = Helper::getFileStatus($files);
            foreach ($files as $file) {
                if (!file_exists($file)) {
                    throw new \RuntimeException("File '{$file}' does not exists");
                }
                $code = Helper::exec('diff -- ' . escapeshellarg($file), $out, $err);
                if ($code) {
                    throw new \RuntimeException("Git integration failed on file {$file}: $err");
                }
                $commits_arg = Helper::commitsArg(null);
                $lines = Helper::changedLines($file, $commits_arg, $file_statuses[$file]);

                $Ctx->removeFile($file);

                if ($lines || ($file_statuses[$file] !== 'M' && $file_statuses[$file] !== Helper::INVALID_STATUS)) {
                    $Ctx->addFile($file . ($lines ? ':' . implode(',', $lines) : ''));
                }
            }
        }
    }

    /**
     * 
     */
    private function branchDetect()
    {
        $this->branch = Helper::currentBranch();
    }

    private function getBranch()
    {
        return $this->branch;
    }
}
