<?php
declare(strict_types=1);

namespace Autoframe\Components\SocketCache\Server;

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;
use Autoframe\Components\SocketCache\Common\AfrCacheSocketClientServerTrait;
use Autoframe\Components\SocketCache\Exception\AfrCacheSocketException;
use Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Store;


class AfrSocketServer
{
    use AfrCacheSocketClientServerTrait;
    use MemoryInfo;

    protected AfrServerStore $oStore;
    protected array $aSocketClients = [];

    //https://www.techinpost.com/only-one-usage-of-each-socket-address-is-normally-permitted/

    /**
     * @param AfrCacheSocketConfig $oConfig
     * @throws AfrCacheSocketException
     */
    public function __construct(AfrCacheSocketConfig $oConfig)
    {
        $this->oConfig = $oConfig;
        error_reporting($this->oConfig->iServerErrorReporting);
        set_time_limit($this->oConfig->iAutoShutdownServerAfterXSeconds);
        if ($this->oConfig->bServerInlineEcho && http_response_code() !== false) {
            //           ob_end_flush();
            // Turn on implicit output dump, so we'll see what we're getting as it arrives.
            //           ob_implicit_flush();
        }
        if ($oConfig->sServerStoreFQCN) {
            $sStoreClass = '\\' . trim($oConfig->sServerStoreFQCN, '\\ ');
            $this->oStore = new $sStoreClass($oConfig);
        }
        $this->run();
    }


    /**
     * @return void
     * @throws AfrCacheSocketException
     */
    protected function run(): void
    {
        if ($pf = @fsockopen(
            $this->oConfig->socketIp,
            $this->oConfig->socketPort,
            $err,
            $err_string,
            1 / 100)
        ) {
            fclose($pf);
            $this->serverEchoInline("Server is already started on {$this->oConfig->socketIp}:{$this->oConfig->socketPort}");
            //$this->serverEchoInline((string)$err);
            //$this->serverEchoInline((string)$err_string);
            die();
        }


        $this->serverEchoInline("Starting Socket Server on {$this->oConfig->socketIp}:{$this->oConfig->socketPort}");
        if (!$this->socketCreateBindListen()) {
            throw new AfrCacheSocketException('Unable to initiate the server: ' . implode('; ', $this->oConfig->aErrors));
        }

        $sAwaiting = '';
        do {
            $aReadClientPool = array_merge([$this->oConfig->mSocket], $this->aSocketClients);

            // Set up a blocking call to socket_select
            $write = $except = null;
            if (socket_select(
                    $aReadClientPool,
                    $write,
                    $except,
                    $this->oConfig->iSocketSelectSeconds,
                    $this->oConfig->iSocketSelectUSeconds
                ) < 1) {
                if (!$sAwaiting) {
                    $sAwaiting = 'Waiting for connections on socket_select';
                    $this->serverEchoInline($sAwaiting, 2);
                    $this->serverEchoInline($this->getSvMemoryUsageInfo(), 2);
                }
                continue;
            }
            $sAwaiting = '';

            // Handle new Connections
            if (in_array($this->oConfig->mSocket, $aReadClientPool)) {
                if (!$this->socketAccept()) {
                    $this->serverEchoInline('New connection is not accepted, so continue...');
                    continue; //KEEPS SERVER ON!!
                    //break; //STOPS THE WHILE LOOP!!
                    //do nothing will unload some existing connections?
                }
            }
            $this->serverEchoInline('Handle new Connections OK', 2);

            // Handle Input
            foreach ($this->aSocketClients as $iClientQueueKey => $oClient) { // for each oClient
                if (!in_array($oClient, $aReadClientPool)) {
                    $this->serverEchoInline('Handle new Input continue', 2);
                    continue;
                }
                $this->serverEchoInline('Handle Input OK => socket read;', 2);

                ///////// RAW READ
                $sRawRead = $this->socketRead($iClientQueueKey);
                if (strlen($sRawRead) < 1) {
                    $this->serverEchoInline('Key#' . $iClientQueueKey . ' Just connected without any send so continue;');
                    $this->socketWrite('hello', $iClientQueueKey, true);
                    //$this->socketCloseClient($iClientQueueKey,1);
                    continue; //just connected without any send
                }

                ////////// READ DECODE / VERIFY
                list($sRead, $bIntegrityCheckSuccess, $sDebugInfo) =
                    $this->oConfig->xetIntegrityValidator()->svDecodeRead($sRawRead);
                if ($bIntegrityCheckSuccess) {
                    $this->serverEchoInline('READ limit 80 chr: ' . substr($sRead, 0, 80), 2);
                } else {
                    $this->serverEchoInline('Read integrity failed: ' . $sDebugInfo);
                    $this->serverEchoInline('Read integrity failed raw read: ' . $sRawRead, 2);
                    $this->socketWrite('false', $iClientQueueKey, true);
                    continue; //skip wrong command
                }


                ////////// SOCKET WRITE ENCODE AND SEND
                if ($sRead === 'shutdown') {
                    $this->serverEchoInline($sRead . ' command received'."\n".$this->getSvMemoryUsageInfo());
                    $this->socketWrite($sRead, $iClientQueueKey);
                    $this->disconnectAll();
                    unset($this->oStore);
                    break 2;
                }

                ////////// SOCKET WRITE PREPARE
                //$sWriteBack = 'I have received this message: '.$sRead; //failsafe on error!
                if (!empty($this->oStore) && substr($sRead, 0, 2) === 'a:') {
                    //$sWriteBack = $this->oStore->
                    $aRead = unserialize($sRead);
                    if (is_array($aRead) && !empty($aRead[0])) {
                        $sCallStoreMethod = $aRead[0];
                        $aCallArgs = array_slice($aRead, 1);
                        try {
                            $sWriteBack = serialize($this->oStore->$sCallStoreMethod(...$aCallArgs));
                        } catch (\Throwable $oEx) {
                            $sWriteBack = $oEx->getMessage();
                            $this->serverEchoInline('~EXCEPTION~: ' . $sWriteBack, 1);
                            $this->socketWrite('false', $iClientQueueKey);
                        }
                    } else {
                        $this->socketWrite('false', $iClientQueueKey);
                        continue; //skip wrong command
                    }
                } else {
                    $sWriteBack = 'false'; //failsafe on error!
                    //$sWriteBack = 'READ.40chr: ' . substr($sRead, 0, 40); //failsafe on error!
                    $this->socketWrite($sWriteBack, $iClientQueueKey);
                    continue; //skip wrong command

                }
                $this->socketWrite($sWriteBack, $iClientQueueKey);
            }
        } while (true);
        $this->socketClose();
    }


    protected function disconnectAll()
    {
        foreach ($this->aSocketClients as $k => $oCl) {
            $this->socketCloseClient($k, 2);

        }
        $this->socketClose();
        $this->oConfig->aErrors = [];
    }

    /**
     * @return bool
     */
    protected function socketCreateBindListen(): bool
    {

        $this->socketClose();

        $this->oConfig->mSocket = socket_create(...$this->oConfig->socketCreate);
        if ($this->oConfig->mSocket === false) {
            $this->pushSocketErrors('socket_create(' . $this->oConfig->driver . ') failed: ' . socket_strerror(socket_last_error()));
            return false;
        }

        if (socket_bind($this->oConfig->mSocket, $this->oConfig->socketIp, $this->oConfig->socketPort) === false) {
            $this->pushSocketErrors('socket_bind(' . $this->oConfig->driver . ') failed: ' . socket_strerror(socket_last_error()));
            return false;
        }

        if (socket_listen($this->oConfig->mSocket, $this->oConfig->iSocketListenBacklogQueue) === false) {
            $this->pushSocketErrors('socket_listen(' . $this->oConfig->driver . ') failed: ' . socket_strerror(socket_last_error()));
            return false;
        }
        $this->aSocketClients = [];

        register_shutdown_function(function () {
            $this->serverEchoInline('Shutting down...');
            $this->socketClose();
        });
        return true;
    }


    /**
     * @param string $sData
     * @param $iClientQueueKey
     * @param bool $bRaw
     * @return false|int|mixed
     */
    protected function socketWrite(string $sData, $iClientQueueKey, bool $bRaw = false)
    {
        if (!$bRaw) {
            $sData = $this->oConfig->xetIntegrityValidator()->svCodeWrite($sData);
        }
        if (!empty($this->aSocketClients[$iClientQueueKey])) {
            $result = socket_write($this->aSocketClients[$iClientQueueKey], $sData, strlen($sData));
            if ($result === false) {
                $this->pushSocketErrors('socket_write(' . $this->oConfig->driver . ') failed: ' .
                    socket_strerror(socket_last_error($this->aSocketClients[$iClientQueueKey])));
            }
        } else {
            $result = null;
            $this->pushSocketErrors(
                'socket_write(' . $this->oConfig->driver .
                ') failed because of empty index #' . $iClientQueueKey . ' ** ' .
                socket_strerror(socket_last_error($this->aSocketClients[$iClientQueueKey])));
        }

        //off reading(0);writing(1);both(2)
        $this->socketCloseClient($iClientQueueKey, 1);
        return $result;
    }

    /**
     * @param $iClientQueueKey
     * @param int $mode
     * @return void
     */
    protected function socketCloseClient($iClientQueueKey, int $mode = 1): void
    {
        if (!empty($this->aSocketClients[$iClientQueueKey])) {
            @socket_shutdown($this->aSocketClients[$iClientQueueKey], $mode);
            @socket_close($this->aSocketClients[$iClientQueueKey]);
        }
        unset($this->aSocketClients[$iClientQueueKey]);
    }

    /**
     * @param $key
     * @return string
     */
    protected function socketRead($key): string
    {
        $sRead = '';
        while ($sBuf = socket_read($this->aSocketClients[$key], $this->oConfig->iSocketReadBuffer)) {
            $sRead .= $sBuf;
        }
        if (strlen($sRead) > 0) {
            @socket_shutdown($this->aSocketClients[$key], 0); //off reading(0);writing(1);both(2)
        }
        return $sRead;
    }


    /**
     * @param string $sEvent
     * @param int $iLevel
     * @return void
     */
    protected function serverEchoInline(string $sEvent, int $iLevel = 1): void
    {
    //    return; //TODO speed test!
        $nl = (http_response_code() === false ? '' : '<br>') . "\n";
        if ($this->oConfig->bServerInlineEcho && $iLevel < 2 || $this->oConfig->bServerAllInlineDebug) {
            echo '[' . microtime(true) . '] ' . $sEvent . $nl;
        }
        if ($this->oConfig->sServerLogInstanceOrSingletonFQCNClassName && $iLevel < 2) {
            try {
                $this->oConfig->sServerLogInstanceOrSingletonFQCNClassName::getInstance()->log($sEvent);
            } catch (\Throwable $oEx) {
                echo '[' . microtime(true) . '] LOGGER EXCEPTION ' .
                    get_class($oEx) . ': ' .
                    $oEx->getMessage() . $nl;
            }
        }
    }


    /**
     * @return bool
     */
    protected function socketAccept(): bool
    {
        if (($msgSock = socket_accept($this->oConfig->mSocket)) === false) {
            $this->pushSocketErrors('socket_accept(' . $this->oConfig->driver . ') failed: ' .
                socket_strerror(socket_last_error($this->oConfig->mSocket)));
            return false;
        }
        $this->aSocketClients[] = $msgSock;
        return true;
    }


    protected function getSvMemoryUsageInfo():string
    {
        $sMemoryUsageInfoAsString = '';
        if (!empty($this->oStore)) {
            try {
                $sMemoryUsageInfoAsString = $this->oStore->getMemoryUsageInfoAsString();
            } catch (\Throwable $oEx) {
            }
        }
        if (!$sMemoryUsageInfoAsString) {
            $sMemoryUsageInfoAsString = $this->getMemoryUsageInfoAsString();
        }
        return $sMemoryUsageInfoAsString;
    }



}