<?php
declare(strict_types=1);

namespace Autoframe\Components\SocketCache\Client;

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;
use Autoframe\Components\SocketCache\Common\AfrCacheSocketClientServerTrait;
use Autoframe\Components\SocketCache\Exception\AfrCacheSocketException;
use Throwable;

class AfrSocketClient
{
    use AfrCacheSocketClientServerTrait;

    public array $afRequestTimeMs = [];
    public static array $aAutoServerUpSent = [];

    /**
     * mConfig can be the name of the config or the serialized class or the instance
     * @param AfrCacheSocketConfig|string $mConfig
     * @throws AfrCacheSocketException
     */
    public function __construct($mConfig)
    {
        if (is_string($mConfig)) {
            if (AfrCacheSocketConfig::hasConfig($mConfig)) {
                $mConfig = AfrCacheSocketConfig::getConfigInstance($mConfig);
            } elseif (substr($mConfig, 0, 2) === 'O:') {
                try {
                    $mConfig = unserialize($mConfig);
                } catch (Throwable $e) {
                }
            }
        }

        if (!$mConfig instanceof AfrCacheSocketConfig) {
            throw new AfrCacheSocketException('Invalid config provided');
        }

        $this->oConfig = $mConfig;
    }


    /**
     * @param string $sData
     * @return array
     */
    public function sendRequest(string $sData): array
    {
        //pre-connection check timeout 10 ms
        if (empty(self::$aAutoServerUpSent[$this->oConfig->driver]) && $this->oConfig->bServerAutoPowerOnByConfigViaCliOnLocal) {
            self::$aAutoServerUpSent[$this->oConfig->driver] = true;
            AfrCacheSocketConfig::serverUp($this->oConfig);
        }

        $fStart = microtime(true);
        $iErrorCountStart = count($this->oConfig->aErrors);

        if ($this->socketCreate()) {
            $this->socketSetOptions();
            if ($this->socketConnect()) {
                if ($this->socketWrite($sData)) { //Buffered
                    $aReturn = $this->socketRead();
                }
            }
        }
        $this->socketClose();
        $this->afRequestTimeMs[] = round((microtime(true) - $fStart) * 1000, 4);


        if (empty($aReturn)) { //generic connection error info
            $iErrorCountAfter = count($this->oConfig->aErrors);
            if ($iErrorCountStart !== $iErrorCountAfter) {
                $sErrTxt = implode(
                    ';  ',
                    $iErrorCountStart ?
                        array_slice($this->oConfig->aErrors, $iErrorCountStart) :
                        $this->oConfig->aErrors
                );
            }
            $aReturn = [
                null,
                false,
                get_class($this) . '::' . __FUNCTION__ . ' ' . (!empty($sErrTxt) ? $sErrTxt : 'NOT MADE')
            ];
        }

        return $aReturn;
    }


    /**
     * @return bool
     */
    protected function awaitingForTimeoutRecovery(): bool
    {
        return ($this->oConfig->fFailedToConnect && $this->oConfig->fFailedToConnect > microtime(true));
    }

    /**
     * @param int $iMsToWait
     * @return void
     */
    protected function setAwaitForTimeoutRecovery(int $iMsToWait = 500): void
    {
        $this->oConfig->fFailedToConnect = microtime(true) + ($iMsToWait / 1000);
    }

    /**
     * @return bool
     */
    protected function socketCreate(): bool
    {
        $this->socketClose();
        if ($this->awaitingForTimeoutRecovery()) {
            return false;
        }
        $this->oConfig->mSocket = socket_create(...$this->oConfig->socketCreate);
        if ($this->oConfig->mSocket === false) {
            $this->pushSocketErrors('socket_create(' . $this->oConfig->driver . ') failed: ' . socket_strerror(socket_last_error()));
            $this->setAwaitForTimeoutRecovery(60 * 60 * 1000); //if the socket could not be created, don't bother to do so
            return false;
        }
        return true;
    }


    /**
     * @return bool
     */
    protected function socketConnect(): bool
    {
        //https://www.techinpost.com/only-one-usage-of-each-socket-address-is-normally-permitted/

        if ($this->awaitingForTimeoutRecovery()) { //check for failed attempts
            return false;
        }

        if ($this->oConfig->bServerAutoPowerOnByConfigViaCliOnLocal && empty(self::$aAutoServerUpSent[$this->oConfig->driver])) {
            self::$aAutoServerUpSent[$this->oConfig->driver] = true;
            AfrCacheSocketConfig::serverUp($this->oConfig);
        }

        $result = socket_connect($this->oConfig->mSocket, $this->oConfig->socketIp, $this->oConfig->socketPort);
        if ($result === false) {
            $sErr = socket_strerror(socket_last_error($this->oConfig->mSocket));
            $this->pushSocketErrors('socket_connect(' . $this->oConfig->driver . ') failed: ' . $sErr);
            $this->socketClose();
            //if the socket could not connect, we wasted 2 seconds already! retry in 2 seconds
            $this->setAwaitForTimeoutRecovery(2 * 1000);
            return false;
        }
        return true;
    }


    /**
     * @param string $sData
     * @return false|int
     */
    protected function socketWrite(string $sData)
    {

        if ($this->awaitingForTimeoutRecovery()) {
            return false;
        }

        $sData = $this->oConfig->xetIntegrityValidator()->clCodeWrite($sData);
        $result = socket_write($this->oConfig->mSocket, $sData, strlen($sData));
        if ($result === false) {
            $this->pushSocketErrors('socket_write(' . $this->oConfig->driver . ') failed: ' .
                socket_strerror(socket_last_error($this->oConfig->mSocket)));
        }
        socket_shutdown($this->oConfig->mSocket, 1); //off reading(0);writing(1);both(2)
        return $result;
    }

    /**
     * list($sRead, $bIntegrityCheckSuccess, $sDebugInfo)
     * @return array
     */
    protected function socketRead(): array
    {

        if ($this->awaitingForTimeoutRecovery()) {
            return [null, true, 'awaitingForTimeoutRecovery'];
        }
        $sOut = '';
        while ($sBuf = socket_read($this->oConfig->mSocket, $this->oConfig->iSocketReadBuffer)) {
            $sOut .= $sBuf;
        }
        if ($this->oConfig->fFailedToConnect && strlen($sOut) > 5) { //connection recovered
            $this->oConfig->fFailedToConnect = 0;
        }
        if (!$sOut && strlen($sOut) < 1) {
            $sErr = 'socket_read(' . $this->oConfig->driver . ') failed: ' .
                socket_strerror(socket_last_error($this->oConfig->mSocket));
            $this->pushSocketErrors($sErr);
            return [null, false, $sErr];
        }

        socket_shutdown($this->oConfig->mSocket, 0); //off reading(0);writing(1);both(2)
        return $this->oConfig->xetIntegrityValidator()->clDecodeRead($sOut);
    }


}
