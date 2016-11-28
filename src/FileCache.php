<?php

namespace Kodus\Cache;

use DateInterval;
use FilesystemIterator;
use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\CounterInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * This is a simple, file-based cache implementation, which is bootstrapped by
 * the Core Provider as a default.
 *
 * Bootstrapping a more powerful cache for production scenarios is highly recommended.
 *
 * @link https://github.com/matthiasmullie/scrapbook/
 */
class FileCache implements CacheInterface, CounterInterface
{
    // TODO garbage collection

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
     * @param string $cache_path  absolute root path of cache-file folder
     * @param int    $default_ttl default time-to-live (in seconds)
     *
     * @throws InvalidArgumentException if the specified cache-path does not exist (or is not writable)
     */
    public function __construct($cache_path, $default_ttl)
    {
        $path = realpath($cache_path);

        if ($path === false) {
            throw new InvalidArgumentException("cache path does not exist: {$cache_path}");
        }

        if (! is_writable($path . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException("cache path is not writable: {$cache_path}");
        }

        $this->cache_path = $path;
        $this->default_ttl = $default_ttl;
    }

    public function get($key, $default = null)
    {
        $path = $this->getPath($key);

        if (! file_exists($path)) {
            return $default; // file not found
        }

        $file = @fopen($path, 'rb');

        if ($file === false) {
            return $default; // file not found
        }

        $expires_at = intval(fgets($file));

        if ($this->getTime() > $expires_at) {
            fclose($file);

            @unlink($path);

            return $default;
        }

        $data = stream_get_contents($file);

        fclose($file);

        if ($data === 'b:0;') {
            return false; // because we can't otherwise distinguish a FALSE return-value from unserialize()
        }

        $value = @unserialize($data);

        if ($value === false) {
            return $default; // unserialize() failed
        }

        return $value;
    }

    public function set($key, $value, $ttl = null)
    {
        $path = $this->getPath($key);

        $dir = dirname($path);

        if (! file_exists($dir)) {
            @mkdir($dir, 0777, true); // ensure that the parent path exists
        }

        $temp_path = $this->cache_path . DIRECTORY_SEPARATOR . uniqid('', true);

        $data = serialize($value);

        if ($ttl instanceof DateInterval) {
            $ttl = ($ttl->s)
                + ($ttl->i * 60)
                + ($ttl->h * 60 * 60)
                + ($ttl->d * 60 * 60 * 24)
                + ($ttl->m * 60 * 60 * 24 * 30)
                + ($ttl->y * 60 * 60 * 24 * 365);
        } elseif ($ttl === null) {
            $ttl = $this->default_ttl;
        }

        if (! is_int($ttl)) {
            throw new InvalidArgumentException("invalid TTL: " . print_r($ttl, true));
        }

        $expires_at = $this->getTime() + $ttl;

        if (false === @file_put_contents($temp_path, "{$expires_at}\n{$data}")) {
            return false;
        }

        if (@rename($temp_path, $path)) {
            return true;
        }

        @unlink($temp_path);

        return false;
    }

    public function delete($key)
    {
        @unlink($this->getPath($key));
    }

    public function clear()
    {
        $iterator = new RecursiveDirectoryIterator(
            $this->cache_path,
            FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS
        );

        $iterator = new RecursiveIteratorIterator($iterator);

        foreach ($iterator as $path) {
            if (is_dir($path)) {
                continue; // leave directories in place, so we don't have to create them again
            }

            @unlink($path);
        }
    }

    public function getMultiple($keys)
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    public function setMultiple($items, $ttl = null)
    {
        $ok = true;

        foreach ($items as $key => $value) {
            $ok = $this->set($key, $value, $ttl) && $ok;
        }

        return $ok;
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    public function has($key)
    {
        return $this->get($key, $this) !== $this;
    }

    public function increment($key, $step = 1)
    {
        $path = $this->getPath($key);

        $dir = dirname($path);

        if (! file_exists($dir)) {
            @mkdir($dir, 0777, true); // ensure that the parent path exists
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
        if (preg_match(self::PSR16_RESERVED, $key, $match) === 1) {
            throw new InvalidArgumentException("invalid character in key: {$match[0]}");
        }

        $hash = sha1(static::class . $key);

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
}
