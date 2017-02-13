<?php
/**
 * Formatter facade
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf;

class Formatter
{
    /**
     * @var Options
     */
    private $Options;

    /**
     * @var IFormatter
     */
    private $Implementation;

    const IMPL_CLASS = "\\Phpcf\\Impl\\Formatter";

    public function __construct(Options $Options)
    {
        $this->Options = $Options;
        $this->initImplementation();
    }

    private function initImplementation()
    {
        \Phpcf\Helper::loadExtension('tokenizer');

        $fsm_context_rules = require(__DIR__ . "/../styles/default/context-rules.php");
        $controls = require(__DIR__ . "/../styles/default/formatting-rules.php");

        $custom_style_dir = $this->Options->getCustomStyle();
        $custom_formatter_class = null;

        if (!empty($custom_style_dir)) {
            // check, which dir is given - relative, or absolute
            if (strpos($custom_style_dir, DIRECTORY_SEPARATOR) === false) {
                $custom_style_dir = __DIR__ . '/../styles/' . $custom_style_dir . '/';
            }

            if (!is_dir($custom_style_dir)) {
                throw new \RuntimeException("Given custom style dir '{$custom_style_dir}' is not a dir");
            }
            if (file_exists($file = $custom_style_dir . DIRECTORY_SEPARATOR . "context-rules.php")) {
                require $file;
            }

            if (file_exists($file = $custom_style_dir . DIRECTORY_SEPARATOR . "formatting-rules.php")) {
                require $file;
            }

            if (file_exists($file = $custom_style_dir . DIRECTORY_SEPARATOR . "phpcf-class.php")) {
                require_once $file;
                $class_name = ucfirst(pathinfo($custom_style_dir, PATHINFO_FILENAME));
                $custom_formatter_class = "\\Phpcf\\Impl\\PHPCF_$class_name";
                if (!class_exists($custom_formatter_class, false)) {
                    throw new \InvalidArgumentException("Custom formatter class '{$custom_formatter_class}' not found in '{$file}'");
                } else if (!in_array(ltrim(self::IMPL_CLASS, '\\'), class_parents($custom_formatter_class, true))) {
                    throw new \InvalidArgumentException("Class '{$custom_formatter_class}' must be instanceof '" . self::IMPL_CLASS . "'");
                }
            }
        }

        $formatter_class = $custom_formatter_class ? $custom_formatter_class : self::IMPL_CLASS;
        $this->Implementation = new $formatter_class($fsm_context_rules, $controls, new ExecStat());

        if ($this->Options->isCyrillicFilterEnabled()) {
            $this->Implementation->setStringFilter(new \Phpcf\Filter\StringAscii(new \Phpcf\Filter\StringAsciiMapCyr()));
        }
    }

    /**
     * Format content of file
     * @param string $file
     * @return FormattingResult
     */
    public function formatFile($file)
    {
        $Result = new FormattingResult($file);
        $start = microtime(true);

        try {
            $lines = [];
            if (strpos($file, ':') !== false) {
                // Support for absolute paths on Windows
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && preg_match('/^[A-Z]\\:/s', $file)) {
                    $parts = explode(':', $file);
                    if (count($parts) == 3) {
                        $file = $parts[0] . ":" . $parts[1];
                        $lines = $parts[2];
                        $lines = $this->parseLines($lines);
                    }
                } else {
                    list($file, $lines) = explode(':', $file);
                    $lines = $this->parseLines($lines);
                }
            }

            $Result = new FormattingResult($file);
            $start = microtime(true);

            if (!file_exists($file)) {
                throw new \RuntimeException("File '{$file}' does not exists");
            }
            if (false === ($source = file_get_contents($file))) {
                throw new \RuntimeException("Failed to read content of '{$file}'");
            }
            $FormatResult = $this->format($source, $lines);
            $Result->setContent($FormatResult->getContent());
            $Result->setWasFormatted($FormatResult->wasFormatted());
            $Result->setError($FormatResult->getError());
            $Result->setIssues($FormatResult->getIssues());
        } catch (\Exception $Error) {
            $Result->setError($Error);
        }
        $Result->setTimeExec(microtime(true) - $start);
        return $Result;
    }

    /**
     * Parses line specification into hash of lines to format
     * @param string $lines
     * @return array
     */
    private function parseLines($lines)
    {
        $retval = [];
        if (strlen($lines)) {
            foreach (explode(',', $lines) as $ln) {
                if (preg_match('/^([0-9]+)-([0-9]+)$/s', $ln, $matches)) {
                    for ($i = $matches[1]; $i <= $matches[2]; $i++) {
                        $retval[$i] = true;
                    }
                } else {
                    $retval[$ln] = true;
                }
            }
        }
        return $retval;
    }

    /**
     * Writes content to temp file and return it's name
     * @param string $content
     * @return string filename
     * @throws \RuntimeException
     */
    private function saveToTemp($content)
    {
        $tmp = tempnam(sys_get_temp_dir(), "phpcf_temp");
        if (false === $tmp) {
            throw new \RuntimeException("Failed to create temp file");
        }
        if (!file_put_contents($tmp, $content)) {
            unlink($tmp);
            throw new \RuntimeException("Failed to write to temp file {$tmp}");
        }
        return $tmp;
    }

    /**
     * @param string $from
     * @param string $to
     * @throws \Exception
     * @return array
     */
    private function getChangedLines($from, $to)
    {
        $file_from = $this->saveToTemp($from);
        $file_to = $this->saveToTemp($to);
        try {
            $lines = $this->calcChangedLinesExec($file_from, $file_to);
            unlink($file_from);
            unlink($file_to);
            return $lines;
        } catch (\Exception $Error) {
            unlink($file_from);
            unlink($file_to);
            throw $Error;
        }
    }

    /**
     * Changed lines calculation, using external diff tool
     * @param $from
     * @param $to
     * @return array
     * @throws \RuntimeException
     */
    private function calcChangedLinesExec($from, $to)
    {
        $lines = [];

        exec("diff " . escapeshellarg($from) . " " . escapeshellarg($to), $out, $retval);
        if ($retval > 1) {
            throw new \RuntimeException("Failed to exec diff");
        }

        foreach ($out as $row) {
            if (!isset($row[0])) {
                continue;
            }
            if (!is_numeric($row[0])) {
                continue;
            }
            $new = false;
            foreach (['a', 'c', 'd'] as $sep) {
                $parts = explode($sep, $row);
                if (count($parts) != 2) {
                    continue;
                }
                $new = $parts[1];
                if (strpos($new, ",") !== false) {
                    list($start, $end) = explode(",", $new);
                    for ($i = $start; $i <= $end; $i++) $lines[$i] = true;
                } else {
                    $lines[$new] = true;
                }
            }
            if ($new === false) {
                continue;
            }
        }

        return $lines;
    }

    /**
     * Format given content
     * @param string $source code to format
     * @param array $lines lines to format
     * @return \Phpcf\FormattingResult
     */
    public function format($source, array $lines = [])
    {
        $this->Implementation->setMaxLineLength($this->Options->getMaxLineLength());
        $this->Implementation->setTabSequence($this->Options->getTabSequence());
        $this->Implementation->setSniffMessages($this->Options->sniffMessages());
        $this->Implementation->setDebugEnabled($this->Options->isDebug());

        $Result = new FormattingResult("");
        $start = microtime(true);
        $old_result = $source;

        $had_lines = !empty($lines);
        try {
            $max_loops = 5;
            $loop = 1;
            do {
                $result = $this->Implementation->format($old_result, $lines);
                if ($result === $old_result) {
                    break;
                }

                // collect data only from the first issue
                if (1 == $loop) {
                    $Result->setIssues($this->Implementation->getIssues());
                    $this->Implementation->setDebugEnabled(false);
                }

                if ($had_lines) {
                    break;
                }

                if ($lines) {
                    $lines = $this->getChangedLines($old_result, $result);
                }

                $old_result = $result;
            } while ($loop++ < $max_loops);

            $Result->setContent($result);
            $Result->setWasFormatted($result !== $source);
        } catch (\Exception $Error) {
            $Result->setError($Error);
        }
        $Result->setTimeExec(microtime(true) - $start);
        return $Result;
    }

    /**
     * @param Filter\StringAscii $StringFilter
     */
    public function setStringFilter(\Phpcf\Filter\StringAscii $StringFilter)
    {
        $this->Implementation->setStringFilter($StringFilter);
    }

    /**
     * @return Options
     */
    public function getOptions()
    {
        return $this->Options;
    }
}
