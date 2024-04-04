# Autoframe is a low level framework that is oriented on SOLID flexibility

[![Build Status](https://github.com/autoframe/components-socket-cache/workflows/PHPUnit-tests/badge.svg)](https://github.com/autoframe/components-socket-cache/actions?query=branch:main)
[![License: The 3-Clause BSD License](https://img.shields.io/github/license/autoframe/components-socket-cache)](https://opensource.org/license/bsd-3-clause/)
![Packagist Version](https://img.shields.io/packagist/v/autoframe/components-socket-cache?label=packagist%20stable)
[![Downloads](https://img.shields.io/packagist/dm/autoframe/components-socket-cache.svg)](https://packagist.org/packages/autoframe/components-socket-cache)

*PHP socket client - cache server manager app*

**Examples**

```php
use Autoframe\Components\SocketCache\Client\AfrClientStore;
use Autoframe\Components\SocketCache\App\AfrCacheApp;
use Autoframe\Components\SocketCache\Facade\AfrCache;
use Autoframe\Components\SocketCache\Facade\AfrRepositoryAutoSelector;

$oApp = AfrCacheApp::getInstance();

        `null`
        AfrCacheApp::getInstance()->setNullConfig(true);
        $oRepo = AfrCache::getManager()->store()

...

        `afrsock`
        if ($oApp->testSock()) {
            $oApp->setSockConfig([ // AfrCacheSocketConfig
                'driver' => 'afrsock',
                'iAutoShutdownServerAfterXSeconds' => 40,
                'bServerAutoPowerOnByConfigViaCliOnLocal' => true,
                'bObfuscateCommunicationBetweenClientServer' => false,
                'iServerMemoryMb' => 64,
                //     'socketPort' =>  rand(11222, 13222);
            ], $bDefault = true);
            $oRepo = AfrCache::getManager()->store();  //instanceof \Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Repository
            $oRepo = AfrCache::getManager()->store('afrsock');
        }

...
        `array`
        $oApp = AfrCacheApp::getInstance()->setArrayConfig(
            $bSerialize = true,
            $bDefault = true
        );
        $oRepo = AfrCache::getManager()->store('array');

...
        `array`
        $oApp = AfrCacheApp::getInstance()->setArrayConfig(
            $bSerialize = true,
            $bDefault = true
        );
        $oRepo = AfrCache::getManager()->store('array');

...
        `file`
        $oApp = AfrCacheApp::getInstance()->setFileConfig(
            $bDefault = true,
            [ 'path' => __DIR__ . DIRECTORY_SEPARATOR . 'fileCache', ]
        );
        $oRepo = AfrCache::getManager()->store('file');
        //And...
        
        $oApp = AfrCacheApp::getInstance()->setFileConfig(
            $bDefault = false,
            [
                'driver' => 'file_nth_driver',
                'path' => __DIR__ . DIRECTORY_SEPARATOR . 'fileCache_other_dir',
            ]
        );
        $oRepo = AfrCache::getManager()->store('file_nth_driver');


...

        `memcached`
        if ($oApp->testMemcached()) {
            $oApp->setMemcachedConfig(
                $bDefault = false,
                [
                //'driver' => 'memcached',
                'servers' => $oApp->parseMemcachedServers('localhost:11211:100,...'),
                ]
            );
            $oRepo = AfrCache::getManager()->store('memcached');
        }



...

        `apc`  //apcu
        if ($oApp->testApc()) {
            $oApp->setApcConfig( $bDefault = false );
            $oRepo = AfrCache::getManager()->store('apc');
        }



```

---

```php
`AfrRepositoryAutoSelector`


use Autoframe\Components\SocketCache\Client\AfrClientStore;
use Autoframe\Components\SocketCache\App\AfrCacheApp;
use Autoframe\Components\SocketCache\Facade\AfrCache;
use Autoframe\Components\SocketCache\Facade\AfrRepositoryAutoSelector;


        AfrRepositoryAutoSelector::setToUseRepositories(
            //HIGH_LOAD::SECONDARY_LOAD::FILESYSTEM::FILESYSTEM2::RAM::NONE
            AfrRepositoryAutoSelector::SECONDARY_LOAD, 
            ['file'] //driver name
        );
        
        $sKeyName = $sKeyVal = 'sKeyName';
        $oRepo = AfrRepositoryAutoSelector::selectRepoByKeyNs(
            AfrRepositoryAutoSelector::prefixKeyForRepo(
                $sKeyName,
                AfrRepositoryAutoSelector::SECONDARY_LOAD
            )
        );
        $oRepo->set($sKeyName,$sKeyVal,60);
        // 1-9 priority or null for auto
        $oRepo = AfrRepositoryAutoSelector::selectRepoByKeyNs(AfrRepositoryAutoSelector::SECONDARY_LOAD.'\\1\\' . $sKeyName);
        $this->assertSame(true, $oRepo instanceof \Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Repository);
        $this->assertSame($sKeyVal, $oRepo->get($sKeyName));
        $oRepo->clear();//flush

```

---

```php
`AfrCache`

namespace Autoframe\Components\SocketCache\Facade;

use Autoframe\Components\SocketCache\App\AfrCacheApp;
use Autoframe\Components\SocketCache\LaravelPort\Cache\CacheManager;
use Autoframe\Components\SocketCache\AfrCacheManager;

/**
 * @method static \Autoframe\Components\SocketCache\LaravelPort\Cache\TaggedCache tags(array|mixed $names)
 * @method static \Autoframe\Components\SocketCache\LaravelPort\Cache\Lock lock(string $name, int $seconds = 0, mixed $owner = null)
 * @method static \Autoframe\Components\SocketCache\LaravelPort\Cache\Lock restoreLock(string $name, string $owner)
 * @method static \Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Repository  store(string|null $name = null)
 * @method static \Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Store getStore()
 * @method static bool add(string $key, $value, \DateTimeInterface|\DateInterval|int $ttl = null)
 * @method static bool flush()
 * @method static bool forever(string $key, $value)
 * @method static bool forget(string $key)
 * @method static bool has(string $key)
 * @method static bool missing(string $key)
 * @method static bool put(string $key, $value, \DateTimeInterface|\DateInterval|int $ttl = null)
 * @method static int|bool decrement(string $key, $value = 1)
 * @method static int|bool increment(string $key, $value = 1)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static mixed remember(string $key, \DateTimeInterface|\DateInterval|int $ttl, \Closure $callback)
 * @method static mixed rememberForever(string $key, \Closure $callback)
 * @method static mixed sear(string $key, \Closure $callback)
 *
 * @see \Autoframe\Components\SocketCache\AfrCacheManager
 * @see \Autoframe\Components\SocketCache\LaravelPort\Cache\CacheManager
 * @see \Autoframe\Components\SocketCache\LaravelPort\Cache\Repository
 */
class AfrCache
{
    /**
     * @var CacheManager|AfrCacheManager
     */
    protected static CacheManager $instance;

    /**
     * @param CacheManager|AfrCacheManager $oCacheManager
     * @return CacheManager|AfrCacheManager
     */
    public static function setManager(CacheManager $oCacheManager): CacheManager
    {
        return static::$instance = $oCacheManager;
    }

    /**
     * @return CacheManager
     */
    public static function getManager(): CacheManager
    {
        if (empty(static::$instance)) {
            static::setManager(
                new AfrCacheManager(
                    AfrCacheApp::getInstance()
                )
            );
        }
        return static::$instance;
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return static::getManager()->$method(...$args);
    }

}

```

---

```php

namespace Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache;

use Closure;

interface Repository
{
    // use Psr\SimpleCache\CacheInterface;
    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null);

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null);

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key);

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear();

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null);

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null);

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys);

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key);
    
    /**
     * Retrieve an item from the cache and delete it.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function pull($key, $default = null);

    /**
     * Store an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool
     */
    public function put($key, $value, $ttl = null);

    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool
     */
    public function add($key, $value, $ttl = null);

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function increment($key, $value = 1);

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function decrement($key, $value = 1);

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function forever($key, $value);

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param  string  $key
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @param  \Closure  $callback
     * @return mixed
     */
    public function remember($key, $ttl, Closure $callback);

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @param  string  $key
     * @param  \Closure  $callback
     * @return mixed
     */
    public function sear($key, Closure $callback);

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @param  string  $key
     * @param  \Closure  $callback
     * @return mixed
     */
    public function rememberForever($key, Closure $callback);

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key);

    /**
     * Get the cache store implementation.
     *
     * @return \Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Store
     */
    public function getStore();
}



```
