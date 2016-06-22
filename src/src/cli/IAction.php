<?php
/**
 * Interface for cli actions of PHPCF
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli;

interface IAction
{
    /**
     * Description, what this action do
     * @return string
     */
    public function getDescription();

    /**
     * @param \Phpcf\Formatter $Formatter
     * @param Ctx $Ctx
     * @return int exit code
     */
    public function handle(\Phpcf\Formatter $Formatter, Ctx $Ctx);
}
