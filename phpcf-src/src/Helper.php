<?php

namespace Phpcf;

class Helper
{
    /**
     * @param $library
     * @throws \RuntimeException
     */
    public static function loadExtension($library)
    {
        if (!extension_loaded($library)) {
            if (!is_callable('dl')) {
                throw new \RuntimeException("Failed to load extension '{$library}', 'dl' disabled");
            }

            if (!@dl($library . '.' . PHP_SHLIB_SUFFIX)) {
                throw new \RuntimeException("Failed to load extension '{$library}': 'dl' failed");
            }
        }
    }

    /**
     * Is terminal interactive
     * @return bool
     */
    public static function isAtty()
    {
        try {
            self::loadExtension('posix');
            return is_callable('posix_isatty') ? posix_isatty(STDOUT) : false;
        } catch (\Exception $Error) {
            return false;
        }
    }

    /**
     * Analog of getopt
     * @param string $name
     * @param string $short_name
     * @param bool $is_flag
     * @param array $options
     * @param array $argv
     * @param $k
     * @param $v
     */
    public static function parseArg($name, $short_name, $is_flag, array &$options, array &$argv, $k, $v)
    {
        if ($is_flag) {
            if ($v === '--' . $name || $short_name && $v === '-' . $short_name) {
                unset($argv[$k]);
                $options[$name] = 1;
            }
        } else {
            $arg = '--' . $name . '=';
            if (substr($v, 0, strlen($arg)) == $arg) {
                $opt_str = (string)substr($argv[$k], strlen($arg));
                $options[$name] = $opt_str;
                unset($argv[$k]);
            }
        }
    }
}
