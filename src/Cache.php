<?php
namespace phpformsframework\cli;

use phpformsframework\libs\cache\Buffer;

class Cache extends App
{
    protected const COMPONENT = "cache";

    public static function setup(array $structs, string $disk_path, int $web_server_uid)
    {
        $app = new static($disk_path, $web_server_uid);
        $app->makeDir($structs);
        $app->makeSafeDir(static::COMPONENT);
    }

    public static function clear()
    {
        Buffer::clear();
    }
}