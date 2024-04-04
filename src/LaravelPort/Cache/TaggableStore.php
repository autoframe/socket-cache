<?php

namespace Autoframe\Components\SocketCache\LaravelPort\Cache;

use Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Store;

abstract class TaggableStore implements Store
{
    /**
     * Begin executing a new tags operation.
     *
     * @param  array|mixed  $names
     * @return \Autoframe\Components\SocketCache\LaravelPort\Cache\TaggedCache
     */
    public function tags($names)
    {
        return new TaggedCache($this, new TagSet($this, is_array($names) ? $names : func_get_args()));
    }
}
