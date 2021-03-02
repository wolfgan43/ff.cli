#!/usr/bin/php
<?php
if (php_sapi_name() !== 'cli') {
    exit;
}

require dirname(dirname(dirname(__DIR__))) . '/autoload.php';

$app = new \phpformsframework\cli\Installer();

switch ($argv[1]) {
    case "setup":
        $app->setup();
        break;
    case "dumpautoload":
        $app->logo();
        echo "  ClearCache...       Done!\n";
        echo "  Indexing Classes... ";
        $app->dumpautoload();
        echo "Done!\n";
        break;
    case "clearcache":
        $app->logo();
        echo "  ClearCache...       ";
        $app->clearCache();
        echo "Done!\n";
        break;
    default:
        $app->helper();
}
