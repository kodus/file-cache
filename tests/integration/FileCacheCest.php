<?php

namespace Kodus\Cache\Test\Integration;

use Codeception\Util\FileSystem;
use DateInterval;
use IntegrationTester;
use Kodus\Cache\Test\TestableFileCache;

class FileCacheCest
{
    /**
     * @var int
     */
    const DEFAULT_EXPIRATION = 86400;

    /**
     * @var TestableFileCache
     */
    protected $cache;

    public function _before()
    {
        $path = dirname(__DIR__) . "/_output/cache";

        FileSystem::deleteDir($path);

        $this->cache = new TestableFileCache($path, self::DEFAULT_EXPIRATION);

        assert(file_exists($path));
        
        assert(is_writable($path));
    }

    public function _after()
    {
        $this->cache->clear();
    }

    public function setGetAndDelete(IntegrationTester $I)
    {
        $I->assertTrue($this->cache->set("key1", "value1"));
        $I->assertTrue($this->cache->set("key2", "value2"));

        $I->assertSame("value1", $this->cache->get("key1"));
        $I->assertSame("value2", $this->cache->get("key2"));

        $this->cache->delete("key1");

        $I->assertSame(null, $this->cache->get("key1"));
        $I->assertSame("value2", $this->cache->get("key2"));
    }

    public function getNonExisting(IntegrationTester $I)
    {
        $I->assertSame(null, $this->cache->get("key"));
        $I->assertSame("default", $this->cache->get("key", "default"));
    }

    public function expirationInSeconds(IntegrationTester $I)
    {
        $this->cache->set("key", "value", 10);

        $this->cache->skipTime(5);

        $I->assertSame("value", $this->cache->get("key"));

        $this->cache->skipTime(5);

        $I->assertSame(null, $this->cache->get("key"));
        $I->assertSame("default", $this->cache->get("key", "default"));
    }

    public function expirationByInterval(IntegrationTester $I)
    {
        $interval = new DateInterval("PT10S");

        $this->cache->set("key", "value", $interval);

        $this->cache->skipTime(5);

        $I->assertSame("value", $this->cache->get("key"));

        $this->cache->skipTime(5);

        $I->assertSame(null, $this->cache->get("key"));
        $I->assertSame("default", $this->cache->get("key", "default"));
    }

    public function expirationByDefault(IntegrationTester $I)
    {
        $this->cache->set("key", "value");

        $this->cache->skipTime(self::DEFAULT_EXPIRATION - 5);

        $I->assertSame("value", $this->cache->get("key"));

        $this->cache->skipTime(10);

        $I->assertSame(null, $this->cache->get("key"));
        $I->assertSame("default", $this->cache->get("key", "default"));
    }

    public function expirationInThePast(IntegrationTester $I)
    {
        $this->cache->set("key1", "value1", 0);
        $this->cache->set("key2", "value2", -10);

        $I->assertSame("default", $this->cache->get("key1", "default"));
        $I->assertSame("default", $this->cache->get("key2", "default"));
    }

    public function clear(IntegrationTester $I)
    {
        // add some values that should be gone when we clear cache:

        $this->cache->set("key1", "value1");
        $this->cache->set("key2", "value2");

        $this->cache->clear();

        // check to confirm everything"s been wiped out:

        $I->assertSame(null, $this->cache->get("key1"));
        $I->assertSame("default", $this->cache->get("key1", "default"));

        $I->assertSame(null, $this->cache->get("key2"));
        $I->assertSame("default", $this->cache->get("key2", "default"));
    }

    public function cleanExpired(IntegrationTester $I)
    {
        $this->cache->set("key1", "value1", 10);
        $this->cache->set("key2", "value2", 30);

        $this->cache->skipTime(20);

        $this->cache->cleanExpired();

        $I->assertFileNotExists($this->cache->getCachePath("key1"), "file has expired");
        $I->assertFileExists($this->cache->getCachePath("key2"), "file has not expired");
    }

    public function testGetAndSetMultiple(IntegrationTester $I)
    {
        $this->cache->setMultiple(["key1" => "value1", "key2" => "value2"]);

        $results = $this->cache->getMultiple(["key1", "key2", "key3"]);

        $I->assertSame(["key1" => "value1", "key2" => "value2", "key3" => null], $results);
    }

    public function testDeleteMultiple(IntegrationTester $I)
    {
        $this->cache->setMultiple(["key1" => "value1", "key2" => "value2", "key3" => "value3"]);

        $this->cache->deleteMultiple(["key1", "key2"]);

        $I->assertSame(["key1" => null, "key2" => null], $this->cache->getMultiple(["key1", "key2"]));

        $I->assertSame("value3", $this->cache->get("key3"));
    }

    public function testHas(IntegrationTester $I)
    {
        $this->cache->set("key", "value");

        $I->assertSame(true, $this->cache->has("key"));
        $I->assertSame(false, $this->cache->has("fudge"));
    }

    public function testIncrement(IntegrationTester $I)
    {
        // test setting initial value:

        $I->assertSame(5, $this->cache->increment("key", 5));

        // test incrementing value:

        $I->assertSame(10, $this->cache->increment("key", 5));
        $I->assertSame(11, $this->cache->increment("key"));
    }

    public function testDecrement(IntegrationTester $I)
    {
        // test setting initial value:

        $I->assertSame(10, $this->cache->increment("key", 10));

        // test decrementing value:

        $I->assertSame(5, $this->cache->decrement("key", 5));
        $I->assertSame(4, $this->cache->decrement("key"));
    }
}
