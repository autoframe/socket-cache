<?php
declare(strict_types=1);

namespace Autoframe\Components\SocketCache\Client;

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;
use Autoframe\Components\SocketCache\Common\AfrCacheSocketStore;
use Autoframe\Components\SocketCache\Exception\AfrCacheSocketException;

class AfrClientStore extends AfrCacheSocketStore
{
    protected AfrSocketClient $oClient;
    protected array $aSockResponse = [];

    /**
     * @throws AfrCacheSocketException
     */
    public function __construct(AfrCacheSocketConfig $oSocketConfig)
    {
        parent::__construct($oSocketConfig);
        $this->oClient = new AfrSocketClient($oSocketConfig);
    }

    /**
     * @return AfrSocketClient
     */
    public function _getSocketClient(): AfrSocketClient
    {
        return $this->oClient;
    }

    /**
     * @return AfrCacheSocketConfig
     */
    public function _getCacheSocketConfig(): AfrCacheSocketConfig
    {
        return $this->oSocketConfig;
    }

    /**
     * @return array
     */
    public function shutdownServer(): array
    {
        $r = $this->aSockResponse = $this->oClient->sendRequest('shutdown');
        if(class_exists('\Autoframe\Components\SocketCache\Facade\AfrCache')){
            \Autoframe\Components\SocketCache\Facade\AfrCache::getManager()->forgetDriver($this->oSocketConfig->driver);
        }
        AfrSocketClient::$aAutoServerUpSent[$this->oSocketConfig->driver] = false;
        sleep(1);
        return $r;
    }

    /**
     * @return array
     */
    public function getServerStats(): array
    {
        return $this->aSockResponse = $this->oClient->sendRequest('stats');
    }



    /**
     * @param string $sFn
     * @param array $aArgs
     * @return mixed|null
     */
    protected function genericStoreAction(string $sFn, array $aArgs)
    {
        $this->aSockResponse = $this->oClient->sendRequest(
            serialize(array_merge(
                [$sFn],
                $aArgs
            ))
        );

        //$this->aSockResponse = ['{serialized data}', success: false/true, 'err info' ];
        if (count($this->aSockResponse) > 0 && $this->aSockResponse[0] === 'N;') {
            return null;
        }
        if (!empty($this->aSockResponse[1])) {
            if (is_string($this->aSockResponse[0])) {
                if (
                    substr($this->aSockResponse[0], 1, 1) === ':' &&
                    strpos('sidbaO', substr($this->aSockResponse[0], 0, 1)) !== false
                ) {
                    //successful return as serialized
                    return unserialize($this->aSockResponse[0]);
                }


            }
            return $this->aSockResponse[0];
        }
        return null;
    }

    /**
     * @return array This will return the last information or error from the socket client
     * This will return the last information received from the socket client
     */
    public function getSockResponse(): array
    {
        return $this->aSockResponse;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string|array $key
     * @param2 Default return as parameter overload
     * @return mixed
     */
    public function get($key)
    {
        return $this->genericStoreAction(__FUNCTION__, func_get_args());
    }


    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @param array $keys
     * @return array
     */
    public function many(array $keys): array
    {
        return (array)$this->genericStoreAction(__FUNCTION__, func_get_args());
        //return (array)$this->get($keys);
    }


    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     * @return bool
     */
    public function put($key, $value, $seconds): bool
    {
        return (bool)$this->genericStoreAction(
            __FUNCTION__,
            [(string)$key, $value, (int)$seconds]
        );
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param array $values
     * @param int $seconds
     * @return bool
     */
    public function putMany(array $values, $seconds): bool
    {
        return (bool)$this->genericStoreAction(__FUNCTION__, [$values, (int)$seconds]);
    }


    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        $mReturn = $this->genericStoreAction(__FUNCTION__, [(string)$key, (int)$value]);
        return is_integer($mReturn) || is_bool($mReturn) ? $mReturn : false;
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        return $this->increment($key, (int)$value * -1);
    }


    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function forever($key, $value): bool
    {
        return (bool)$this->genericStoreAction(__FUNCTION__, [(string)$key, $value]);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget($key): bool
    {
        return (bool)$this->genericStoreAction(__FUNCTION__, [(string)$key]);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush(): bool
    {
        return (bool)$this->genericStoreAction(__FUNCTION__, []);
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return '';   //TODO not supported yet
        //return (string)$this->genericStoreAction(__FUNCTION__, []);
    }


    /**
     * @param string $sKey
     * @param int $iDelay
     * @return bool
     */
    public function delete(string $sKey, int $iDelay = 0): bool
    {
        return (bool)$this->genericStoreAction(__FUNCTION__, [$sKey, $iDelay]);
    }

    /**
     * @return array
     */
    public function getAllKeys(): array
    {
        return (array)$this->genericStoreAction(__FUNCTION__, []);
    }

    /**
     * @return array
     */
    public function getMemoryUsageInfo(): array
    {
        return (array)$this->genericStoreAction(__FUNCTION__, [true]);
    }
}