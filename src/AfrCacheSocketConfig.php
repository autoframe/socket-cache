<?php
declare(strict_types=1);

namespace Autoframe\Components\SocketCache;

use Autoframe\Components\SocketCache\Exception\AfrCacheSocketException;
use Autoframe\Components\SocketCache\Integrity\AfrSocketIntegrityClass;
use Autoframe\Components\SocketCache\Integrity\AfrSocketIntegrityInterface;
use Autoframe\Components\SocketCache\Server\AfrSocketServer;
use Autoframe\Components\SocketCache\Server\AfrServerStore;

//https://www.techinpost.com/only-one-usage-of-each-socket-address-is-normally-permitted/

class AfrCacheSocketConfig
{
    use AfrCacheSocketConfigStatic;

    public $mSocket = null;
    /**
     * @var string[]
     */
    public array $aErrors = [];
    /**
     * @var float microtime(true)
     */
    public float $fFailedToConnect = 0.0;

    public string $driver;
    public string $sSocketServerFQCN = AfrSocketServer::class;
    public string $sServerStoreFQCN = AfrServerStore::class; //implememts Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Store

    public array $socketCreate = [AF_INET, SOCK_STREAM, SOL_TCP];
    public array $socketSetOption = [
        [SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]],
        [SOL_SOCKET, SO_SNDTIMEO, ['sec' => 1, 'usec' => 0]],
    ];
    public string $socketIp = '127.0.0.1';
    public $socketPort = 11318;


    public int $iSocketReadBuffer = 1024; //used for reading with socket_read both on the server and client
    public int $iSocketListenBacklogQueue = SOMAXCONN; //1000; // SOMAXCONN
    public int $iSocketSelectSeconds = 1;
    public int $iSocketSelectUSeconds = 0;
    public int $iAutoShutdownServerAfterXSeconds = 0;


    public int $iServerErrorReporting = E_ALL;
    public bool $bServerInlineEcho = false;
    public bool $bServerAllInlineDebug = false;
    public bool $bServerAutoPowerOnByConfigViaCliOnLocal = true;
    /** @var int Allowed Server memory in MB */
    public int $iServerMemoryMb = 256; //64 MB

    public array $aEvictionPercentRange = [
        'GREEN' => 85, //Memory is checked each second. Under this everything is bliss!
        'YELLOW' => 90, //Memory is always checked after eviction procedure or nothing will happen until we hit RED and eviction
        'RED' => 95, //Memory is always checked inside the started eviction loop. Above RED% we evict stored next inline expires, until we drop in the YELLOW.
    ];

    /**
     * @var string singleton class name that implements method log(string)
     */
    public string $sServerLogInstanceOrSingletonFQCNClassName = ''; // TODO interface!!

    /**
     * @var bool Slows down performance but makes a low security if you want to host the server on another pc
     */
    public bool $bObfuscateCommunicationBetweenClientServer = false;
    public array $aObfuscateMap = [
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' . "\x1F\x8B\x08\x00\x02",
        "hqr01sijk3Dlmn2HI5NO\x1F\x8B\x08\x00\x02PQ678vwxygzEF4ABCG9JKLMRSXYZabcdTUVWefoptu"
    ];
    protected AfrSocketIntegrityInterface $oIntegrity;


    /**
     * @param $mPropertiesOrDriverName
     * @throws AfrCacheSocketException
     */
    public function __construct($mPropertiesOrDriverName)
    {
        if (is_array($mPropertiesOrDriverName)) {
            $mPropertiesOrDriverName = (array)$mPropertiesOrDriverName;
            if (
                !empty($mPropertiesOrDriverName['driver']) &&
                is_string($mPropertiesOrDriverName['driver'])
            ) {
                $this->driver = $mPropertiesOrDriverName['driver'];
            }
            $this->extend($mPropertiesOrDriverName);

        } elseif (is_string($mPropertiesOrDriverName) && strlen($mPropertiesOrDriverName)) {
            $this->driver = $mPropertiesOrDriverName;
        }
        if (empty($this->driver) || strlen(trim($this->driver)) < 1) {
            throw new AfrCacheSocketException(
                'Please provide a unique configuration name for the class ' . __CLASS__
            );
        }
        self::$aInstances[$this->driver] = $this;
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
     * @return $this
     */
    public function extend(array $aProperty): self
    {
        foreach ($aProperty as $sProperty => $mValue) {
            if (in_array($sProperty, ['driver', 'extend', 'closure',])) {
                continue;
            }
            if (is_integer($sProperty) || is_float($sProperty) || is_double($sProperty)) {
                $sProperty = '(' . gettype($sProperty) . ')' . (string)$sProperty;
            }
            if (!isset($this->$sProperty) || $this->$sProperty !== $mValue) {
                $this->$sProperty = $mValue;
            }
        }
        return $this;
    }


    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), ['mSocket']);
    }

    public function __wakeup()
    {
        self::$aInstances[$this->driver] = $this;
    }

    public function __toString(): string
    {
        return serialize($this);
    }

    /**
     * @param string $sSerializedBase64Instance Serialized and base64 Config instance
     * @param string $sDriver
     * @param bool $bPrintInfo
     * @return void
     * @throws AfrCacheSocketException
     */
    public static function up(
        string $sSerializedBase64Instance = '',
        string $sDriver = 'afrsock',
        bool   $bPrintInfo = false
    ): void
    {
        /** @var self $oConfig */
        $oConfig = !empty($sSerializedBase64Instance) ?
            unserialize(base64_decode($sSerializedBase64Instance)) :
            (new static($sDriver));

        $bCli = http_response_code() === false;

        if ($oConfig instanceof AfrCacheSocketConfig) {
            if (DIRECTORY_SEPARATOR === '\\' && $bCli) {
                ob_start();
            }
            if ($bPrintInfo) {
                $displaySettings = clone $oConfig;
                $displaySettings->aObfuscateMap = [];
                print_r($displaySettings);
                echo "\r\n\r\n\r\n";
            }

            $oConfig->aErrors = [];
            $oConfig->fFailedToConnect = 0;

            $sServerClass = '\\' . trim($oConfig->sSocketServerFQCN, '\\ ');
            new $sServerClass($oConfig);
            if (DIRECTORY_SEPARATOR === '\\') {
                ob_end_flush();
            }
        } else {
            echo 'Invalid config!';
            if ($bPrintInfo) {
                var_dump($oConfig);
            }
        }
    }

}