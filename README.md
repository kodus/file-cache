kodus/file-cache
================

[![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://packagist.org/packages/kodus/file-cache)
[![Build Status](https://travis-ci.org/kodus/file-cache.svg?branch=master)](https://travis-ci.org/kodus/file-cache)
[![Code Coverage](https://scrutinizer-ci.com/g/kodus/file-cache/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/kodus/file-cache/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/kodus/file-cache/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/kodus/file-cache/?branch=master)

This library provides a minimal [PSR-16](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-16-simple-cache.md)
cache-implementation backed by simple file-system storage.

This can be used to provide working, lightweight bootstrapping when you want to ship a project that works
out of the box, but doesn't depend on an [awesome, full-blown caching-framework](http://www.scrapbook.cash/).


## Strategy

Files are stored in a specified cache-folder, with two levels of sub-folders to avoid file-system limitations on
the number of files per folder. (This will probably work okay for entry-numbers in the tens of thousands - if you're
storing cache-entries in the millions, you should not be using a file-based cache.)

To reduce storage overhead and speed up expiration time-checks, the file modification time will be set in the future.
(The file creation timestamp will reflect the time the file was actually created.)

## Usage

Please refer to the [PSR-16 spec](https://packagist.org/packages/psr/simple-cache) for the API description.

### Security

In a production setting, consider specifying appropriate `$dir_mode` and `$file_mode` constructor-arguments for
your hosting environment - the defaults are a typical choice, but you may be able to tighten permissions on your
system, if needed.

### Garbage Collection

Because this is a file-based cache, you do need to think about garbage-collection as it relates to your use-case.

This cache-implementation does not do any automatic garbage-collection on-the-fly, because this would periodically
block a user-request, and garbage-collection across a file-system isn't very fast.

A public method `cleanExpired()` will flush expired entries - depending on your use-case, consider these options:

  1. For cache-entries with non-dynamic keys (e.g. based on primary keys, URLs, etc. of user-managed
     data) you likely don't need garbage-collection. Manually clearing the folder once a year or so might suffice.

  2. For cache-entries with dynamic keys (such as Session IDs, or other random or pseudo-random keys) you should
     set up a cron-job to call the `cleanExpired()` method periodically, say, once per day.

For cache-entries with dynamic keys in the millions, as mentioned, you probably don't want a file-based cache.

## License

MIT license. Please see the [license file](LICENSE) for more information.

