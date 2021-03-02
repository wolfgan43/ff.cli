<?php
namespace phpformsframework\cli;

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
    private static $basepath;

    private static $classExclusions = [
        "\phpformsframework\libs\security\widgets\Recover",
        "\phpformsframework\libs\tpl\adapters\ViewSmarty"
    ];

    /**
     * ClassFinder constructor.
     * @param string $basepath
     */
    public function __construct(string $basepath)
    {
        self::$composer = null;
        self::$classes  = [];

        self::$basepath = $basepath;
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
        $allClasses = array_diff($allClasses, self::$classExclusions);

        if ($interface) {
            $filteredResult = array();
            $descendants = array();

            foreach ($allClasses as $class) {
                $classImpl = new ReflectionClass($class);

                $implCollection = (array)$classImpl->getInterfaceNames();

                if (in_array($interface, $implCollection)) {
                    $tmp = $classImpl;
                    while ($tmp = $tmp->getParentClass()) {
                        if ($tmp) {
                            if (in_array($interface, $tmp->getInterfaceNames())) {
                                if (!in_array($tmp->getName(), $descendants)) {
                                    $descendants[] = $tmp->getName();
                                }
                            }
                        }
                    }

                    $classId = strtolower($classImpl->getShortName());
                    $filteredResult[$classId] = $classImpl->getName();
                }
            }

            $filteredResult = array_diff($filteredResult, $descendants);

            return $filteredResult;
        }

        return [];
    }
}
