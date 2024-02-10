<?php
declare(strict_types=1);

namespace Autoframe\Components\SocketCache;

use Autoframe\Components\SocketCache\Client\AfrClientStore;
use Autoframe\Components\SocketCache\Client\AfrSocketClient;
use Autoframe\Components\SocketCache\Exception\AfrCacheSocketException;
use Autoframe\Components\SocketCache\Integrity\AfrSocketIntegrityClass;
use Autoframe\Components\SocketCache\Integrity\AfrSocketIntegrityInterface;
use Autoframe\Components\SocketCache\Server\AfrSocketServer;
use Autoframe\Components\SocketCache\Server\AfrServerStore;

//C:\xampp\htdocs\components-socket-cache\vendor\autoframe\process-control\src\Lock\AfrLockFileClass.php
//TODO SERVER LOCK
//TODO:: php -d memory_limit=4M Tests\serverUp.php
class AfrCacheSocketConfig
{
    use AfrCacheSocketConfigStatic;

    public $mSocket = null;
    public array $aErrors = [];
    public float $fFailedToConnect = 0.0;

    public string $sConfigName;
    public string $sConfigPrefix = ''; //TODO laravel
    public string $sSocketServerFQCN = AfrSocketServer::class;
    public string $sServerStoreFQCN = AfrServerStore::class; //implememts Illuminate\Contracts\Cache\Store
    public string $sClientFQCN = AfrSocketClient::class;  //implememts Illuminate\Contracts\Cache\Store

    protected AfrSocketIntegrityInterface $oIntegrity;
    public array $socketCreate = [AF_INET, SOCK_STREAM, SOL_TCP];
    public array $socketSetOption = [
        [SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]],
        [SOL_SOCKET, SO_SNDTIMEO, ['sec' => 1, 'usec' => 0]],
    ];
    public string $socketIp = '127.0.0.1';
    public $socketPort = 11317;


    public int $iSocketReadBuffer = 1024; //used for reading with socket_read both on the server and client
    public int $iSocketListenBacklogQueue = SOMAXCONN; //1000; // SOMAXCONN
    public int $iSocketSelectSeconds = 1;
    public int $iSocketSelectUSeconds = 0;
    public int $iAutoShutdownServerAfterXSeconds = 60;


    public int $iServerErrorReporting = E_ALL;
    public bool $bServerInlineEcho = true;
    public bool $bServerAllInlineDebug = true;
    public bool $bServerAutoPowerOnByConfigViaCliOnLocal = true;
    public int $iServerMemoryMb = 64; //64 MB

    public array $aEvictionPercentRange = [
        'GREEN' => 85, //Memory is checked each second. Under this everything is bliss!
        'YELLOW' => 90, //Memory is always checked after eviction procedure or nothing will happen until we hit RED and eviction
        'RED' => 95, //Memory is always checked inside the started eviction loop. Above RED% we evict stored next inline expires, until we drop in the YELLOW.
    ];

    public $mLogInstanceOrSingletonFQCNClassName = null; //must implement log(string) TODO interface!!

    public bool $bObfuscateCommunicationBetweenClientServer = false;
    public array $aObfuscateMap = [
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' . "\x1F\x8B\x08\x00\x02",
        "hqr01sijk3Dlmn2HI5NO\x1F\x8B\x08\x00\x02PQ678vwxygzEF4ABCG9JKLMRSXYZabcdTUVWefoptu"
    ];



    /**
     * @param $mPropertiesOrName
     * @throws AfrCacheSocketException
     */
    public function __construct($mPropertiesOrName)
    {
        if (is_array($mPropertiesOrName)) {
            $this->setAssoc((array)$mPropertiesOrName);
        } elseif (is_string($mPropertiesOrName) && strlen($mPropertiesOrName)) {
            $this->sConfigName = $mPropertiesOrName;
        }
        if (empty($this->sConfigName) || strlen(trim($this->sConfigName)) < 1) {
            throw new AfrCacheSocketException(
                'Please provide a unique configuration key name for the class ' . __CLASS__
            );
        }
        self::$aInstances[$this->sConfigName] = $this;
    }


    /**
     * @return string
     */
    public function getLockName(): string
    {
        //TODO MAKE USE OR REMOVE!!!
        return $this->socketIp . ':' . $this->socketPort;
    }

    public function xetIntegrityValidator(AfrSocketIntegrityInterface $oIntegrity = null): AfrSocketIntegrityInterface
    {
        if (!empty($oIntegrity)) {
            $this->oIntegrity = $oIntegrity;
        } elseif (empty($this->oIntegrity)) {
            $this->oIntegrity = new AfrSocketIntegrityClass($this);
        }
        return $this->oIntegrity;
    }

    /**
     * @param array $aProperty
     * @return void
     */
    protected function setAssoc(array $aProperty): void
    {
        foreach ($aProperty as $sProperty => $mValue) {
            if (is_integer($sProperty) || is_float($sProperty) || is_double($sProperty)) {
                $sProperty = '(' . gettype($sProperty) . ')' . (string)$sProperty;
            }
            if (!isset($this->$sProperty) || $this->$sProperty !== $mValue) {
                $this->$sProperty = $mValue;
            }
        }
    }



    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), ['mSocket']);
    }

    public function __wakeup()
    {
        self::$aInstances[$this->sConfigName] = $this;
    }

    public function __toString(): string
    {
        return serialize($this);
    }


}