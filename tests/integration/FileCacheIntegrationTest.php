<?php

declare(strict_types=1);

namespace Kodus\Cache\Test\Integration;

use Cache\IntegrationTests\SimpleCacheTest;
use Codeception\Util\FileSystem;
use Kodus\Cache\FileCache;
use TypeError;

class FileCacheIntegrationTest extends SimpleCacheTest
{
    const DEFAULT_EXPIRATION = 86400;
    const DIR_MODE = 0775;
    const FILE_MODE = 0664;

    protected $skippedTests = [
        'testGetInvalidKeys' => 'New simple-cache v3 type hints break the test. FileCacheIntegrationTest contains updated versions.',
        'testGetMultipleInvalidKeys' => 'New simple-cache v3 type hints break the test. FileCacheIntegrationTest contains updated versions.',
        'testSetInvalidKeys' => 'New simple-cache v3 type hints break the test. FileCacheIntegrationTest contains updated versions.',
        'testHasInvalidKeys' => 'New simple-cache v3 type hints break the test. FileCacheIntegrationTest contains updated versions.',
        'testDeleteInvalidKeys' => 'New simple-cache v3 type hints break the test. FileCacheIntegrationTest contains updated versions.',
    ];

    public function createSimpleCache()
    {
        $path = dirname(__DIR__) . "/_output/cache";

        FileSystem::deleteDir($path);

        $cache = new FileCache($path, self::DEFAULT_EXPIRATION, self::DIR_MODE, self::FILE_MODE);

        assert(file_exists($path));

        assert(is_writable($path));

        return $cache;
    }

    /**
     * @dataProvider invalidStringKeys
     */
    public function testGetInvalidStringKeys($key)
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->cache->get($key);
    }

    /**
     * @dataProvider invalidNonStringKeys
     */
    public function testGetInvalidNonStringKeys($key)
    {
        $this->expectException(TypeError::class);
        $this->cache->get($key);
    }

    /**
     * @dataProvider invalidStringKeys
     */
    public function testGetMultipleInvalidStringKeys($key)
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->cache->getMultiple(['key1', $key, 'key2']);
    }

    /**
     * @dataProvider invalidNonStringKeys
     */
    public function testGetMultipleInvalidNonStringKeys($key)
    {
        $this->expectException(TypeError::class);
        $this->cache->getMultiple(['key1', $key, 'key2']);
    }

    public function testGetMultipleNoIterable()
    {
        $this->expectException(TypeError::class);
        $this->cache->getMultiple('key');
    }

    /**
     * @dataProvider invalidStringKeys
     */
    public function testSetInvalidStringKeys($key)
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->cache->set($key, 'foobar');
    }

    /**
     * @dataProvider invalidNonStringKeys
     */
    public function testSetInvalidNonStringKeys($key)
    {
        $this->expectException(TypeError::class);
        $this->cache->set($key, 'foobar');
    }

    public function testSetMultipleNoIterable()
    {
        $this->expectException(TypeError::class);
        $this->cache->setMultiple('key');
    }

    /**
     * @dataProvider invalidStringKeys
     */
    public function testHasInvalidStringKeys($key)
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->cache->has($key);
    }
    /**
     * @dataProvider invalidNonStringKeys
     */
    public function testHasInvalidNonStringKeys($key)
    {
        $this->expectException(TypeError::class);
        $this->cache->has($key);
    }

    /**
     * @dataProvider invalidStringKeys
     */
    public function testDeleteInvalidStringKeys($key)
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->cache->delete($key);
    }

    /**
     * @dataProvider invalidNonStringKeys
     */
    public function testDeleteInvalidNonStringKeys($key)
    {
        $this->expectException(TypeError::class);
        $this->cache->delete($key);
    }


    public function testDeleteMultipleNoIterable()
    {
        $this->expectException(TypeError::class);
        $this->cache->deleteMultiple('key');
    }

    /**
     * @dataProvider invalidTtl
     */
    public function testSetInvalidTtl($ttl)
    {
        $this->expectException(TypeError::class);
        $this->cache->set('key', 'value', $ttl);
    }

    /**
     * @dataProvider invalidTtl
     */
    public function testSetMultipleInvalidTtl($ttl)
    {
        $this->expectException(TypeError::class);
        $this->cache->setMultiple(['key' => 'value'], $ttl);
    }

    public static function invalidNonStringKeys()
    {
        return [
            [true],
            [false],
            [null],
            [2.5],
            [new \stdClass()],
            [['array']],
        ];
    }

    public static function invalidStringKeys()
    {
        return [
            [''],
            ['{str'],
            ['rand{'],
            ['rand{str'],
            ['rand}str'],
            ['rand(str'],
            ['rand)str'],
            ['rand/str'],
            ['rand\\str'],
            ['rand@str'],
            ['rand:str'],
        ];
    }
}
