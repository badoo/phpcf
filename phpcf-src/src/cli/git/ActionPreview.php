<?php
/**
 * Check only changed lines
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli\Git;

class ActionPreview extends \Phpcf\Cli\ActionCheck
{
    protected $description = "show diff for changes to be pushed";
}
