<?php
declare(strict_types=1);

namespace Autoframe\Components\SocketCache;

use Autoframe\Components\SocketCache\App\AfrCacheApp;
use Autoframe\Components\SocketCache\LaravelPort\Cache\CacheManager;
use InvalidArgumentException;


class AfrCacheManager extends CacheManager
{
    /**
     * Create a new Cache manager instance.
     */
    public function __construct($app)
    {
        if (empty($app)) {
            $this->app = AfrCacheApp::getInstance();
        } else {
            parent::__construct($app);
        }
    }

    /**
     * @param $name
     * @return LaravelPort\Contracts\Cache\Repository
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Cache store [{$name}] is not defined.");
        }
        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';
        if (
            !method_exists($this, $driverMethod) &&
            !isset($this->customCreators[$config['driver']]) &&
            isset($this->app['config']["cache.stores.{$name}"])
        ) {
            if (!empty($config['extend']) && $config['extend'] instanceof \Closure) {
                //'extend' Closure must return a new Repository
                $this->extend($name, $config['extend']);
            } elseif (!empty($config['closure']) && $config['closure'] instanceof \Closure) {
                $config['closure']->bindTo($this, $this);
                $config['closure']($this->app, $config);
            }
        }
        return parent::resolve($name);
    }
}