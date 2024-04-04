<?php
declare(strict_types=1);

namespace Autoframe\Components\SocketCache\Common;

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;

trait AfrCacheSocketClientServerTrait
{
    protected AfrCacheSocketConfig $oConfig;

    /**
     * @param int $socketShutdownMode
     * @return void
     */
    protected function socketClose(int $socketShutdownMode = 2): void
    {
        if (!empty($oConfig)) {
            if ($this->oConfig->mSocket) {
                //off reading(0);writing(1);both(2)
                @socket_shutdown($this->oConfig->mSocket, $socketShutdownMode);
                socket_close($this->oConfig->mSocket);
                $this->oConfig->mSocket = null;
            }
        }
    }

    /**
     * @param string $sErr
     * @return void
     */
    protected function pushSocketErrors(string $sErr): void
    {
        $this->oConfig->aErrors[] = $sErr;
    }

    /**
     * @return void
     */
    protected function socketSetOptions(): void
    {
        foreach ($this->oConfig->socketSetOption as $aOptions) {
            socket_set_option($this->oConfig->mSocket, $aOptions[0], $aOptions[1], $aOptions[2]);
        }
    }

}