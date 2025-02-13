<?php

namespace Autoframe\Components\SocketCache\LaravelPort\Cache;

class ArrayLock extends Lock
{
    /**
     * The parent array cache store.
     *
     * @var \Autoframe\Components\SocketCache\LaravelPort\Cache\ArrayStore
     */
    protected $store;

    /**
     * Create a new lock instance.
     *
     * @param  \Autoframe\Components\SocketCache\LaravelPort\Cache\ArrayStore  $store
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return void
     */
    public function __construct($store, $name, $seconds, $owner = null)
    {
        parent::__construct($name, $seconds, $owner);

        $this->store = $store;
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire()
    {
        $expiration = $this->store->locks[$this->name]['expiresAt'] ?? time()+1;

        if ($this->exists() && $expiration->isFuture()) {
            return false;
        }

        $this->store->locks[$this->name] = [
            'owner' => $this->owner,
            'expiresAt' => $this->seconds === 0 ? null : time()+$this->seconds,
        ];

        return true;
    }

    /**
     * Determine if the current lock exists.
     *
     * @return bool
     */
    protected function exists()
    {
        return isset($this->store->locks[$this->name]);
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release()
    {
        if (! $this->exists()) {
            return false;
        }

        if (! $this->isOwnedByCurrentProcess()) {
            return false;
        }

        $this->forceRelease();

        return true;
    }

    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return string
     */
    protected function getCurrentOwner()
    {
        return $this->store->locks[$this->name]['owner'];
    }

    /**
     * Releases this lock in disregard of ownership.
     *
     * @return void
     */
    public function forceRelease()
    {
        unset($this->store->locks[$this->name]);
    }
}
