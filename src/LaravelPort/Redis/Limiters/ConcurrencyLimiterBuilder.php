<?php

namespace Autoframe\Components\SocketCache\LaravelPort\Redis\Limiters;

use Autoframe\Components\SocketCache\LaravelPort\Contracts\Redis\LimiterTimeoutException;
use Autoframe\Components\SocketCache\LaravelPort\Support\InteractsWithTime;

class ConcurrencyLimiterBuilder
{
    use InteractsWithTime;

    /**
     * The Redis connection.
     *
     * @var \Autoframe\Components\SocketCache\LaravelPort\Redis\Connections\Connection
     */
    public $connection;

    /**
     * The name of the lock.
     *
     * @var string
     */
    public $name;

    /**
     * The maximum number of entities that can hold the lock at the same time.
     *
     * @var int
     */
    public $maxLocks;

    /**
     * The number of seconds to maintain the lock until it is automatically released.
     *
     * @var int
     */
    public $releaseAfter = 60;

    /**
     * The amount of time to block until a lock is available.
     *
     * @var int
     */
    public $timeout = 3;

    /**
     * Create a new builder instance.
     *
     * @param  \Autoframe\Components\SocketCache\LaravelPort\Redis\Connections\Connection  $connection
     * @param  string  $name
     * @return void
     */
    public function __construct($connection, $name)
    {
        $this->name = $name;
        $this->connection = $connection;
    }

    /**
     * Set the maximum number of locks that can be obtained per time window.
     *
     * @param  int  $maxLocks
     * @return $this
     */
    public function limit($maxLocks)
    {
        $this->maxLocks = $maxLocks;

        return $this;
    }

    /**
     * Set the number of seconds until the lock will be released.
     *
     * @param  int  $releaseAfter
     * @return $this
     */
    public function releaseAfter($releaseAfter)
    {
        $this->releaseAfter = $this->secondsUntil($releaseAfter);

        return $this;
    }

    /**
     * Set the amount of time to block until a lock is available.
     *
     * @param  int  $timeout
     * @return $this
     */
    public function block($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Execute the given callback if a lock is obtained, otherwise call the failure callback.
     *
     * @param  callable  $callback
     * @param  callable|null  $failure
     * @return mixed
     *
     * @throws \Autoframe\Components\SocketCache\LaravelPort\Contracts\Redis\LimiterTimeoutException
     */
    public function then(callable $callback, callable $failure = null)
    {
        try {
            return (new ConcurrencyLimiter(
                $this->connection, $this->name, $this->maxLocks, $this->releaseAfter
            ))->block($this->timeout, $callback);
        } catch (LimiterTimeoutException $e) {
            if ($failure) {
                return $failure($e);
            }

            throw $e;
        }
    }
}
