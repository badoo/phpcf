<?php
/**
 * Context for CLI call
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli;

class Ctx
{
    private $files = [];

    /**
     * @param string $file
     */
    public function addFile($file)
    {
        $this->files[$file] = true;
    }

    /**
     * @return string[]
     */
    public function getFiles()
    {
        return array_keys($this->files);
    }

    public function removeFile($file)
    {
        unset($this->files[$file]);
    }
}
