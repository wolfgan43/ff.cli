<?php
namespace ff\cli;

use Exception;
use ff\libs\Kernel;
use ff\libs\security\UUID;
use ff\libs\storage\FilemanagerFs;
use ff\libs\storage\FilemanagerWeb;
use ReflectionException;

/**
 * Class setup
 * @package ff\cli
 */
class Installer extends Kernel implements Constant
{
    private const NAMESPACE         = "ff\libs";
    private const VERSION           = "1.0";
    private const CONFIG            = [
        "'appid'",
        "'appname'",

        "'mysqlhost'",
        "'mysqldbname'",
        "'mysqluser'",
        "'mysqlsecret'",

        "'mongohost'",
        "'mongodbname'",
        "'mongouser'",
        "'mongosecret'",

        "'smtpuser'",
        "'smtpsecret'",
        "'fromemail'",
        "'fromname'",
        "'debugemail'",

        "'twiliosmssid'",
        "'twiliosmstoken'",
        "'twiliosmsfrom'",
    ];

    private $disk_path              = null;
    private $webServerUid           = null;
    private $respirce_disk_path     = null;

    public static function setup()
    {
        Cache::clear();

        $installer = new static();

        App::setup($installer->dirStruct("app"), $installer->disk_path, $installer->webServerUid);
        Cache::setup($installer->dirStruct("cache"), $installer->disk_path, $installer->webServerUid);

        $installer->indexClasses();

        $installer->makeConfig([]);
        $installer->makeIndex();
        $installer->makeHtaccess();
    }

    /**
     * @throws ReflectionException
     */
    public static function dumpautoload() : void
    {
        $installer = new static();

        Cache::clear();

        $installer->indexClasses();
    }

    public static function clearcache() : void
    {
        Cache::clear();
    }

    public function logo()
    {
        echo "
    ____  __          ______                          ______                                             __  
   / __ \/ /_  ____  / ____/___  _________ ___  _____/ ____/________ _____ ___  ___ _      ______  _____/ /__
  / /_/ / __ \/ __ \/ /_  / __ \/ ___/ __ `__ \/ ___/ /_  / ___/ __ `/ __ `__ \/ _ \ | /| / / __ \/ ___/ //_/
 / ____/ / / / /_/ / __/ / /_/ / /  / / / / / (__  ) __/ / /  / /_/ / / / / / /  __/ |/ |/ / /_/ / /  / ,<   
/_/   /_/ /_/ .___/_/    \____/_/  /_/ /_/ /_/____/_/   /_/   \__,_/_/ /_/ /_/\___/|__/|__/\____/_/  /_/|_|  
           /_/                                                                                      v " . self::VERSION . "\n\n";
    }

    public function __construct()
    {
        parent::__construct(Config::class);

        $this->disk_path            = $this::$Environment::DISK_PATH;
        $this->respirce_disk_path   = dirname(__DIR__);
        if (PHP_OS == "Linux") {
            $web_server_user = @shell_exec("ps aux | egrep '([a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx)' | awk '{ print $1}' | uniq | tail -1");
            if ($web_server_user) {
                $this->webServerUid = (int)shell_exec("id -u " . $web_server_user);
            }
        }
    }

    public function helper()
    {
        echo "
    ____  __          ______                          ______                                             __  
   / __ \/ /_  ____  / ____/___  _________ ___  _____/ ____/________ _____ ___  ___ _      ______  _____/ /__
  / /_/ / __ \/ __ \/ /_  / __ \/ ___/ __ `__ \/ ___/ /_  / ___/ __ `/ __ `__ \/ _ \ | /| / / __ \/ ___/ //_/
 / ____/ / / / /_/ / __/ / /_/ / /  / / / / / (__  ) __/ / /  / /_/ / / / / / /  __/ |/ |/ / /_/ / /  / ,<   
/_/   /_/ /_/ .___/_/    \____/_/  /_/ /_/ /_/____/_/   /_/   \__,_/_/ /_/ /_/\___/|__/|__/\____/_/  /_/|_|  
           /_/                                                   ____           __        ____               
                                                                /  _/___  _____/ /_____ _/ / /__  _____      
  Usage: Installer.php <arg> [-h | --help]                      / // __ \/ ___/ __/ __ `/ / / _ \/ ___/      
   -h, --help : Display Helper                                _/ // / / (__  ) /_/ /_/ / / /  __/ /          
   <arg>:                                                    /___/_/ /_/____/\__/\__,_/_/_/\___/_/  v " . self::VERSION . "         
        setup:          Init app
        dumpautoload:   Refresh classes dipencence                                                                                                        
        clearcache:     clear cache
        ";
    }

    /**
     * @throws ReflectionException
     */
    private function indexClasses() : void
    {
        $classFinder = new ClassFinder($this::$Environment::DISK_PATH);
        $libsClasses = $classFinder->getClassesByNamespace(self::NAMESPACE); // retrives all classes for ff\libs namespace

        // obtain classes that implements Configurable interface
        $files_configurable = $classFinder->filterByInterface($libsClasses, self::NAMESPACE . "\Configurable");

        // obtain classes that implements Configurable Dumpable
        $files_dumpable = $classFinder->filterByInterface($libsClasses, self::NAMESPACE . "\Dumpable");

        // open Config.php in ff\libs\
        $fileConfig = $this::$Environment::FRAMEWORK_DISK_PATH . "/src/Config.php";
        $content = file_get_contents($fileConfig);

        // replace (if exists) $class_configurable array in Config.php
        $content = preg_replace(
            '/(?<=private static \$class_configurable)(?s)(.*?)(?=;)/m',
            " = " . $this->arrayToStringPhp($files_configurable),
            $content
        );

        // replace (if exists) $class_configurable array in Config.php
        $content = preg_replace(
            '/(?<=private static \$class_dumpable)(?s)(.*?)(?=;)/m',
            " = " . $this->arrayToStringPhp($files_dumpable),
            $content
        );

        // save Config.php
        file_put_contents($fileConfig, $content);
    }

    /**
     * @param array $arr
     * @return string
     */
    private function arrayToStringPhp(array $arr) : string
    {
        $base = "array(\n";
        $keys = array_keys($arr);

        $content = "";
        $i = 0;
        foreach ($keys as $key) {
            $content .= "\t\t\"$key\"" . " => \"" . $arr[$key] . "\",\n";
            $i++;
        }

        if (strlen($content) >= 2) {
            $content = substr($content, 0, -2);
        }
        $base .= $content;
        $base .= "\n\t)";

        return $base;
    }

    /**
     *
     */
    private function makeIndex()
    {
        $index_file = $this->disk_path . DIRECTORY_SEPARATOR . "index.php";
        if (!FilemanagerFs::copy($this->respirce_disk_path . self::RESOURCE_PATH . DIRECTORY_SEPARATOR . "index.tpl", $index_file)) {
            echo "Error: " . $index_file . " unable to write!\n";
        }
    }

    /**
     * @param array $config
     * @throws \ff\libs\Exception
     */
    private function makeConfig(array $config)
    {
        if (file_exists($this->disk_path . "/config.php")) {
            return;
        }

        // Reading App NAME
        $cArr               = FilemanagerWeb::fileGetContentsJson($this->disk_path . DIRECTORY_SEPARATOR . "composer.json");
        $appName            = str_replace("/", "_", $cArr->name);

        $config["appid"]    = UUID::v4();
        $config["appname"]  = $appName;

        $content = str_replace(
            array_keys($config),
            array_values($config),
            FilemanagerWeb::fileGetContents($this->respirce_disk_path . self::RESOURCE_PATH . DIRECTORY_SEPARATOR . "config.tpl")
        );

        FilemanagerFs::filePutContents($this->disk_path . "/config.php", str_replace(self::CONFIG, "null", $content));
    }

    /**
     * @throws Exception
     */
    private function makeHtaccess()
    {
        $htaccess_disk_path = $this->disk_path . DIRECTORY_SEPARATOR . ".htaccess";
        FilemanagerFs::copy($this->respirce_disk_path . self::HTACCESS_PATH . DIRECTORY_SEPARATOR . ".htaccess", $htaccess_disk_path);
        $htaccessManager = new Htaccess($htaccess_disk_path);
        $htaccessManager->setup();
    }
}
