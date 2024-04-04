<?php
declare(strict_types=1);

namespace Autoframe\Components\SocketCache\Server;

trait MemoryInfo
{

    protected int $iMemoryLimitBytes;
    protected array $aMemoryUsageInfo;

    /**
     * @return int
     */
    protected function getMemoryLimitBytes(): int
    {
        if (!isset($this->iMemoryLimitBytes)) {
            $sConfigLine = trim(ini_get('memory_limit'));
            $fMemoryLimit = (float)substr($sConfigLine, 0, -1);
            switch (strtolower(substr($sConfigLine, -1, 1))) {
                case 't':
                    $fMemoryLimit *= 1024;
                case 'g':
                    $fMemoryLimit *= 1024;
                case 'm':
                    $fMemoryLimit *= 1024;
                case 'k':
                    $fMemoryLimit *= 1024;
            }
            $this->iMemoryLimitBytes = (int)round($fMemoryLimit, 0);
        }
        return $this->iMemoryLimitBytes;
    }

    /**
     * @param bool $bForceNew
     * @return array
     */
    public function getMemoryUsageInfo(bool $bForceNew = false): array
    {
        $iTime = time();
        if ($bForceNew || !isset($this->aMemoryUsageInfo) || $this->aMemoryUsageInfo['iTime'] < $iTime) {
            $this->aMemoryUsageInfo = [
                'iMemoryLimitBytes' => $this->getMemoryLimitBytes(),
                'iPeakUsage' => memory_get_peak_usage(),
                'iCurrentUsage' => memory_get_usage(),
                'iTime' => $iTime,
                'iHits' => $this->iHits ?? null,
                'iMisses' => $this->iMisses ?? null,
                'iEvicted' => $this->iEvicted ?? null,
                'bEvictionCheckFlag' => $this->bEvictionCheckFlag ?? null,
            ];
            $this->aMemoryUsageInfo['fUsedPercent'] =
                (float)round($this->aMemoryUsageInfo['iCurrentUsage'] * 100 / $this->getMemoryLimitBytes(), 2);


        }
        return $this->aMemoryUsageInfo;
    }

    /**
     * @param bool $bForceNew
     * @return string
     */
    public function getMemoryUsageInfoAsString(bool $bForceNew = false): string
    {
        $sOut = '';
        foreach ($this->getMemoryUsageInfo($bForceNew) as $sKey => $fVal) {
            if (in_array($sKey, ['iMemoryLimitBytes', 'iPeakUsage', 'iCurrentUsage'])) {
                $fVal = round($fVal / 1024 / 1024, 2) . 'Mb';
            } elseif ($sKey == 'fUsedPercent') {
                $fVal = round($fVal, 2) . '%';
            } elseif ($sKey == 'iTime') {
                $fVal = gmdate('Y-m-d\TH:i:sP', $fVal);
            }

            $sOut .= '; ' . $sKey . ':' . $fVal;
        }
        return substr($sOut, 2);
    }
}