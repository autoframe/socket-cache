<?php
declare(strict_types=1);

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
