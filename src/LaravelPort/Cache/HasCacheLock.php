<?php

namespace Autoframe\Components\SocketCache\LaravelPort\Cache;

trait HasCacheLock
{
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return Lock
     */
    public function lock($name, $seconds = 0, $owner = null)
    {
        return new CacheLock($this, $name, $seconds, $owner);
    }

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return Lock
     */
    public function restoreLock($name, $owner)
    {
        return $this->lock($name, 0, $owner);
    }
}
