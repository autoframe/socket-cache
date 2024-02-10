<?php

namespace Autoframe\Components\SocketCache\Integrity;

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;

class AfrSocketIntegrityClass implements AfrSocketIntegrityInterface
{
    protected AfrCacheSocketConfig $oConfig;

    /**
     * @param AfrCacheSocketConfig $oConfig
     */
    public function __construct(AfrCacheSocketConfig $oConfig)
    {
        $this->oConfig = $oConfig;
    }

    /**
     * @param string $sRawRead
     * @return array
     */
    public function svDecodeRead(string $sRawRead): array
    {
        if (
            $this->oConfig->bObfuscateCommunicationBetweenClientServer &&
            ( $sRawRead === 'false' || strlen($sRawRead) < 1 )
        ) {
            return [null, false, 'Message is plain `' . $sRawRead . '`'];
        }
        $sRead = $this->obfuscate($sRawRead,false);
        $bIntegrityCheckSuccess = !($sRead === false);
        $sDebugInfo = $bIntegrityCheckSuccess?'':'Decode failed!';
        return [$sRead, $bIntegrityCheckSuccess, $sDebugInfo];
    }

    /**
     * @param string $sWrite
     * @return string
     */
    public function svCodeWrite(string $sWrite): string
    {
        $sRawWrite = $this->obfuscate($sWrite,true);
        if($sRawWrite === false){
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
        if(!$this->oConfig->bObfuscateCommunicationBetweenClientServer){
            return $sData;
        }
        $aMap = [
            'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' . "\x1F\x8B\x08\x00\x02",
            "hqr01sijk3Dlmn2HI5NO\x1F\x8B\x08\x00\x02PQ678vwxygzEF4ABCG9JKLMRSXYZabcdTUVWefoptu"
        ];
        if (
            !empty($this->oConfig->aObfuscateMap) &&
            count($this->oConfig->aObfuscateMap) > 1 &&
            strlen($this->oConfig->aObfuscateMap[0] > 5)) {
            $this->oConfig->aObfuscateMap = $aMap;
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