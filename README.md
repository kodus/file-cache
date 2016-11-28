kodus/file-cache
================

[![PHP Version](https://img.shields.io/badge/php-5.6%2B-blue.svg)](https://packagist.org/packages/kodus/file-cache)
[![Build Status](https://travis-ci.org/kodus/file-cache.svg?branch=master)](https://travis-ci.org/kodus/file-cache)
[![Code Coverage](https://scrutinizer-ci.com/g/kodus/file-cache/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/kodus/file-cache/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/kodus/file-cache/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/kodus/file-cache/?branch=master)

This library provides a minimal [PSR-16](https://github.com/php-fig/fig-standards/blob/master/proposed/simplecache.md)
cache-implementation backed by simple file-system storage.

This can be used to provide working, lightweight bootstrapping when you want to ship a project that works
out of the box, but doesn't depend on an [awesome, full-blown caching-framework](http://www.scrapbook.cash/).


## Usage

Please refer to the [PSR-16 spec](https://packagist.org/packages/psr/simple-cache) for the API description.

### Garbage Collection

Because this is a file-based cache, you do need to think about garbage-collection as it relates to your use-case.

TODO
