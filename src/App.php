<?php
namespace phpformsframework\cli;

use phpformsframework\libs\Kernel;
use phpformsframework\libs\storage\FilemanagerFs;

/**
 * Class AppBuilder
 * @package phpformsframework\cli
 */
class App implements Constant
{
    protected const COMPONENT = "app";

    public static function setup(array $structs, string $disk_path, int $web_server_uid)
    {
        $app = new static($disk_path, $web_server_uid);
        $app->makeDir($structs);
        $app->makeSafeDir(static::COMPONENT);
        $app->makeConfig();
        $app->makeWelcomePage();
    }


    private $disk_path              = null;
    private $resource_disk_path     = null;
    private $disk_owner             = null;
    private $disk_group             = null;

    public function __construct(string $disk_path, int $web_server_uid)
    {
        $this->disk_path            = $disk_path;
        $this->resource_disk_path   = dirname(__DIR__);

        $this->disk_owner           = (empty($web_server_uid) ? fileowner($this->disk_path) : $web_server_uid);
        $this->disk_group           = filegroup($this->disk_path);
    }

    public function makeDir(array $structs)
    {
        foreach ($structs as $key => $struct) {
            if(!empty($struct["virtual"])) {
                continue;
            }

            FilemanagerFs::makeDir($struct["path"], $this->disk_path, $this->chmod($struct["writable"] ?? false));

            chown($this->disk_path . $struct["path"], $this->disk_owner);
            chgrp($this->disk_path . $struct["path"], $this->disk_group);

            $this->makeSafeDir($key);
        }
    }

    public function makeSafeDir(string $key)
    {
        if(!file_exists($this->resource_disk_path . self::HTACCESS_PATH . DIRECTORY_SEPARATOR . ".htaccess_" . $key)) {
            return;
        }

        $htaccess_path = $this->disk_path . DIRECTORY_SEPARATOR . $key;
        if (is_dir($htaccess_path)) {
            $htaccess_file = $htaccess_path . DIRECTORY_SEPARATOR . ".htaccess";
            if (!FilemanagerFs::copy($this->resource_disk_path . self::HTACCESS_PATH . DIRECTORY_SEPARATOR . ".htaccess_" . $key, $htaccess_file)) {
                echo "Skipped: " . $this->disk_path . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . ".htaccess" . " unable to write!\n";
            }
        } else {
            echo "Error: " . $htaccess_path . " doesn't exists!\n";
        }
    }

    public function makeConfig()
    {
        $config_path = $this->disk_path . DIRECTORY_SEPARATOR . static::COMPONENT . DIRECTORY_SEPARATOR . "conf";
        if (is_dir($config_path)) {
            $config_file = $config_path . DIRECTORY_SEPARATOR . "config.xml";
            if(!file_exists($config_file) && !FilemanagerFs::copy($this->resource_disk_path . self::RESOURCE_PATH . DIRECTORY_SEPARATOR . "config.tpl", $config_file)) {
                echo "Error: " . $config_file . " unable to write!\n";
            }
        } else {
            echo "Error: " . $config_path . " doesn't exists!\n";
        }
    }

    public function makeWelcomePage()
    {
        $public_path = $this->disk_path . DIRECTORY_SEPARATOR . static::COMPONENT . DIRECTORY_SEPARATOR . "public";
        if (is_dir($public_path)) {
            $public_file = $public_path . DIRECTORY_SEPARATOR . "index.php";
            if(!file_exists($public_file) && !FilemanagerFs::copy($this->resource_disk_path . self::RESOURCE_PATH . DIRECTORY_SEPARATOR . "public.tpl", $public_file)) {
                echo "Error: " . $public_file . " unable to write!\n";
            }
        } else {
            echo "Error: " . $public_path . " doesn't exists!\n";
        }
    }

    private function chmod(bool $isWritable = false) : string
    {
        return ($isWritable
            ? "0775"
            : "0755"
        );
    }
}