<?php
/**
 * CLI for phpcf
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli;

class Handler
{
    /**
     * Registry of actions
     * @var IAction[]
     */
    private $registry = [];

    private $callbacks = [];

    private $flags = [
        ['debug',       'd', true,  'turn on debug mode (A LOT will be printed)'],
        ['quiet',       'q', true,  'do not print status messages'],
        ['summary',     's', true,  'show only number of formatting error messages (if any)'],
        ['emacs',       'e', true,  'emacs-style output (each line of output contains filename, line, col)'],
        ['style',       '',  false, 'use specified <style> in addition to default'],
    ];

    /**
     * Register new action
     * @param string $alias
     * @param IAction $Action
     * @param null|callable $callback
     * @throws \InvalidArgumentException on alias collision
     */
    public function addAction($alias, IAction $Action, $callback = null)
    {
        if (isset($this->registry[$alias])) {
            throw new \InvalidArgumentException("Alias '{$alias}' is already bound to " . get_class($this->registry[$alias]));
        }
        $this->registry[$alias] = $Action;
        if (is_callable($callback)) {
            $this->callbacks[$alias] = $callback;
        }
    }

    /**
     * Print utility usage instructions to STDERR
     */
    private function printUsage()
    {
        $rows = [
            "Usage: phpcf [<flags>] <command> <filename> [ ... <filename>]",
            "",
        ];

        if (!empty($this->flags)) {
            $rows[] = "Flags:";
            foreach ($this->flags as $flag) {
                $rows[] = $this->getFlagDescription($flag);
            }
            $rows[] = "";
        }

        if (!empty($this->registry)) {
            $rows[] = "Commands:";
            $max_length = max(array_map('strlen', array_keys($this->registry)));
            foreach ($this->registry as $alias => $Action) {
                $rows[] = "    " . str_pad($alias, $max_length + 4) . ' ' . $Action->getDescription();
            }
        }
        $rows[] = "";
        foreach ($rows as $row) {
            fwrite(STDERR, $row . PHP_EOL);
        }
    }

    /**
     * @param array $argv
     * @return int exit code
     */
    public function handle(array $argv)
    {
        try {
            $Options = $this->parseParams($argv);
            if (empty($argv[1])) {
                throw new \InvalidArgumentException("No action specified");
            }
            $action = $argv[1];
            if (!isset($this->registry[$action])) {
                throw new \InvalidArgumentException("Unknown action '{$action}'");
            }
            $Handler = $this->registry[$action];
            $Formatter = $this->createFormatter($Options);
            $Ctx = new Ctx();

            if (count($argv) > 2) {
                for ($i = 2; $i < count($argv); $i++) {
                    $Ctx->addFile($argv[$i]);
                }
            }

            $callback_invoked = false;
            if (isset($this->callbacks[$action])) {
                call_user_func_array($this->callbacks[$action], [$Ctx, $Options]);
                $callback_invoked = true;
            }

            if (!$Ctx->getFiles() && !$callback_invoked) {
                throw new \RuntimeException("No files to execute");
            }

            return $Handler->handle($Formatter, $Ctx);
        } catch (\Exception $Error) {
            fwrite(STDERR, 'Error: ' . $Error->getMessage() . PHP_EOL);
            $this->printUsage();
            return 1;
        }
    }

    /**
     * @param array $argv
     * @return \Phpcf\Options
     */
    private function parseParams(array &$argv)
    {
        $options = [];
        foreach ($argv as $k => $v) {
            \Phpcf\Helper::parseArg('debug',       'd', 1, $options, $argv, $k, $v);
            \Phpcf\Helper::parseArg('quiet',       'q', 1, $options, $argv, $k, $v);
            \Phpcf\Helper::parseArg('summary',     's', 1, $options, $argv, $k, $v);
            \Phpcf\Helper::parseArg('emacs',       'e', 1, $options, $argv, $k, $v);
            \Phpcf\Helper::parseArg('style',       0,   0, $options, $argv, $k, $v);
        }

        $Retval = new \Phpcf\Options();

        if (!empty($options['debug'])) {
            $Retval->setDebug(true);
        }
        if (!empty($options['quiet'])) {
            $Retval->setQuiet(true);
        }
        if (!empty($options['summary'])) {
            $Retval->setSummary(true);
        }
        if (!empty($options['emacs'])) {
            $Retval->setEmacsStyle(true);
        }
        if (!empty($options['style'])) {
            $Retval->setCustomStyle($options['style']);
        }

        $argv = array_values($argv);
        return $Retval;
    }

    /**
     * @param \Phpcf\Options $Options
     * @return \Phpcf\Formatter
     */
    private function createFormatter(\Phpcf\Options $Options)
    {
        $Formatter =  new \Phpcf\Formatter($Options);
        return $Formatter;
    }

    /**
     * @param array $flag
     * @return string
     */
    private function getFlagDescription(array $flag)
    {
        $row = '    ';
        if ($flag[1]) {
            $row .= '-' . $flag[1] . ', ';
        }
        $row .= '--' . $flag[0];
        if (!$flag[2]) {
            $row .= '=<' . $flag[0] . '>';
        }
        $row = str_pad($row, 25); // @todo make it good
        $row .= $flag[3];
        return $row;
    }

    /**
     * Factory method for default handler creation
     * @return Handler
     */
    public static function create()
    {
        $Retval = new self();
        $Retval->addAction('apply',     new ActionApply());
        $Retval->addAction('check',     new ActionCheck());
        $Retval->addAction('preview',   ActionPreview::create());
        $Retval->addAction('stdin',     new ActionStdin());

        $GitCallback = new Git\GitCallback();
        $Retval->addAction('apply-git',     new Git\ActionApply(), $GitCallback);
        $Retval->addAction('check-git',     new Git\ActionCheck(), $GitCallback);
        $Retval->addAction('preview-git',   ActionPreview::create(), $GitCallback);

        return $Retval;
    }
}
