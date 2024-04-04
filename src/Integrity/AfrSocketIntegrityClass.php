<?php

namespace Autoframe\Components\SocketCache\Integrity;

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;

class AfrSocketIntegrityClass implements AfrSocketIntegrityInterface
{
    protected bool $bObfuscateCommunicationBetweenClientServer;
    protected array $aObfuscateMap;

    /**
     * @param AfrCacheSocketConfig $oConfig
     */
    public function __construct(AfrCacheSocketConfig $oConfig)
    {
        $this->bObfuscateCommunicationBetweenClientServer =
            $oConfig->bObfuscateCommunicationBetweenClientServer &&
            function_exists('gzencode') &&
            function_exists('gzdecode');

        $this->aObfuscateMap = $this->bObfuscateCommunicationBetweenClientServer ?
            $oConfig->aObfuscateMap : [];

    }

    /**
     * @param string $sRawRead
     * @return array
     */
    public function svDecodeRead(string $sRawRead): array
    {
        if (
            $this->bObfuscateCommunicationBetweenClientServer &&
            ($sRawRead === 'false' || strlen($sRawRead) < 1)
        ) {
            return [null, false, 'Message is plain `' . $sRawRead . '`'];
        }
        $sRead = $this->obfuscate($sRawRead, false);
        $bIntegrityCheckSuccess = true;
        $sDebugInfo = '';
        if ($sRead === false) {
            $bIntegrityCheckSuccess = false;
            $sDebugInfo = 'Decode failed!';
        } elseif ($sRead === 'false') {
            $bIntegrityCheckSuccess = false;
            $sDebugInfo = 'Unknown command received';
        } elseif (substr($sRead, 0, strlen('~EXCEPTION~: ')) === '~EXCEPTION~: ') {
            $bIntegrityCheckSuccess = false;
            $sDebugInfo = $sRead;
        }
        return [$sRead, $bIntegrityCheckSuccess, $sDebugInfo];
    }

    /**
     * @param string $sWrite
     * @return string
     */
    public function svCodeWrite(string $sWrite): string
    {
        $sRawWrite = $this->obfuscate($sWrite, true);
        if ($sRawWrite === false) {
            $sRawWrite = 'false';
        }
        return $sRawWrite;
    }

    /**
     * @param string $sRawRead
     * @return array
     */
    public function clDecodeRead(string $sRawRead): array
    {
        return $this->svDecodeRead($sRawRead);
    }

    /**
     * @param string $sWrite
     * @return string
     */
    public function clCodeWrite(string $sWrite): string
    {
        return $this->svCodeWrite($sWrite);
    }

    /**
     * @param string $sData
     * @param bool $bEnc
     * @return false|string
     */
    protected function obfuscate(string $sData, bool $bEnc)
    {
        if (!$this->bObfuscateCommunicationBetweenClientServer) {
            return $sData;
        }
        $aMap = [
            'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' . "\x1F\x8B\x08\x00\x02",
            "hqr01sijk3Dlmn2HI5NO\x1F\x8B\x08\x00\x02PQ678vwxygzEF4ABCG9JKLMRSXYZabcdTUVWefoptu"
        ];
        if (
            !empty($this->aObfuscateMap) &&
            count($this->aObfuscateMap) > 1 &&
            strlen($this->aObfuscateMap[0] > 5)) {
            $aMap = $this->aObfuscateMap;
        }

        if ($bEnc) {
            $gz = gzencode($sData, 9);
            if ($gz === false) {
                return false;
            }
            return strtr($gz, $aMap[0], $aMap[1]);
        }
        return gzdecode(strtr($sData, $aMap[1], $aMap[0]));
    }
}