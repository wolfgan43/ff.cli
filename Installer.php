#!/usr/bin/php
<?php
if (php_sapi_name() !== 'cli') {
    exit;
}

require dirname(dirname(__DIR__)) . '/autoload.php';

$installer = new \phpformsframework\cli\Installer();

switch ($argv[1]) {
    case "setup":
        $installer->setup();
        break;
    case "dumpautoload":
        $installer->logo();
        echo "  ClearCache...       Done!\n";
        echo "  Indexing Classes... ";
        $installer->dumpautoload();
        echo "Done!\n";
        break;
    case "clearcache":
        $installer->logo();
        echo "  ClearCache...       ";
        $installer->clearCache();
        echo "Done!\n";
        break;
    default:
        $installer->helper();
}
