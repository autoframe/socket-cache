<?php

namespace Autoframe\Components\SocketCache\Common;

use Autoframe\Components\Exception\AfrException;
use Autoframe\Components\SocketCache\AfrCacheSocketConfig;
use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;

abstract class AfrCacheSocketStore extends TaggableStore implements Store
{
    //TODO !!! la get si many exista valori default pe undeva puse ca si overload de parametrii!! Please check!
    //TODO class TaggedCache extends Repository
    //TODO Illuminate\Contracts\Cache\Store

    const TS = 0;
    const VALUE = 1;
    const EXISTS = 2;

    const MAXTS = 9999999999;
    const MINTS = -9999999999;


    protected AfrCacheSocketConfig $oSocketConfig;

    /**
     * Create a new ******* store.
     *
     * @param  AfrCacheSocketConfig  $oSocketConfig
     * @return void
     */
    public function __construct(AfrCacheSocketConfig $oSocketConfig)
    {
        $this->oSocketConfig = $oSocketConfig;
    }


    //TODO Illuminate\Contracts\Cache\LockProvider

    //TODO Psr\SimpleCache\CacheInterface
    //TODO Illuminate\Contracts\Cache\Repository extends CacheInterface
// TODO DEJA IMPLEMENTAT CA class Repository implements ArrayAccess, CacheContract

    /**
     * @throws AfrException
     */
    public static function addCacheConfig(
        &$app,
        AfrCacheSocketConfig $oConfig
    ): void
    {
        if (is_array($app)) {
            if (!isset($app['config']['cache.prefix'])) {
                $app['config']['cache.prefix'] = '';
            }
            $sKeyConfig = 'cache.stores.' . $oConfig->sConfigName;
            $app['config'][$sKeyConfig] = [
                'driver' => 'AfrSocket',
                'prefix' => '', //$aRepoConfig['prefix'] ?? $app['config']['cache.prefix'];
                'oConfigSerialized' => serialize($oConfig),
            ];

        } else {
            throw new AfrException('Check for config/cache.php for the "AfrSocket" ');
        }
    }

    //TODO !!! https://www.youtube.com/watch?v=-NOOqIYEFwc&ab_channel=CodeWithDary

    public static function registerServiceProviderBoot(
        CacheManager         $oCacheManager,
                             &$app,
        AfrCacheSocketConfig $oConfig
    ): void
    {
        $oCacheManager->extend('AfrSocket', function ($oCacheManager, $app, $oConfig) {
            $prefix = $app['config']['cache.stores.' . $oConfig->sConfigName]['prefix'];
 //           return $oCacheManager->repository(new AfrCacheSocketStore($oConfig, $prefix));
        });
    }

    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback->bindTo($this, $this);

        return $this;
    }



//Illuminate\Contracts\Foundation\Application
    public static function zzzzz(
        AfrCacheSocketConfig $oConfig,
        &$app
    )
    {
/*
CacheManager->store($name)->(
    $this->stores[$name] = $this->get($name)->resolve($name)
    function resolve($name){
        $config = $this->getConfig($name);
        @return Repository
        return $this->callCustomCreator($config);
    }
    )

    0. $app prepare :D
    1. CacheManager->extend(string $driver_keyName, Closure $callback)  customCreators
    ----
    2. CacheManager->store($name = null) || CacheManager->driver($name = null)


 * */


        $aRepoConfig = [
            'driver' => 'AfrSocket',
            'prefix' => '', //$aRepoConfig['prefix'] ?? $app['config']['cache.prefix'];
            'oConfig' => $oConfig,
        ];

        //$driverMethod = 'create'.ucfirst($config['driver']).'Driver';
        return $aRepoConfig;
    }



    protected function getConfig($name)
    {
        if (! is_null($name) && $name !== 'null') {
            return $this->app['config']["cache.stores.{$name}"];
        }

        return ['driver' => 'null'];
    }

    /**
     * @param array $config
     * @return Repository
     */
    protected function callCustomCreator(array $config)
    {
        return $this->customCreators[$config['driver']]($this->app, $config);
    }


    /**
     * Create an instance of the Memcached cache driver.
     *
     * @param  array  $config
     * @return \Illuminate\Cache\Repository
     */
    protected function createMemcachedDriver(array $config)
    {
        $prefix = $this->getPrefix($config);

        $memcached = $this->app['memcached.connector']->connect(
            $config['servers'],
            $config['persistent_id'] ?? null,
            $config['options'] ?? [],
            array_filter($config['sasl'] ?? [])
        );

        return $this->repository(new MemcachedStore($memcached, $prefix));
    }



}
