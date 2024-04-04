<?php

namespace Autoframe\Components\SocketCache\Facade;

use Autoframe\Components\SocketCache\App\AfrCacheApp;
use Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Repository;

class AfrRepositoryAutoSelector
{
    // !! use only 2 chars for constants / indexes :D
    const HIGH_LOAD = 'H1';
    const SECONDARY_LOAD = 'H2';
    const FILESYSTEM = 'F1';
    const FILESYSTEM2 = 'F2';
    const RAM = 'R0';
    const NONE = 'N0';

    public static array $aLoadTypes;

    public static function setToUseRepositories(
        string $sType,
        array  $aDesiredRepositoryNames
    ): array
    {
        if (empty($aDesiredRepositoryNames)) {
            if ($sType === static::HIGH_LOAD) {
                $aDesiredRepositoryNames = [
                    'afrsock',
                    'memcached',
                    'redis',
                    'apc',
                ];
            } elseif ($sType === static::SECONDARY_LOAD) {
                $aDesiredRepositoryNames = [
                    'afrsock2',
                    'memcached',
                    'apc',
                ];
            } elseif ($sType === static::RAM) {
                $aDesiredRepositoryNames = ['array'];
            } elseif ($sType === static::FILESYSTEM || $sType === static::FILESYSTEM2) {
                $aDesiredRepositoryNames = ['file'];
            } else {
                $aDesiredRepositoryNames = ['null'];
            }
        }
        return static::$aLoadTypes[$sType] = static::matchAvailable($aDesiredRepositoryNames);
    }

    /**
     * @param string $sKey H1\2\key or H1\key or default repository
     * @param int $iPriority second ns level or 1
     * @return Repository
     */
    public static function selectRepoByKeyNs(string $sKey, int $iPriority = 1): Repository
    {
        if (substr($sKey, 2, 1) === '\\') {
            $sNsType = substr($sKey, 0, 2);
            if (!static::validType($sNsType)) {
                return AfrCache::getManager()->store();
            }
            if (substr($sKey, 4, 1) === '\\') {
                $iCheckPriority = (int)substr($sKey, 3, 1);
                if ($iCheckPriority > 0) {
                    $iPriority = $iCheckPriority;
                }
            }
            return static::getRepositoryByPriority($sNsType, $iPriority);
        }
        return AfrCache::getManager()->store();
    }


    /**
     * @param string $sKey
     * @param string $sType constant values H1, H2, F1, etc
     * @param int $iPriority [0-9]
     * @return string H1\1\key or H1\key or key
     */
    public static function prefixKeyForRepo(string $sKey, string $sType = '', int $iPriority = 0): string
    {
        if ($sType && static::validType($sType)) {
            return $sType . '\\' . ($iPriority > 0 ? $iPriority . '\\' : '') . $sKey;
        }
        return $sKey;
    }

    public static function getRepositoryByPriority(string $sType = '', int $iPriority = 1): Repository
    {
        if (!empty($sType)) {
            $aRepositories = static::getXLoadRepository($sType);
            $iPriority = max(min($iPriority, count($aRepositories)), 1);
            $i = 1;
            foreach ($aRepositories as $sRepository => $sDriver) {
                if ($i === $iPriority) {
                    return AfrCache::getManager()->store($sRepository);
                }
                $i++;
            }
        }

        return AfrCache::getManager()->store();
    }

    public static function getRepositoryHigh(int $iPriority = 1): Repository
    {
        return static::getRepositoryByPriority(static::HIGH_LOAD, $iPriority);
    }

    public static function getRepositorySecondary(int $iPriority = 1): Repository
    {
        return static::getRepositoryByPriority(static::SECONDARY_LOAD, $iPriority);
    }

    public static function getRepositoryFS(int $iPriority = 1): Repository
    {
        return static::getRepositoryByPriority(static::FILESYSTEM, $iPriority);
    }

    public static function getRepositoryFS2(int $iPriority = 1): Repository
    {
        return static::getRepositoryByPriority(static::FILESYSTEM2, $iPriority);
    }

    public static function getRepositoryRam(int $iPriority = 1): Repository
    {
        return static::getRepositoryByPriority(static::RAM, $iPriority);
    }

    public static function getRepositoryNull(int $iPriority = 1): Repository
    {
        return static::getRepositoryByPriority(static::NONE, $iPriority);
    }

    protected static function validType(string $sNsType): bool
    {
        return isset(static::$aLoadTypes[$sNsType]) ||
            in_array($sNsType, [
                static::HIGH_LOAD,
                static::SECONDARY_LOAD,
                static::FILESYSTEM,
                static::FILESYSTEM2,
                static::RAM,
                static::NONE,
            ]);
    }

    protected static function getXLoadRepository(string $sType): array
    {
        if (!isset(static::$aLoadTypes[$sType])) {
            return static::setToUseRepositories($sType, []);//predefined order
        }
        return static::$aLoadTypes[$sType];
    }

    protected static function matchAvailable(array $aDesiredRepositoryNames): array
    {
        $aMatches = [];
        foreach (AfrCacheApp::getInstance()->getAvailableRepositoryNames() as $sRepositoryName => $sDriver) {
            if (in_array($sRepositoryName, $aDesiredRepositoryNames)) {
                $aMatches[$sRepositoryName] = $sDriver;
            }
        }
        if (empty($aMatches)) {
            $aMatches = ['null' => 'null'];
        }
        return $aMatches;
    }


}