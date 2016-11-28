<?php

namespace Kodus\Cache\Test;

use Kodus\Cache\FileCache;

/**
 * File cache extension for testing - allows us to skip forward in time ;-)
 */
class TestableFileCache extends FileCache
{
    protected $time_skip = 0;

    protected function getTime()
    {
        return parent::getTime() + $this->time_skip;
    }

    /**
     * @param int $seconds
     */
    public function skipTime($seconds)
    {
        $this->time_skip += $seconds;
    }
}
