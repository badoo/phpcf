<?php
/**
 * Class loader for PHPCF
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf;

class ClassLoader
{
    const NS = "Phpcf";
    private $_fileExtension = '.php';
    private $path;
    private $_namespaceSeparator = '\\';

    /**
     * @var self
     */
    private static $instance;

    /**
     *
     */
    private function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * @return ClassLoader
     */
    public static function register()
    {
        if (null === self::$instance) {
            self::$instance = new self(__DIR__);
            self::$instance->registerAutoload();
        }
        return self::$instance;
    }

    /**
     * Installs this class loader on the SPL autoload stack.
     */
    public function registerAutoload()
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Uninstalls this class loader from the SPL autoloader stack.
     */
    public function unregisterAutoload()
    {
        spl_autoload_unregister([$this, 'loadClass']);
    }

    /**
     * Loads the given class or interface.
     *
     * @param string $className The name of the class to load.
     * @return void
     */
    public function loadClass($className)
    {
        if (self::NS . $this->_namespaceSeparator === substr($className, 0, strlen(self::NS . $this->_namespaceSeparator))) {
            $fileName = '';
            if (false !== ($lastNsPos = strripos($className, $this->_namespaceSeparator))) {
                $namespace = substr($className, 0, $lastNsPos);
                $className = substr($className, $lastNsPos + 1);
                $fileName = strtolower(str_replace($this->_namespaceSeparator, DIRECTORY_SEPARATOR, $namespace)) . DIRECTORY_SEPARATOR;
                $fileName = substr($fileName, strlen(self::NS . DIRECTORY_SEPARATOR));
            }
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . $this->_fileExtension;

            $target = $this->path . DIRECTORY_SEPARATOR . $fileName;
            if (file_exists($target)) {
                require $target;
            }
        }
    }
}
