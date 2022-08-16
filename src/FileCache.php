<?php

declare(strict_types=1);

namespace Kodus\Cache;

use DateInterval;
use FilesystemIterator;
use Generator;
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * This class implements a simple, file-based cache.
 *
 * Make sure your schedule an e.g. nightly call to {@see cleanExpired()}.
 */
class FileCache implements CacheInterface
{
    /**
     * @var string control characters for keys, reserved by PSR-16
     */
    const PSR16_RESERVED = '/\{|\}|\(|\)|\/|\\\\|\@|\:/u';

    /**
     * @var string
     */
    private $cache_path;

    /**
     * @var int
     */
    private $default_ttl;

    /**
     * @var int
     */
    private $dir_mode;

    /**
     * @var int
     */
    private $file_mode;

    /**
     * @param string $cache_path  absolute root path of cache-file folder
     * @param int    $default_ttl default time-to-live (in seconds)
     * @param int    $dir_mode    permission mode for created dirs
     * @param int    $file_mode   permission mode for created files
     *
     * @throws InvalidArgumentException
     */
    public function __construct($cache_path, $default_ttl, $dir_mode = 0775, $file_mode = 0664)
    {
        $this->default_ttl = $default_ttl;
        $this->dir_mode = $dir_mode;
        $this->file_mode = $file_mode;

        if (! file_exists($cache_path) && file_exists(dirname($cache_path))) {
            $this->mkdir($cache_path); // ensure that the parent path exists
        }

        $path = realpath($cache_path);

        if ($path === false) {
            throw new InvalidArgumentException("cache path does not exist: {$cache_path}");
        }

        if (! is_writable($path . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException("cache path is not writable: {$cache_path}");
        }

        $this->cache_path = $path;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getPath($key);

        $expires_at = @filemtime($path);

        if ($expires_at === false) {
            return $default; // file not found
        }

        if ($this->getTime() >= $expires_at) {
            @unlink($path); // file expired

            return $default;
        }

        $data = @file_get_contents($path);

        if ($data === false) {
            return $default; // race condition: file not found
        }

        if ($data === 'b:0;') {
            return false; // because we can't otherwise distinguish a FALSE return-value from unserialize()
        }

        $value = @unserialize($data);

        if ($value === false) {
            return $default; // unserialize() failed
        }

        return $value;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $path = $this->getPath($key);

        $dir = dirname($path);

        if (! file_exists($dir)) {
            // ensure that the parent path exists:
            $this->mkdir($dir);
        }

        $temp_path = $this->cache_path . DIRECTORY_SEPARATOR . uniqid('', true);

        if (is_int($ttl)) {
            $expires_at = $this->getTime() + $ttl;
        } elseif ($ttl instanceof DateInterval) {
            $expires_at = date_create_from_format("U", (string) $this->getTime())->add($ttl)->getTimestamp();
        } elseif ($ttl === null) {
            $expires_at = $this->getTime() + $this->default_ttl;
        } else {
            throw new InvalidArgumentException("invalid TTL: " . print_r($ttl, true));
        }

        if (false === @file_put_contents($temp_path, serialize($value))) {
            return false;
        }

        if (false === @chmod($temp_path, $this->file_mode)) {
            return false;
        }

        if (@touch($temp_path, $expires_at) && @rename($temp_path, $path)) {
            return true;
        }

        @unlink($temp_path);

        return false;
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);

        $path = $this->getPath($key);

        return !file_exists($path) || @unlink($path);
    }

    public function clear(): bool
    {
        $success = true;

        $paths = $this->listPaths();

        foreach ($paths as $path) {
            if (! unlink($path)) {
                $success = false;
            }
        }

        return $success;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key) ?: $default;
        }

        return $values;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $ok = true;

        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $key = (string) $key;
            }

            $this->validateKey($key);

            $ok = $this->set($key, $value, $ttl) && $ok;
        }

        return $ok;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;

        foreach ($keys as $key) {
            $this->validateKey($key);

            $ok = $ok && $this->delete($key);
        }

        return $ok;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function increment($key, $step = 1)
    {
        $path = $this->getPath($key);

        $dir = dirname($path);

        if (! file_exists($dir)) {
            $this->mkdir($dir); // ensure that the parent path exists
        }

        $lock_path = $dir . DIRECTORY_SEPARATOR . ".lock"; // allows max. 256 client locks at one time

        $lock_handle = fopen($lock_path, "w");

        flock($lock_handle, LOCK_EX);

        $value = $this->get($key, 0) + $step;

        $ok = $this->set($key, $value);

        flock($lock_handle, LOCK_UN);

        return $ok ? $value : false;
    }

    public function decrement($key, $step = 1)
    {
        return $this->increment($key, -$step);
    }

    /**
     * Clean up expired cache-files.
     *
     * This method is outside the scope of the PSR-16 cache concept, and is specific to
     * this implementation, being a file-cache.
     *
     * In scenarios with dynamic keys (such as Session IDs) you should call this method
     * periodically - for example from a scheduled daily cron-job.
     *
     * @return void
     */
    public function cleanExpired()
    {
        $now = $this->getTime();

        $paths = $this->listPaths();

        foreach ($paths as $path) {
            if ($now > filemtime($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * For a given cache key, obtain the absolute file path
     *
     * @param string $key
     *
     * @return string absolute path to cache-file
     *
     * @throws InvalidArgumentException if the specified key contains a character reserved by PSR-16
     */
    protected function getPath($key)
    {
        $this->validateKey($key);

        $hash = hash("sha256", $key);

        return $this->cache_path
            . DIRECTORY_SEPARATOR
            . strtoupper($hash[0])
            . DIRECTORY_SEPARATOR
            . strtoupper($hash[1])
            . DIRECTORY_SEPARATOR
            . substr($hash, 2);
    }

    /**
     * @return int current timestamp
     */
    protected function getTime()
    {
        return time();
    }

    /**
     * @return Generator|string[]
     */
    protected function listPaths()
    {
        $iterator = new RecursiveDirectoryIterator(
            $this->cache_path,
            FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS
        );

        $iterator = new RecursiveIteratorIterator($iterator);

        foreach ($iterator as $path) {
            if (is_dir($path)) {
                continue; // ignore directories
            }

            yield $path;
        }
    }

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    protected function validateKey($key)
    {
        if (! is_string($key)) {
            $type = is_object($key) ? get_class($key) : gettype($key);

            throw new InvalidArgumentException("invalid key type: {$type} given");
        }

        if ($key === "") {
            throw new InvalidArgumentException("invalid key: empty string given");
        }

        if ($key === null) {
            throw new InvalidArgumentException("invalid key: null given");
        }

        if (preg_match(self::PSR16_RESERVED, $key, $match) === 1) {
            throw new InvalidArgumentException("invalid character in key: {$match[0]}");
        }
    }

    /**
     * Recursively create directories and apply permission mask
     *
     * @param string $path absolute directory path
     */
    private function mkdir($path)
    {
        $parent_path = dirname($path);

        if (!file_exists($parent_path)) {
            $this->mkdir($parent_path); // recursively create parent dirs first
        }

        mkdir($path);
        chmod($path, $this->dir_mode);
    }
}
