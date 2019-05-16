<?php

namespace Kodus\Cache\Test\Integration;

use function assert;
use Cache\IntegrationTests\SimpleCacheTest;
use Codeception\Util\FileSystem;
use function dirname;
use function file_exists;
use function is_writable;
use Kodus\Cache\FileCache;

class FileCacheIntegrationTest extends SimpleCacheTest
{
    const DEFAULT_EXPIRATION = 86400;
    const DIR_MODE = 0775;
    const FILE_MODE = 0664;

    public function createSimpleCache()
    {
        $path = dirname(__DIR__) . "/_output/cache";

        FileSystem::deleteDir($path);

        $cache = new FileCache($path, self::DEFAULT_EXPIRATION, self::DIR_MODE, self::FILE_MODE);

        assert(file_exists($path));

        assert(is_writable($path));

        return $cache;
    }
}
