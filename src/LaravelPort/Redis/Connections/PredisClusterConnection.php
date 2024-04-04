<?php

namespace Autoframe\Components\SocketCache\LaravelPort\Redis\Connections;

use Autoframe\Components\SocketCache\LaravelPort\Support\Tap;
use Predis\Command\ServerFlushDatabase;

class PredisClusterConnection extends PredisConnection
{
    /**
     * Flush the selected Redis database on all cluster nodes.
     *
     * @return void
     */
    public function flushdb()
    {
        $this->client->executeCommandOnNodes(
            Tap::tap(new ServerFlushDatabase)->setArguments(func_get_args())
        );
    }
}
