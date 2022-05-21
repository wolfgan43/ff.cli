<?php
namespace ff\cli;

use ReflectionClass;
use ReflectionException;

/**
 * Class ClassFinder
 * @package hcore\classes
 */
class ClassFinder
{
    private const COMPOSER_PATH = '/vendor/autoload.php';

    private static $composer = null;
    private static $classes  = [];

    private static $classExclusions = [
        "\ff\libs\security\widgets\Recover",
        "\ff\libs\tpl\adapters\ViewSmarty"
    ];

    /**
     * ClassFinder constructor.
     * @param string $basepath
     */
    public function __construct(string $basepath)
    {
        self::$composer = null;
        self::$classes  = [];

        self::$composer = require($basepath . self::COMPOSER_PATH);

        if (false === empty(self::$composer)) {
            self::$classes  = array_keys(self::$composer->getClassMap());
        }
    }

    /**
     * @return array
     */
    public function getClasses() : array
    {
        $allClasses = [];

        if (false === empty(self::$classes)) {
            foreach (self::$classes as $class) {
                $allClasses[] = '\\' . $class;
            }
        }

        return $allClasses;
    }

    /**
     * @param string $namespace
     * @return array
     */
    public function getClassesByNamespace(string $namespace) : array
    {
        if (0 !== strpos($namespace, '\\')) {
            $namespace = '\\' . $namespace;
        }

        $termUpper = strtoupper($namespace);
        return array_filter($this->getClasses(), function ($class) use ($termUpper) {
            $className = strtoupper($class);
            if (
                0 === strpos($className, $termUpper) and
                false === strpos($className, strtoupper('Abstract')) and
                false === strpos($className, strtoupper('Interface'))
            ) {
                return $class;
            }
            return false;
        });
    }

    /**
     * @param array $allClasses
     * @param string $interface
     * @return array
     * @throws ReflectionException
     */
    public function filterByInterface(array $allClasses, string $interface) : array
    {
        ini_set('memory_limit', '296M');
        $allClasses = array_diff($allClasses, self::$classExclusions);
        if ($interface) {
            $filteredResult = array();
            foreach ($allClasses as $class) {
                $this->filterResult($class, $interface, $filteredResult);
            }

            return $filteredResult;
        }

        return [];
    }

    /**
     * @param string $class
     * @param string $interface
     * @param array $filteredResult
     * @throws ReflectionException
     */
    private function filterResult(string $class, string $interface, array &$filteredResult)
    {
        $classImpl = new ReflectionClass($class);

        $implCollection = (array)$classImpl->getInterfaceNames();
        if (in_array($interface, $implCollection)) {
            $classId = strtolower($classImpl->getShortName());
            $filteredResult[$classId] = $classImpl->getName();
        }
        unset($classImpl, $implCollection);
    }
}
