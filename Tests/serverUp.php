<?php
declare(strict_types=1);

require_once(__DIR__ . '/../vendor/autoload.php');

use Autoframe\Components\SocketCache\Server\AfrSocketServer;
use Autoframe\Components\SocketCache\AfrCacheSocketConfig;

/*
$aMap = [
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/@[]{}\'".,;',
    'hqr0wxy.1sijk3+D@]lmn/2H}I;5N[OPQ\'678vgzEF4A,B{CG9JKL"MRSXYZabcdTUVWefoptu'
];

function b64e(string $data): string
{
    //return $data;
    return rtrim(base64_encode($data), '=');
}

function b64d(string $data)
{
    // return $data;
    return base64_decode($data . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

function ofuscheaza(string $sData, $bEnc): string
{
    $aMap = [
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'."\x1F\x8B\x08\x00\x02",
        "hqr01sijk3Dlmn2HI5NO\x1F\x8B\x08\x00\x02PQ678vwxygzEF4ABCG9JKLMRSXYZabcdTUVWefoptu"
    ];


    if ($bEnc) {
        return strtr((string)gzencode($sData, 9), $aMap[0], $aMap[1]);
    }
    return (string)gzdecode(strtr($sData, $aMap[1], $aMap[0]));

}



$sTest = 'Mama are mere';
$sTest = 'Mama';
$sTest = 'Tata';
$sTest = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
$sTest = 'Mama are mere.Mama are mere.Mama are mere.Mama are mere.Mama are mere.Mama are mere.';
//echo ofuscheaza(ofuscheaza( $sTest,true),false). "\n";
//echo b64e(gzed($sTest))."\n";
//echo b64e(gzed($sTest.'!'))."\n-------\n";

echo $sOfsc = ofuscheaza($sTest, true);
echo "\n";
echo ofuscheaza($sOfsc, false);


die;*/
$oConfig = new AfrCacheSocketConfig('TODO-REPLACE-ME!');
while (true) {
    new AfrSocketServer($oConfig);
}
die(1);
?>
Process control single instance server via lock
client singleton with multiple configs => n servers


deci cand fac UP / run trebuie sa am o clasa de config initiata, eventual clasa de config sa faca up
am nevoie si de un canal / traductor de comenzi de comunicare, care sa aiba criptare, etc..., tot legata de config
si o sa am nevoie pentru client si de o inregistrare globala ca si cache provide pentru mysql, si alte operatii, eventual cache namespaces :D
DA: fac cache namespaces pe baza carora se va alege cache servers, si aici pun si redis / memcached / afr
TODO: de implementat un semafor / selector de cache pe baza a namespaces
