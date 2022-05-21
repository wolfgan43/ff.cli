<?php
namespace ff\cli;

interface Constant
{
    public const RESOURCE_PATH     = DIRECTORY_SEPARATOR . "resources";
    public const HTACCESS_PATH     = self::RESOURCE_PATH . DIRECTORY_SEPARATOR . "htaccess";
}