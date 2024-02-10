<?php

namespace Autoframe\Components\SocketCache;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;

class AfrCacheManager
{


    /**
     * Create a new cache repository with the given implementation.
     *
     * @param  \Illuminate\Contracts\Cache\Store  $store
     * @return \Illuminate\Cache\Repository
     */
    public function repository(Store $store)
    {
        return tap(new Repository($store), function ($repository) {
            $this->setEventDispatcher($repository);
        });
    }

    /**
     * Set the event dispatcher on the given repository instance.
     *
     * @param  \Illuminate\Cache\Repository  $repository
     * @return void
     */
    protected function setEventDispatcher(Repository $repository)
    {
        if (! $this->app->bound(DispatcherContract::class)) {
            return;
        }

        $repository->setEventDispatcher(
            $this->app[DispatcherContract::class]
        );
    }

}