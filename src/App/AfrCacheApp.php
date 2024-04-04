<?php

namespace Autoframe\Components\SocketCache\App;

use Autoframe\Components\Arr\Merge\AfrArrMergeProfileClass;
use Autoframe\Components\Exception\AfrException;
use Autoframe\Components\SocketCache\AfrCacheSocketConfig;
use Autoframe\Components\SocketCache\Client\AfrClientStore;

class AfrCacheApp extends ArrayAccessSingletonApp // extends Autoframe\Components\SocketCache\LaravelPort\Contracts\Foundation\Application
{
    /**
     * @var \Closure|null
     */
    protected $oEnvGetterClosure = null;
    protected static $aSupportedTypes;
    protected array $aArrayAccessData = [
        'bound' => false,
        'files' => null,
        'memcached.connector' => null,
        'redis' => null,
        'database.redis' => null,
        'config' => [
            'cache.prefix' => '',
            'cache.default' => 'null',
            'cache.stores.null' => ['driver' => 'null'],
        //    'cache.stores.afrsock' => ['driver' => 'afrsock'],
        ],
    ];

    public function extendAppConfig(array $aData = [], bool $bMerge = true): self
    {
        if ($bMerge) {
            AfrArrMergeProfileClass::getInstance()->arrayMergeProfile($this->aArrayAccessData, $aData);
        } else {
            $this->aArrayAccessData = $aData;
        }
        $this->keyMaping(true);
        return $this;
    }

    /**
     * @return array
     */
    public function getSupportedRepositoryTypes(): array
    {
        if(!isset(self::$aSupportedTypes)){
            self::$aSupportedTypes = [
                'array' => true,
                'null' => true,
                'file' => true,
                'apc' => $this->testApc(),
                'afrsock' => $this->testSock(),
                'memcached' => $this->testMemcached(),
                'redis' => false, //$this->testRedis(),
                'database' => false,
                'dynamodb' => false,
                'mongodb' => false,
            ];
        }
        return self::$aSupportedTypes;
    }

    /**
     * [$sConfigName=>$sDriver]
     * @return array
     */
    public function getAvailableRepositoryNames(): array
    {
        $aStoreNames = [];
        $aSupportedTypes = $this->getSupportedRepositoryTypes();
        foreach ($this->aArrayAccessData['config'] as $sConfigKey => $mConfigData) {
            if (
                !empty($mConfigData['driver']) &&
                !empty($aSupportedTypes[$mConfigData['driver']]) &&
                substr((string)$sConfigKey, 0, 13) === 'cache.stores.'
            ) {
                $aStoreNames[substr((string)$sConfigKey, 13)] = $mConfigData['driver'];
            }
        }
        return $aStoreNames;
    }

    /**
     * @param string $sDriverName
     * @param array $aData
     * @param bool $bDefault
     * @param \Closure|null $AppClosure
     * @return $this
     */
    public function setStoreConfig
    (
        string   $sDriverName = 'null',
        array    $aData = [],
        bool     $bDefault = false,
        \Closure $AppClosure = null
    ): self
    {

        $aData['driver'] = $sDriverName;
        $this->aArrayAccessData['config']['cache.stores.' . $aData['driver']] = $aData;
        if ($bDefault) {
            $this->aArrayAccessData['config']['cache.default'] = $aData['driver'];
        }
        if ($AppClosure instanceof \Closure) {
            $AppClosure->bindTo($this, $this);
            $AppClosure($aData);
        }
        $this->keyMaping(true); //array pointer fix for $this->aArrayAccessData

        return $this;
    }


    public function testSock(): bool
    {
        return function_exists('socket_create');
    }

    /**
     * /**
     * @param array $aData
     * @param bool $bDefault
     * @return $this
     * @throws AfrException
     */
    public function setSockConfig(array $aData = [], bool $bDefault = false): self
    {
        if (!$this->testSock()) {
            throw new AfrException('REQUIRED SOCKET EXTENSION ext-sockets in ' . __CLASS__);
        }

        if (empty($aData['driver'])) {
            $aData['driver'] = 'afrsock';
        }
        if (empty($aData['extend']) || !$aData['extend'] instanceof \Closure) {
            $aData['extend'] = function ($oApp, $aConfig) {
                AfrCacheSocketConfig::setSockConfigParameters($aConfig);
                return $this->repository(
                    new AfrClientStore(
                        AfrCacheSocketConfig::getConfigInstance($aConfig['driver'])
                    )
                );
            };
        }

        return $this->setStoreConfig($aData['driver'], $aData, $bDefault);
    }

    /**
     * @param bool $bSerialize
     * @param bool $bDefault
     * @param array $aData
     * @return $this
     */
    public function setArrayConfig
    (
        bool  $bSerialize,
        bool  $bDefault = false,
        array $aData = []
    ): self
    {
        if (empty($aData['driver'])) {
            $aData['driver'] = 'array';
        }
        $aData['serialize'] = $bSerialize;
        if (
            $aData['driver'] !== 'array' && (empty($aData['extend']) || !$aData['extend'] instanceof \Closure)) {
            $aData['extend'] = function ($oApp, $aConfig) {
                return $this->repository(
                    new \Autoframe\Components\SocketCache\LaravelPort\Cache\ArrayStore(
                        $aConfig['serialize'] ?? false
                    )
                );
            };
        }
        return $this->setStoreConfig($aData['driver'], $aData, $bDefault);
    }

    public function testApc(): bool
    {
        return function_exists('apcu_fetch') || function_exists('apc_fetch');
    }

    /**
     * @param bool $bDefault
     * @return $this
     * @throws AfrException
     */
    public function setApcConfig(bool $bDefault = false): self
    {
        if (!$this->testApc()) {
            throw new AfrException('REQUIRED APC EXTENSION ext-apcu in ' . __CLASS__);
        }
        return $this->setStoreConfig('apc', ['driver' => 'apc'], $bDefault);

    }

    /**
     * @param bool $bDefault
     * @return $this
     */
    public function setNullConfig(bool $bDefault = false): self
    {
        return $this->setStoreConfig('null', ['driver' => 'null'], $bDefault);
    }


    public function setFileConfig
    (
        bool  $bDefault = false,
        array $aData = []
    ): self
    {
        if (empty($aData['driver'])) {
            $aData['driver'] = 'file';
        }
        if ($aData['driver'] === 'file' && empty($this->aArrayAccessData['files']) && !empty($aData['files'])) {
            $this->aArrayAccessData['files'] = $aData['files']; //CacheManager legacy method
        }
        if (empty($this->aArrayAccessData['files'])) {
            $this->aArrayAccessData['files'] = new \Autoframe\Components\SocketCache\LaravelPort\Filesystem\Filesystem();
        }

        if (
            $aData['driver'] !== 'file' && (empty($aData['extend']) || !$aData['extend'] instanceof \Closure)) {
            $aData['extend'] = function ($oApp, $aConfig) {
                return $this->repository(
                    new \Autoframe\Components\SocketCache\LaravelPort\Cache\FileStore(
                        $aConfig['files'], // $oApp['files'],
                        $aConfig['path'],
                        $aConfig['permission'] ?? null
                    )
                );
            };
        }
        return $this->setStoreConfig($aData['driver'], $aData, $bDefault);
    }

    public function testMemcached(): bool
    {
        return class_exists('\Memcached');
    }

    public function setMemcachedConfig
    (
        bool  $bDefault = false,
        array $aData = []
    ): self
    {
        if (!$this->testMemcached()) {
            throw new AfrException('REQUIRED PHP EXTENSION ext-memcached in ' . __CLASS__);
        }

        $aDefaults = [
            'driver' => 'memcached',
            'persistent_id' => 'memcached_pool_id',
            //'persistent_id' => 1,
            /*'sasl' => [
                $this->env('MEMCACHIER_USERNAME'),
                $this->env('MEMCACHIER_PASSWORD'),
            ],*/
            'options' => [
                // some nicer default options
                /*    // - nicer TCP options
                    \Memcached::OPT_TCP_NODELAY => TRUE,
                    \Memcached::OPT_NO_BLOCK => FALSE,
                    // - timeouts
                    \Memcached::OPT_CONNECT_TIMEOUT => 2000,    // ms
                    \Memcached::OPT_POLL_TIMEOUT => 2000,       // ms
                    \Memcached::OPT_RECV_TIMEOUT => 750 * 1000, // us
                    \Memcached::OPT_SEND_TIMEOUT => 750 * 1000, // us
                    // - better failover
                    \Memcached::OPT_DISTRIBUTION => \Memcached::DISTRIBUTION_CONSISTENT,
                    \Memcached::OPT_LIBKETAMA_COMPATIBLE => TRUE,
                    \Memcached::OPT_RETRY_TIMEOUT => 2,
                    \Memcached::OPT_SERVER_FAILURE_LIMIT => 1,
                    \Memcached::OPT_AUTO_EJECT_HOSTS => TRUE,
    */
            ],
            'servers' => $this->parseMemcachedServers(
                $this->env('MEMCACHIER_SERVERS', 'localhost:11211')
            )
        ];

        if (empty($aData['driver'])) {
            $aData['driver'] = $aDefaults['driver'];
        }
        if (empty($aData['servers'])) {
            $aData['servers'] = $aDefaults['servers'];
        }
        if (!isset($aData['options'])) {
            $aData['options'] = $aDefaults['options'];
        }
        if (!isset($aData['persistent_id'])) {
            $aData['persistent_id'] = $aDefaults['persistent_id'];
        }
        if (!isset($aData['sasl']) && !empty($aDefaults['sasl'])) {
            $aData['sasl'] = [];
            if (strlen(implode('', $aDefaults['sasl'])) > 1) {
                $aData['sasl'] = $aDefaults['sasl'];
            }
        }


        if (empty($this->aArrayAccessData['memcached.connector'])) {
            $this->aArrayAccessData['memcached.connector'] =
                new \Autoframe\Components\SocketCache\LaravelPort\Cache\MemcachedConnector(); //singleton preferred
        }

        if ( //second custom connection
            $aData['driver'] !== 'memcached' && (empty($aData['extend']) || !$aData['extend'] instanceof \Closure)) {
            $aData['extend'] = function ($oApp, $aConfig) {
                return $this->repository(
                    new \Autoframe\Components\SocketCache\LaravelPort\Cache\MemcachedStore(
                    //$this->app['memcached.connector']->connect
                        $oApp['memcached.connector']->connect(
                            $aConfig['servers'],
                            $aConfig['persistent_id'] ?? null,
                            $aConfig['options'] ?? [],
                            array_filter($aConfig['sasl'] ?? [])
                        ),
                        $this->getPrefix($aConfig)
                    )
                );
            };
        }
        return $this->setStoreConfig($aData['driver'], $aData, $bDefault);
    }

    public function parseMemcachedServers(string $sServerList = 'localhost:11211:100'): array
    {
        return array_map(function ($s) {
            $parts = explode(":", $s);
            return [
                'host' => $parts[0],
                'port' => $parts[1],
                'weight' => $parts[2] ?? 100,
            ];
        }, explode(",", $sServerList));
    }

    protected function addAppDatabaseRedisConfig(array $aRedisDbConfig = []): void
    {
        // composer require predis/predis:^2.0
        if (empty($this->aArrayAccessData['database.redis'])) {
            if (!empty($aRedisDbConfig)) {
                $this->aArrayAccessData['database.redis'] = $aRedisDbConfig;
                return;
            }
            $this->aArrayAccessData['database.redis'] = [

                'client' => $this->env('REDIS_CLIENT', 'phpredis'),

                'options' => [
                    'cluster' => $this->env('REDIS_CLUSTER', 'redis'),
                    'prefix' => $this->env('REDIS_PREFIX', $this->aArrayAccessData['cache.prefix'] . '__database_'),
                    //    'serializer' => Redis::SERIALIZER_MSGPACK, //    supported serializers include: Redis::SERIALIZER_NONE (default), Redis::SERIALIZER_PHP, Redis::SERIALIZER_JSON, Redis::SERIALIZER_IGBINARY, and Redis::SERIALIZER_MSGPACK.
                    //    'compression' => Redis::COMPRESSION_LZ4, //Supported compression algorithms include: Redis::COMPRESSION_NONE (default), Redis::COMPRESSION_LZF, Redis::COMPRESSION_ZSTD, and Redis::COMPRESSION_LZ4.
                ],

                'default' => [
                    // 'url' => 'tcp://127.0.0.1:6379?database=0',
                    'scheme' => 'tcp',
                    'url' => $this->env('REDIS_URL'),
                    'host' => $this->env('REDIS_HOST', '127.0.0.1'),
                    'username' => $this->env('REDIS_USERNAME'),
                    'password' => $this->env('REDIS_PASSWORD'),
                    'port' => $this->env('REDIS_PORT', '6379'),
                    'database' => $this->env('REDIS_DB', '0'),
                    //    'read_write_timeout' => 60,

                ],

                'cache' => [
                    // 'url' => 'tls://user:password@127.0.0.1:6380?database=1',
                    'scheme' => 'tls',
                    'url' => $this->env('REDIS_URL'),
                    'host' => $this->env('REDIS_HOST', '127.0.0.1'),
                    'username' => $this->env('REDIS_USERNAME'),
                    'password' => $this->env('REDIS_PASSWORD'),
                    'port' => $this->env('REDIS_PORT', '6379'),
                    'database' => $this->env('REDIS_CACHE_DB', '1'),
                    //    'read_write_timeout' => 60,

                ],

                //clustering
                'clusters' => [
                    'default' => [
                        [
                            'url' => $this->env('REDIS_URL'),
                            'host' => $this->env('REDIS_HOST', '127.0.0.1'),
                            'username' => $this->env('REDIS_USERNAME'),
                            'password' => $this->env('REDIS_PASSWORD'),
                            'port' => $this->env('REDIS_PORT', '6379'),
                            'database' => $this->env('REDIS_DB', '0'),
                            //    'read_write_timeout' => 60,
                        ],
                    ],
                ],

            ];
        }
    }

    public function testRedis(): bool
    {
        return class_exists('\Redis') || class_exists('\Predis\Client'); //TODO relly test :)
    }

    /**
     * @param bool $bDefault
     * @param array $aData
     * @param array $aRedisDbConfig
     * @return $this
     * @throws AfrException
     */
    public function setRedisConfig
    (
        bool  $bDefault = false,
        array $aData = [],
        array $aRedisDbConfig = []
    ): self
    {
        if (!$this->testRedis()) {
            throw new AfrException(
                'RUN: `composer require predis/predis:^2.0` || ext-redis ~ because class Redis not found in:' . __CLASS__
            );
        }

        //TODO test, and fix and implement...
        if (empty($aData['driver'])) {
            $aData['driver'] = 'redis';
        }
        if (!empty($aData['redis']) && empty($this->aArrayAccessData['redis'])) {
            $this->aArrayAccessData['redis'] = $aData['redis']; //magic redis instance :D
        }


        if (empty($this->aArrayAccessData['redis'])) {
            if (empty($this->aArrayAccessData['database.redis'])) {
                if (!empty($aRedisDbConfig)) {
                    $this->addAppDatabaseRedisConfig($aRedisDbConfig);
                } else {
                    throw new AfrException(
                        'Please configure ' . __CLASS__ . '[database.redis] = [...] SEE addAppDatabaseRedisConfig'
                    );
                }
            }
            $aDbRedisCfg = $this->aArrayAccessData['database.redis'];
            $sRedisDriver = !empty($aDbRedisCfg['client']) ? $aDbRedisCfg['client'] : 'phpredis';
            unset($aDbRedisCfg['client']);

            $this->aArrayAccessData['redis'] =
                new \Autoframe\Components\SocketCache\LaravelPort\Redis\RedisManager(
                    $this,
                    $sRedisDriver,
                    $aDbRedisCfg
                );
        }

        if ( //TODO: test REDIS alt config, because it may not work...
            $aData['driver'] !== 'redis' && (empty($aData['extend']) || !$aData['extend'] instanceof \Closure)) {
            $aData['extend'] = function ($oApp, $aConfig) {
                $connection = $aConfig['connection'] ?? 'default';
                $store = new \Autoframe\Components\SocketCache\LaravelPort\Cache\RedisStore(
                    $oApp['redis'],
                    $this->getPrefix($aConfig),
                    $connection
                );
                return $this->repository(
                    $store->setLockConnection($aConfig['lock_connection'] ?? $connection)
                );
            };
        }
        return $this->setStoreConfig($aData['driver'], $aData, $bDefault);
    }


    public function bound($mData): bool
    {
        if ($mData === 'events') {
            return false; //TODO: REDIS events 
        }
        return isset($this->aArrayAccessData[(string)$mData]);
    }

    public function make($mData) //TODO  REDIS
    {
        return;
    }

    /**
     * @param string $key
     * @param $default
     * @return mixed|null
     */
    public function env(string $key, $default = null)
    {
        if (!empty($this->oEnvGetterClosure)) {
            $mData = ($this->oEnvGetterClosure)(...func_get_args());
            if (!empty($mData)) {
                return $mData;
            }
        }
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        return $default;
    }


    public function setEnvGetterClosure(\Closure $fn): void
    {
        $this->oEnvGetterClosure = $fn;
    }


}