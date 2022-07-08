<?php
namespace ff\cli;

use ff\libs\storage\FilemanagerFs;
use Composer\Script\Event;
use Exception;

/**
 * Class HtaccessManager
 * @package ff\cli
 */
class Htaccess
{
    private const RESOURCE_PATH     = DIRECTORY_SEPARATOR . "resources";
    private const HTACCESS_PATH     = self::RESOURCE_PATH . DIRECTORY_SEPARATOR . "htaccess";

    private $htaccess;
    private $availables;
    private $required;
    private $htaccess_path;

    private $use_www;
    private $use_https;
    private $resource_path;

    private const TEMPLATE_PREFIX = "htaccess-";

    /**
     * @param string $htaccess_disk_path
     * @throws Exception
     */
    public function __construct(string $htaccess_disk_path)
    {
        if (file_exists($htaccess_disk_path)) {
            $this->resource_path = dirname(__DIR__) . self::HTACCESS_PATH;
            $this->htaccess_path = $htaccess_disk_path;
            $this->htaccess = FilemanagerFs::fileGetContents($htaccess_disk_path);

            $this->availables = $this->templates($this->resource_path);
            $this->required = preg_grep('/### REQUIRE/i', file($htaccess_disk_path));
        } else {
            throw new Exception('Invalid htaccess path', 500);
        }
    }

    /**
     * @return string
     */
    public function read() : string
    {
        return $this->htaccess;
    }

    /**
     * @return self
     */
    public function enableHttpRedirect() : self
    {
        $pattern = '/(?<=### BEGIN http-redirect)(?s)(.*?)(?=### END http-redirect)/m';
        $replace = <<<RULES
\nRewriteCond %{HTTP_HOST} !^www\.
RewriteRule ^(.*)$ http://www.%{HTTP_HOST}/$1 [R=301,L]\n
RULES;
        $this->htaccess = preg_replace($pattern, $replace, $this->htaccess);
        $this->use_www = true;
        return $this;
    }

    /**
     * @return self
     */
    public function disableHttpRedirect() : self
    {
        $pattern = '/(?<=### BEGIN http-redirect)(?s)(.*?)(?=### END http-redirect)/m';
        $replace = <<<RULES
\n
RULES;
        $this->htaccess = preg_replace($pattern, $replace, $this->htaccess);
        $this->use_www = false;
        return $this;
    }

    /**
     * @return self
     */
    public function enableHttpsRedirect() : self
    {
        $pattern = '/(?<=### BEGIN https-redirect)(?s)(.*?)(?=### END https-redirect)/m';
        $prefix = $this->use_www ? "www." : "";
        $replace = <<<RULES
\nRewriteCond %{HTTPS} =off [OR]
RewriteCond %{HTTP_HOST} !^www\. [OR]
RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\ /index\.(html|php)
RewriteCond %{HTTP_HOST} ^(www\.)?(.+)$
RewriteRule ^(index\.(html|php))|(.*)$ https://{$prefix}%2/$3 [R=301,L]\n
RULES;
        $this->htaccess = preg_replace($pattern, $replace, $this->htaccess);
        $this->use_https = true;
        return $this;
    }

    /**
     * @return self
     */
    public function disableHttpsRedirect() : self
    {
        $pattern = '/(?<=### BEGIN https-redirect)(?s)(.*?)(?=### END https-redirect)/m';
        $replace = <<<RULES
\n
RULES;
        $this->htaccess = preg_replace($pattern, $replace, $this->htaccess);
        $this->use_https = true;
        return $this;
    }

    /**
     * @param string $referer
     * @return self
     * @throws Exception
     */
    public function addToBanlist(string $referer) : self
    {
        $pattern = '/(?<=### BEGIN security-banlist)(?s)(.*?)(?=### END security-banlist)/m';
        $base_banlist = trim($this->getMatch($pattern, $this->htaccess));

        if (false !== strpos($base_banlist, "RewriteCond %{HTTP_REFERER} {$referer} [NC")) {
            return $this;
        }

        if (empty($base_banlist)) {
            $base_banlist = FilemanagerFs::fileGetContents($this->resource_path . "/htaccess-security-banlist");
            $banlist_item = "\n\tRewriteCond %{HTTP_REFERER} {$referer} [NC]\n\t";
            $subpattern = '/(?<=### BEGIN banlist-items)(?s)(.*?)(?=### END banlist-items)/m';
            $base_banlist = preg_replace($subpattern, $banlist_item, $base_banlist);
        } else {
            $subpattern = '/(?<=### BEGIN banlist-items)(?s)(.*?)(?=### END banlist-items)/m';
            $current_list = $this->getMatch($subpattern, $base_banlist);
            if (false !== $index = strrpos($current_list, "[NC]")) {
                $current_list = substr_replace($current_list, "[NC,OR]", $index, 4);
                $current_list .= "RewriteCond %{HTTP_REFERER} {$referer} [NC]\n\t";
                $subpattern = '/(?<=### BEGIN banlist-items)(?s)(.*?)(?=### END banlist-items)/m';
                $base_banlist = "\n".preg_replace($subpattern, $current_list, $base_banlist)."\n";
            }
        }
        $this->htaccess = preg_replace($pattern, $base_banlist, $this->htaccess);
        return $this;
    }

    /**
     * @param string $referer
     * @return self
     */
    public function removeFromBanlist(string $referer) : self
    {
        $subpattern = '/(?<=### BEGIN banlist-items)(?s)(.*?)(?=### END banlist-items)/m';
        $current_list = $this->getMatch($subpattern, $this->htaccess);

        $item_or = "RewriteCond %{HTTP_REFERER} {$referer} [NC,OR]";
        $item_end = "RewriteCond %{HTTP_REFERER} {$referer} [NC]";
        if (false !== $index = strpos($current_list, $item_or)) {
            $current_list = substr_replace($current_list, "", $index, strlen($item_or));
        } elseif (false !== $index = strpos($current_list, $item_end)) {
            $current_list = substr_replace($current_list, "", $index, strlen($item_end));
            if (false !== $index = strrpos($current_list, "[NC,OR]")) {
                $current_list = substr_replace($current_list, "[NC]", $index, 8);
                $this->htaccess = preg_replace($subpattern, $current_list, $this->htaccess);
            }
        }
        return $this;
    }

    /**
     * @param string $domain
     * @return self
     */
    public function addCorsRule(string $domain) : self
    {
        $pattern = '/(?<=### BEGIN security-cors)(?s)(.*?)(?=### END security-cors)/m';
        $cors_string = trim($this->getMatch($pattern, $this->htaccess));
        if (empty($cors_string)) {
            $cors_string = "\nHeader add Access-Control-Allow-Origin '".$domain."'\n";
            $this->htaccess = preg_replace($pattern, $cors_string, $this->htaccess);
        } else {
            $subpattern = '/(?<=\nHeader add Access-Control-Allow-Origin \')(?s)(.*?)(?=\'\n)/m';
            $domains = explode("|", trim($this->getMatch($subpattern, $this->htaccess)));
            if (!in_array($domain, $domains)) {
                $domains[] = $domain;
                $cors_string = "\nHeader add Access-Control-Allow-Origin '".implode("|", $domains)."'\n";
                $this->htaccess = preg_replace($pattern, $cors_string, $this->htaccess);
            }
        }
        return $this;
    }

    /**
     * @param string $domain
     * @return self
     */
    public function removeCorsRule(string $domain) : self
    {
        $pattern = '/(?<=### BEGIN security-cors)(?s)(.*?)(?=### END security-cors)/m';
        $cors_string = trim($this->getMatch($pattern, $this->htaccess));
        if (!empty($cors_string)) {
            $subpattern = '/(?<=\nHeader add Access-Control-Allow-Origin \')(?s)(.*?)(?=\'\n)/m';
            $domains = explode("|", trim($this->getMatch($subpattern, $this->htaccess)));
            if (false !== $key = array_search($domain, $domains)) {
                unset($domains[$key]);
                if (sizeof($domains) > 0) {
                    $cors_string = "\nHeader add Access-Control-Allow-Origin '" . implode("|", $domains) . "'\n";
                } else {
                    $cors_string = "\n";
                }
                $this->htaccess = preg_replace($pattern, $cors_string, $this->htaccess);
            }
        }
        return $this;
    }

    /**
     * @param string $domain
     * @return self
     * @throws Exception
     */
    public function addXFrameOptions(string $domain) : self
    {
        $pattern = '/(?<=### BEGIN x-frame-options)(?s)(.*?)(?=### END x-frame-options)/m';
        $base_xframe = trim($this->getMatch($pattern, $this->htaccess));

        if (empty($base_xframe)) {
            $base_xframe = "\n".trim(FilemanagerFs::fileGetContents($this->resource_path . "/htaccess-x-frame-options"))."\n";
        }

        $subpattern = '/(?<=### BEGIN x-frame-items)(?s)(.*?)(?=### END x-frame-items)/m';
        $optionlist = trim($this->getMatch($subpattern, $base_xframe));

        $xframe_item = "\n\tHeader always set X-Frame-Options {$domain}";
        if (empty($optionlist)) {
            $optionlist .= $xframe_item;
        } else {
            if (false !== strpos($optionlist, "Header always set X-Frame-Options {$domain}")) {
                return $this;
            }
            $optionlist .= $xframe_item;
        }

        $base_xframe = preg_replace($subpattern, "\n\t".$optionlist."\n\t", $base_xframe);
        $this->htaccess = preg_replace($pattern, "\n".$base_xframe."\n", $this->htaccess);
        return $this;
    }

    /**
     * @param string $domain
     * @return self
     */
    public function removeXFrameOptions(string $domain) : self
    {
        $pattern = '/(?<=### BEGIN x-frame-options)(?s)(.*?)(?=### END x-frame-options)/m';
        $base_xframe = trim($this->getMatch($pattern, $this->htaccess));

        $subpattern = '/(?<=### BEGIN x-frame-items)(?s)(.*?)(?=### END x-frame-items)/m';
        $optionlist = trim($this->getMatch($subpattern, $base_xframe));

        if (empty($optionlist)) {
            return $this;
        }

        $optionlist = str_replace("\tHeader always set X-Frame-Options {$domain}\n", "", $optionlist);
        return $this;
    }

    /**
     * @param string $region_name
     * @return self
     * @throws Exception
     */
    public function enableRegion(string $region_name) : self
    {
        if (in_array($region_name, array_keys($this->availables))) {
            $this->disableRegion($region_name);
            $pattern = '/(?<=### BEGIN ' . $region_name . ')(?s)(.*?)(?=### END ' . $region_name . ')/m';
            $replace = "\n".trim(FilemanagerFs::fileGetContents($this->resource_path . "/" . $region_name))."\n";
            $this->htaccess = preg_replace($pattern, str_replace(
                ['$0', '$1', '$2', '$3', '$4', '$5', '$6', '$7', '$8', '$9'],
                ['\$0', '\$1', '\$2', '\$3', '\$4', '\$5', '\$6', '\$7', '\$8', '\$9'],
                $replace
            ), $this->htaccess);
            $this->save();
        } else {
            echo "skipped: " . $region_name . " template not found!\n";
        }
        return $this;
    }

    /**
     * @param string $region_name
     * @return self
     */
    public function disableRegion(string $region_name) : self
    {
        if (in_array($region_name, array_keys($this->availables))) {
            $pattern = '/(?<=### BEGIN ' . $region_name . ')(?s)(.*?)(?=### END ' . $region_name . ')/m';
            $replace = "\n";
            $this->htaccess = preg_replace($pattern, $replace, $this->htaccess);
            $this->save();
        } else {
            echo "skipped: " . $region_name . " template not found!\n";
        }
        return $this;
    }

    public function save() : void
    {
        FilemanagerFs::filePutContents($this->htaccess_path, $this->htaccess);
    }

    // TEST METHODS
    public static function testRequire() : void
    {
        if (!file_exists('.htaccess')) {
            copy('.htaccess_sample', '.htaccess');
        } else {
            echo ".htaccess already exists\n";
        }
        $manager = new HtaccessManager('.htaccess');
    }

    public static function testEnable(Eve $event) : void
    {
        $region_name = $event->getArguments()[0];
        if (!file_exists('.htaccess')) {
            copy('.htaccess_sample', '.htaccess');
        } else {
            echo ".htaccess already exists\n";
        }
        $manager = new HtaccessManager('.htaccess');
        echo "enable " . $region_name . "\n";
        $manager->enableRegion($region_name);
    }

    public static function testDisable(Event $event) : void
    {
        $region_name = $event->getArguments()[0];
        if (!file_exists('.htaccess')) {
            copy('.htaccess_sample', '.htaccess');
        } else {
            echo ".htaccess already exists";
        }
        $manager = new HtaccessManager('.htaccess');
        echo "disable " . $region_name;
        $manager->disableRegion($region_name);
    }

    public function setup():void
    {
        echo "Resolving required regions for htaccess...\n";
        foreach ($this->required as $item) {
            $key = str_replace("### REQUIRE: ", "", $item);
            $key = str_replace("\n", "", $key);
            $this->enableRegion(trim(str_replace('### REQUIRE:', '', $item)));
        }
        echo "Completed!\n";
    }

    /**
     * @param string $pattern
     * @param string $content
     * @return string
     */
    private function getMatch(string $pattern, string $content) : string
    {
        preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE, 0);
        if (sizeof($matches) > 0) {
            $search = $matches[0][0];
            $index = $matches[0][1];
            return substr($content, $index, strlen($search));
        }
        return $content;
    }

    /**
     * @param string $folder
     * @return array
     */
    private function templates(string $folder) : array
    {
        $files = glob($folder.'/'. self::TEMPLATE_PREFIX .'*');
        $templates = array();
        foreach ($files as $file) {
            $comps = explode('/', $file);
            $templates[$comps[sizeof($comps)-1]] = $file;
        }
        return $templates;
    }
}
