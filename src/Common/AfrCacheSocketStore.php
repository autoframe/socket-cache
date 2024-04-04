<?php

namespace Autoframe\Components\SocketCache\Common;

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;
use Autoframe\Components\SocketCache\LaravelPort\Cache\TaggableStore;
use Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Store;

abstract class AfrCacheSocketStore extends TaggableStore implements Store
{

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



}
