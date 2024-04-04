<?php

namespace Autoframe\Components\SocketCache\Server;

use Autoframe\Components\SocketCache\Common\AfrCacheSocketStore;

class AfrServerStore extends AfrCacheSocketStore
{
    use MemoryInfo;

    protected array $aStore = []; //all stored values in memory; [self::TS] is the expire timestamp and [self::VALUE] is the value
    protected int $iGCTime = 0; //last time the garbage man was called

    protected array $aClassMethods = []; //methods are stored as keys
    protected array $aClassMethodsExcludes = [
        '__construct',
        '__destruct',
        '__sleep',
        'getFromStore',
        'setInStore',
        'garbageMan',
    ];


    protected int $iHits = 0;
    protected int $iMisses = 0;
    protected int $iEvicted = 0;
    protected bool $bEvictionCheckFlag = false;


    protected function methodIsCallable(string $sMethod): string
    {
        if (empty($this->aClassMethods)) {
            $this->aClassMethods = array_flip(
                array_diff(
                    get_class_methods(get_class($this)),
                    $this->aClassMethodsExcludes
                )
            );
        }
        return !empty($this->aClassMethods[$sMethod]) ? $sMethod : '';
    }

    /**
     * Cleans up the expired cache
     * @param int $iTime
     * @return int
     */
    protected function garbageMan(int $iTime = 0): int
    {
        $iExpired = 0;
        $iTime = $iTime === 0 ? time() : $iTime;
        if ($this->iGCTime < $iTime) {
            $this->iGCTime = $iTime;
            $aKeys = array_keys($this->aStore);
            foreach ($aKeys as $sKey) {
                if ($this->isKeyExpired($sKey, $iTime)) {
                    unset($this->aStore[$sKey]);
                    $iExpired++;
                }
            }
        }
        $this->evictionProcedure();
        return $iExpired;
    }

    protected function evictionProcedure(): int
    {
        $fGreen = $this->oSocketConfig->aEvictionPercentRange['GREEN'];
        $fYellow = $this->oSocketConfig->aEvictionPercentRange['YELLOW'];
        $fRed = $this->oSocketConfig->aEvictionPercentRange['RED'];

        $aMemInfo = $this->getMemoryUsageInfo($this->bEvictionCheckFlag);

        if ($aMemInfo['fUsedPercent'] > $fYellow) {
            $this->bEvictionCheckFlag = true;
        } elseif ($aMemInfo['fUsedPercent'] < $fGreen) {
            $this->bEvictionCheckFlag = false;
        }

        if ($this->bEvictionCheckFlag && $aMemInfo['fUsedPercent'] > $fRed) {
            $aKeysTs = [];
            foreach ($this->aStore as $k => $aV) {
                if (empty($aKeysTs[$aV[self::TS]])) {
                    $aKeysTs[$aV[self::TS]] = [];
                }
                $aKeysTs[$aV[self::TS]][] = $k;
            }
            ksort($aKeysTs);
            if (count($aKeysTs)) {
                foreach ($aKeysTs as $iTs => &$aKeys) {
                    foreach ($aKeys as $sKeyToEvict) {
                        unset($this->aStore[$sKeyToEvict]);
                        $this->iEvicted++;
                    }
                    $aKeys = [];
                    $aMemInfo = $this->getMemoryUsageInfo(true);
                    if ($aMemInfo['fUsedPercent'] < $fYellow) {
                        unset($aKeysTs, $aMemInfo);
                        break;
                    }
                }
            }
        }
        return $this->iEvicted;
    }

    /**
     * @param string $sKey
     * @param int $iTime
     * @return bool
     */
    protected function isKeyExpired(string $sKey, int $iTime = 0): bool
    {
        if (!isset($this->aStore[$sKey])) {
            return true;
        }
        $iTime = $iTime < 1 ? time() : $iTime;
        return $this->aStore[$sKey][self::TS] < $iTime;
    }

    /**
     * Checks is the key exits and gets the data
     * @param string $sKey
     * @param int $iTime
     * @return array
     */
    protected function getFromStore(string $sKey, int $iTime = 0): array
    {
        $this->garbageMan($iTime);
        if ($this->isKeyExpired($sKey, $iTime)) {
            unset($this->aStore[$sKey]);
        }
        if (isset($this->aStore[$sKey])) {
            $this->iHits++;
            return [
                $this->aStore[$sKey][self::TS], //expireTs
                $this->aStore[$sKey][self::VALUE], //mValue
                true //bExists
            ];
        }
        $this->iMisses++;
        return [
            0, //expireTs
            null, //mValue
            false //bExists
        ];
    }

    /**
     * @param string $sKey the complete merged key containing prefixes, etc
     * @param $mValue mixed
     * @param int $iDeltaExpire the amount of seconds to be valid
     * @param int $iTime app local time
     * @return void
     */
    protected function setInStore(string $sKey, $mValue, int $iDeltaExpire = 0, int $iTime = 0): void
    {
        $iTime = $iTime < 1 ? time() : $iTime;
        $this->garbageMan($iTime);
        $iExpire = min(max(self::MINTS, $iDeltaExpire + $iTime), self::MAXTS);
        if ($iExpire < $iTime && isset($this->aStore[$sKey])) { //expire negative time values
            unset($this->aStore[$sKey]);
            return;
        }
        $this->aStore[$sKey] = [$iExpire, $mValue];
    }

    /**
     * @return array
     */
    public function getAllKeys(): array
    {
        $this->garbageMan();
        return array_keys($this->aStore);
    }

    /**
     * serializedAction = serialize(['method_name','arg.A','arg.B',...]) from client side
     * @param string $serializedAction
     * @return mixed
     */
    public function takeAction(string $serializedAction)
    {
        $aAction = (array)unserialize($serializedAction);

        $sMethod = array_shift($aAction);
        $sMethod = $this->methodIsCallable((string)$sMethod); //TODO error handling?

        return $sMethod !== '' ? $this->$sMethod(...$aAction) : null;

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
        $iTime = time();
        if (is_iterable($key)) {
            return $this->many(...func_get_args());
        }

        $aStoreValue = $this->getFromStore((string)$key, $iTime);
        if (!$aStoreValue[self::EXISTS]) {
            $aArgs = func_get_args();
            if (isset($aArgs[1])) {
                return $aArgs[1]; //default value provided as argument overload
            }
        }
        return $aStoreValue[self::VALUE];
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
        //return $this->get($keys);
        $iTime = time();
        $aReturn = [];
        foreach ($keys as $k => $v) {
            if (is_integer($k)) {
                //key is int, so we have no default values
                $aReturn[$v] = $this->getFromStore($v, $iTime)[self::VALUE];
            } else {
                //key is string, so we check for existing values or get the default from $v
                $k = (string)$k;
                $aStoreValue = $this->getFromStore($k, $iTime);
                $aReturn[$k] = $aStoreValue[self::EXISTS] ? $aStoreValue[self::VALUE] : $v;
                unset($aStoreValue);
            }
        }
        return $aReturn;
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
        $seconds = (int)$seconds;
        $this->setInStore((string)$key, $value, $seconds);
        return isset($this->aStore[$key]);
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
        $manyResult = true;
        $seconds = (int)$seconds;
        foreach ($values as $key => $value) {
            $manyResult = $this->put($key, $value, $seconds) && $manyResult;
        }
        return $manyResult;
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
        if(0){
            //bad implementation
             $aStoreValue = $this->getFromStore($key);
             if (!$aStoreValue[self::EXISTS]) {
                 return false;
             }
             $this->setInStore(
                 $key,
                 $aStoreValue[self::VALUE],
                 $aStoreValue[self::TS] + (int)$value - time()
             );
             $this->iHits--;//count fix
             return $this->getFromStore($key)[self::TS];
        }
        $key = (string)$key;
        $aStoreValue = $this->getFromStore($key);
        $mStoredValue = !$aStoreValue[self::EXISTS] ? 0 : $aStoreValue[self::VALUE];
        $iNewStoredValue = (int)($mStoredValue + $value);
        $this->setInStore((string)$key, $iNewStoredValue, self::MAXTS);
        $this->iHits--;//count fix
        return $iNewStoredValue;
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
        $this->setInStore((string)$key, $value, self::MAXTS);
        return true;
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget($key): bool
    {
        $key = (string)$key;
        $bExists = !$this->isKeyExpired($key);
        if ($bExists) {
            $this->setInStore($key, null, self::MINTS); //expire
        }
        return $bExists;
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush(): bool
    {
        $this->aStore = [];
        return true;
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return ''; //TODO not supported yet
    }


    /**
     * @param string $sKey
     * @param int $iDelay
     * @return bool
     */
    public function delete(string $sKey, int $iDelay = 0): bool
    {
        $aStoreValue = $this->getFromStore($sKey);
        if (!$aStoreValue[self::EXISTS]) {
            return false;
        }
        $iDelay = max($iDelay, 0);
        if ($iDelay > 0) {
            $this->iHits--;//count fix
            $this->setInStore($sKey, $aStoreValue[self::VALUE], $iDelay);
        } else {
            $this->setInStore($sKey, null, self::MINTS);
        }
        return true;
    }


}