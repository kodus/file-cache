<?php

namespace Kodus\Cache\Test;

use Kodus\Cache\FileCache;

/**
 * File cache extension for testing - makes time stand still and allows us to time-travel ;-)
 */
class TestableFileCache extends FileCache
{
    /**
     * @var int
     */
    protected $time_frozen;

    public function __construct($cache_path, $default_ttl)
    {
        parent::__construct($cache_path, $default_ttl);

        $this->time_frozen = parent::getTime();
    }

    protected function getTime()
    {
        return $this->time_frozen;
    }

    /**
     * @param int $seconds
     */
    public function skipTime($seconds)
    {
        $this->time_frozen += $seconds;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function getCachePath($key)
    {
        return $this->getPath($key);
    }
}
