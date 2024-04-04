<?php

namespace Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache;

interface LockProvider
{
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Autoframe\Components\SocketCache\LaravelPort\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null);

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Autoframe\Components\SocketCache\LaravelPort\Cache\Lock
     */
    public function restoreLock($name, $owner);
}
