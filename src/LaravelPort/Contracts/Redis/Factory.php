<?php

namespace Autoframe\Components\SocketCache\LaravelPort\Contracts\Redis;

interface Factory
{
    /**
     * Get a Redis connection by name.
     *
     * @param  string|null  $name
     * @return \Autoframe\Components\SocketCache\LaravelPort\Redis\Connections\Connection
     */
    public function connection($name = null);
}
