<?php

namespace Autoframe\Components\SocketCache;

use Autoframe\Components\SocketCache\Exception\AfrCacheSocketException;
use Autoframe\Process\Control\Lock\AfrLockFileClass;
use Autoframe\Process\Control\Worker\Background\AfrBackgroundWorkerClass;

trait AfrCacheSocketConfigStatic
{

    /**
     * @var AfrCacheSocketConfig[]
     */
    protected static array $aInstances = [];
    protected static array $aFactoryParameters = [];


    /**
     * @param string $sName
     * @return bool
     */
    public static function hasConfig(string $sName): bool
    {
        return
            !empty(self::$aInstances[$sName]) ||
            !empty(self::$aFactoryParameters[$sName]);
    }

    /**
     * @param string|array $mPropertiesOrDriverName
     * @return void
     * @throws AfrCacheSocketException
     */
    public static function setSockConfigParameters($mPropertiesOrDriverName)
    {
        if (!empty($mPropertiesOrDriverName['driver']) && is_string($mPropertiesOrDriverName['driver'])) {
            $driver = $mPropertiesOrDriverName['driver'];
            self::$aFactoryParameters[$driver] = $mPropertiesOrDriverName;
            if (!empty(self::$aInstances[$driver])) {
                self::$aInstances[$driver]->extend($mPropertiesOrDriverName);
            }
        } elseif (!empty($mPropertiesOrDriverName) && is_string($mPropertiesOrDriverName)) {
            $driver = $mPropertiesOrDriverName;
            self::$aFactoryParameters[$driver] = ['driver' => $driver]; //all default!
        } else {
            throw new AfrCacheSocketException(
                'Please provide a unique configuration + driver name for the class ' . __CLASS__
            );
        }


    }

    /**
     * We use only the default class settings if in_array($sName,['local','default','dev'])
     * @param string $sDriver
     * @return AfrCacheSocketConfig
     * @throws AfrCacheSocketException
     */
    public static function getConfigInstance(string $sDriver): AfrCacheSocketConfig
    {
        if (!empty(self::$aInstances[$sDriver])) {
            return self::$aInstances[$sDriver];
        }

        if (empty(self::$aInstances[$sDriver]) && !empty(self::$aFactoryParameters[$sDriver])) {
            self::$aInstances[$sDriver] = new static(self::$aFactoryParameters[$sDriver]);
        //    unset(self::$aFactoryParameters[$sDriver]);
        }

        if (empty(self::$aInstances[$sDriver]) || !self::$aInstances[$sDriver] instanceof AfrCacheSocketConfig) {
            throw new AfrCacheSocketException(
                'Instance not found for  ' . static::class.print_r(array_keys(self::$aInstances),true)
            );
        }
        return self::$aInstances[$sDriver];
    }


    public static function serverUp(AfrCacheSocketConfig $oConfigInstance): void
    {
        if ($pf = @fsockopen(
            $oConfigInstance->socketIp,
            $oConfigInstance->socketPort,
            $err,
            $err_string,
            1 / 250)
        ) {
            fclose($pf);
            return;
        }

        //debug_print_backtrace();

        $oLock = new AfrLockFileClass(
            $oConfigInstance->socketIp . '-' . $oConfigInstance->socketPort
        );
        if (!$oLock->isLocked() && $oLock->obtainLock()) {
            AfrBackgroundWorkerClass::execWithArgs(
                '-d memory_limit=' .
                $oConfigInstance->iServerMemoryMb . 'M ' .
                __DIR__ . DIRECTORY_SEPARATOR . 'Server' . DIRECTORY_SEPARATOR . 'serverUp.php ' .
                base64_encode(serialize($oConfigInstance))
            );
            sleep(1);
            $oLock->releaseLock();
        }

    }

}