<?php
declare(strict_types=1);

namespace Autoframe\Components\SocketCache\Server;

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;
use Autoframe\Components\SocketCache\Common\AfrCacheSocketClientServerTrait;
use Autoframe\Components\SocketCache\Exception\AfrCacheSocketException;
use Illuminate\Contracts\Cache\Store;


class AfrSocketServer
{
    use AfrCacheSocketClientServerTrait;
    use MemoryInfo;

    protected Store $oStore;
    protected array $aSocketClients = [];

    //https://www.techinpost.com/only-one-usage-of-each-socket-address-is-normally-permitted/
    //TODO /usr/bin/php -d memory_limit=128M socketServer.php

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
        if($oConfig->sServerStoreFQCN){
            $sStoreClass = '\\'.trim($oConfig->sServerStoreFQCN,'\\ ');
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
                    $this->serverEchoInline($sAwaiting);
                    $this->InlineStats();
                }
                continue;
            }
            $sAwaiting = '';

            // Handle new Connections
            if (in_array($this->oConfig->mSocket, $aReadClientPool)) {
                if (!$this->socketAccept()) {
                    $this->serverEchoInline('Handle new Connections: !socketAccept();');
                    //TODO if the connection is not accepted, then continue or break or just handle existing connections?
                    $this->serverEchoInline('TODO if the connection is not accepted, then continue or break?');
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
                    $this->socketWrite('false', $iClientQueueKey,true);
                    continue; //skip wrong command
                }


                ////////// SOCKET WRITE ENCODE AND SEND
                if ($sRead === 'shutdown' || $sRead === 'restart') {
                    $this->serverEchoInline($sRead . ' command received');
                    $this->socketWrite($sRead, $iClientQueueKey);
                    $this->disconnectAll();
                    unset($this->oStore);
                    if ($sRead === 'shutdown') {
                        exit(0);
                    } else {
                        break 2;
                    }
                }

                ////////// SOCKET WRITE PREPARE
                //$sWriteBack = 'I have received this message: '.$sRead; //failsafe on error!
                if(!empty($this->oStore) && substr($sRead,0,2)==='a:'){
                    //$sWriteBack = $this->oStore->
                    $aRead = unserialize($sRead);
                    if(is_array($aRead) && !empty($aRead[0])){
                        $sCallStoreMethod = $aRead[0];
                        $aCallArgs = array_slice($aRead,1);
                        try {
                            $sWriteBack = serialize($this->oStore->$sCallStoreMethod(...$aCallArgs));
                        }
                        catch (\Throwable $oEx){
                            $sWriteBack = $oEx->getMessage();//todo de unde ma prind ca este eroare aici si de ce dau mesajul de eroare ca si data valida??
                            $this->serverEchoInline('ERROR:  ' . $sWriteBack, 1);
                        }
                    }
                    else{
                        $this->socketWrite('false', $iClientQueueKey);
                        continue; //skip wrong command
                    }
                }
                else{
                    $sWriteBack = 'READ.40chr: ' . substr($sRead, 0, 40); //failsafe on error!
                }
                $this->socketWrite($sWriteBack, $iClientQueueKey);
            }
        } while (true);
        $this->socketClose();
    }




    protected function disconnectAll()
    {
        foreach ($this->aSocketClients as $k => $oCl) {
            socket_shutdown($this->aSocketClients[$k], 2);
            socket_close($this->aSocketClients[$k]);
            unset($this->aSocketClients[$k]);
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
            $this->pushSocketErrors('socket_create(' . $this->oConfig->sConfigName . ') failed: ' . socket_strerror(socket_last_error()));
            return false;
        }

        if (socket_bind($this->oConfig->mSocket, $this->oConfig->socketIp, $this->oConfig->socketPort) === false) {
            $this->pushSocketErrors('socket_bind(' . $this->oConfig->sConfigName . ') failed: ' . socket_strerror(socket_last_error()));
            return false;
        }

        if (socket_listen($this->oConfig->mSocket, $this->oConfig->iSocketListenBacklogQueue) === false) {
            $this->pushSocketErrors('socket_listen(' . $this->oConfig->sConfigName . ') failed: ' . socket_strerror(socket_last_error()));
            return false;
        }
        $this->aSocketClients = [];

        register_shutdown_function(function () {
            $this->serverEchoInline('Shutting down...');
            //    $this->serverEchoInline('<pre>' . print_r($this->getSelectedConfig(), true) . '</pre>'); //TODO remove echo
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
        if(!$bRaw){
            $sData = $this->oConfig->xetIntegrityValidator()->svCodeWrite($sData);
        }

        $result = socket_write($this->aSocketClients[$iClientQueueKey], $sData, strlen($sData));
        if ($result === false) {
            $this->pushSocketErrors('socket_write(' . $this->oConfig->sConfigName . ') failed: ' .
                socket_strerror(socket_last_error($this->aSocketClients[$iClientQueueKey])));
        }
        //off reading(0);writing(1);both(2)
        socket_shutdown($this->aSocketClients[$iClientQueueKey], 1);
        socket_close($this->aSocketClients[$iClientQueueKey]);
        unset($this->aSocketClients[$iClientQueueKey]);
        return $result;
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
            socket_shutdown($this->aSocketClients[$key], 0); //off reading(0);writing(1);both(2)
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
        //return;
        if ($this->oConfig->bServerInlineEcho && $iLevel < 2 || $this->oConfig->bServerAllInlineDebug) {
            $nl = (http_response_code() === false ? '' : '<br>') . "\n";
            echo '[' . microtime(true) . '] ' . $sEvent . $nl;
        }
        if ($this->oConfig->mLogInstanceOrSingletonFQCNClassName && $iLevel < 2) {
            if (!is_object($this->oConfig->mLogInstanceOrSingletonFQCNClassName)) {
                $oLog = ($this->oConfig->mLogInstanceOrSingletonFQCNClassName)::getInstance();
            } else {
                $oLog = $this->oConfig->mLogInstanceOrSingletonFQCNClassName;
            }
            $oLog->log($sEvent);
        }
    }


    /**
     * @return bool
     */
    protected function socketAccept(): bool
    {
        if (($msgSock = socket_accept($this->oConfig->mSocket)) === false) {
            $this->pushSocketErrors('socket_accept(' . $this->oConfig->sConfigName . ') failed: ' .
                socket_strerror(socket_last_error($this->oConfig->mSocket)));
            return false;
        }
        $this->aSocketClients[] = $msgSock;
        return true;
    }

    private function InlineStats()
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
        $this->serverEchoInline($sMemoryUsageInfoAsString, 2);
    }


}