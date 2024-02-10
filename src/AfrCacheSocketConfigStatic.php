<?php

namespace Autoframe\Components\SocketCache;

use Autoframe\Components\SocketCache\Exception\AfrCacheSocketException;
use Autoframe\Process\Control\Lock\AfrLockFileClass;
use Autoframe\Process\Control\Worker\Background\AfrBackgroundWorkerClass;

trait AfrCacheSocketConfigStatic
{
    protected static array $aInstances = [];
    protected static array $aFactoryParameters = [];


    /**
     * @param string $sName
     * @return bool
     */
    public static function hasConfig(string $sName): bool
    {
        return
            static::isLocalName($sName) ||
            !empty(self::$aInstances[$sName]) ||
            !empty(self::$aFactoryParameters[$sName]);
    }

    /**
     * @param $mPropertiesOrName
     * @return void
     * @throws AfrCacheSocketException
     */
    public static function prepareConfig($mPropertiesOrName)
    {
        if (!empty($mPropertiesOrName['sConfigName'])) {
            $sConfigName = $mPropertiesOrName['sConfigName'];
        } elseif (is_string($mPropertiesOrName) && strlen($mPropertiesOrName)) {
            $sConfigName = $mPropertiesOrName;
        } else {
            throw new AfrCacheSocketException(
                'Please provide a unique configuration key name for the class ' . __CLASS__
            );
        }
        self::$aFactoryParameters[$sConfigName] = $mPropertiesOrName;
    }

    /**
     * We use only the default class settings if in_array($sName,['local','default','dev'])
     * @param string $sName
     * @return AfrCacheSocketConfig
     * @throws AfrCacheSocketException
     */
    public static function makeConfig(string $sName): AfrCacheSocketConfig
    {
        if (static::isLocalName($sName) && empty(self::$aInstances[$sName]) && empty(self::$aFactoryParameters[$sName])) {
            self::$aInstances[$sName] = new static(self::$aFactoryParameters[$sName]);
        }

        if (empty(self::$aInstances[$sName]) && !empty(self::$aFactoryParameters[$sName])) {
            self::$aInstances[$sName] = new static(self::$aFactoryParameters[$sName]);
            unset(self::$aFactoryParameters[$sName]);
        }

        if (empty(self::$aInstances[$sName]) || !self::$aInstances[$sName] instanceof AfrCacheSocketConfig) {
            throw new AfrCacheSocketException(
                'Instance not found for  ' . static::class
            );
        }
        return self::$aInstances[$sName];
    }

    protected static function isLocalName(string $sName): bool
    {
        return in_array($sName, ['local', 'default', 'dev']);
    }

    public static function serverUp(AfrCacheSocketConfig $oConfigInstance): void
    {
        $oLock = new AfrLockFileClass($oConfigInstance->getLockName());
        if (!$oLock->isLocked()) {
            AfrBackgroundWorkerClass::execWithArgs(
                '-d memory_limit=' .
                $oConfigInstance->iServerMemoryMb . 'M ' .
                __DIR__ . DIRECTORY_SEPARATOR . 'Server' . DIRECTORY_SEPARATOR . 'serverUp.php ' .
                base64_encode(serialize($oConfigInstance))
            );
        }

    }

}