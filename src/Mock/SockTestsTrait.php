<?php

namespace Autoframe\Components\SocketCache\Mock;

trait SockTestsTrait
{

    /**
     * @param $key
     * @param string $sRead
     * @param string $talkback
     * @return void
     */
    private function talkbackWrite($key, string $sRead, string $talkback): void
    {
        //TODO debug refactorizare, nu in productie
        if ($key % 100 == 0) {
            $this->serverEchoInline("\n" . $this->getMemoryUsageInfoAsString() . "<hr>\n");
        }
        echo "\n#$key CL: " . count($this->aSocketClients) . "; RECV_SV: " . round(strlen($sRead) / 1024, 2) . ' KB; ';
        echo 'SENT_SV: ' . round(strlen($talkback) / 1024, 2) . ' KB; ';
//        echo substr($talkback, 0, 51) . "...<br>\n";
        echo "\n";
    }


    private function addSvResponseLengthCheckup(string &$sReply, int $iContainerLen = 32)
    {
        $sTotalLen = (string)(strlen($sReply) + $iContainerLen);
        $sContainer = str_repeat('@', $iContainerLen - strlen($sTotalLen)) . $sTotalLen;
        $sReply .= $sContainer;
    }

    /**
     * @param string $sRead
     * @param $key
     * @return string
     */
    private function prepareTalkback(string $sRead, $key): string
    {
        $repeatA = 102;
        $repeatB = 25;
        $repeatA = 7;
        $repeatB = 7;

        //$repeatA = 10;

        $talkback = '';
        for ($i = 0; $i < 10; $i++) {
            for ($j = 0; $j < 10; $j++) {
                $talkback .= str_repeat((string)$i, $repeatA) . "\n";
            }
        }

        $md5 = substr($sRead, 0, 32);
        $talkback = "#{$key}~~$md5~~" . strlen($sRead) . "~~\n" . str_repeat($talkback, rand(1, $repeatB)) . '~~' . $sRead;
        //$this->addSvResponseLengthCheckup($talkback);
        return $talkback;
    }


    /**
     * @param int $iKb
     * @param string $sOneKbEll
     * @return string
     */
    public static function generateRandomText(int $iKb = 10, string $sOneKbEll = "\n"): string
    {
        $talkback = '';
        for ($i = 0; $i < 10; $i++) {
            for ($j = 0; $j < 10; $j++) {
                if ($j % 2) {
                    $talkback .= str_repeat((string)$i, 11);
                } else {
                    $talkback .= str_repeat(chr(rand(64, 90)), 10);
                }
            }
        }
        $talkback = substr($talkback, 0, 1024 - strlen($sOneKbEll)) . $sOneKbEll;
        return str_repeat($talkback, $iKb);
    }

}