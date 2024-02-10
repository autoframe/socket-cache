<?php
declare(strict_types=1);

require_once(__DIR__ . '/../vendor/autoload.php');

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;
use Autoframe\Components\SocketCache\Client\AfrSocketClient;
use Autoframe\Components\SocketCache\Client\AfrClientStore;


$oClient = new AfrClientStore(
    new AfrCacheSocketConfig('TODO-REPLACE-ME!')
);
$iMax = 50;
$fStart = microtime(true);


for ($i = 0; $i < $iMax; $i++) {
    //echo 'Set #' . $i . '::' . $oClient->put('key.' . $i, AfrSocketClient::generateRandomText(1), 20) . "\n";
    $bBut = $oClient->put('key.' . $i, 'AfrSocketClient::generateRandomText('.$i.')', 20);
    echo 'Set #' . $i . '::' . ($bBut===true?'true':'false') . "\n";
}
echo "\n\n-------------\n\n";
for ($i = 0; $i < $iMax; $i++) {
    echo 'Read #' . $i . '::' . $oClient->get('key.' . $i) . "\n";
    //print_r($oClient->getSockResponse());
//    echo 'Read #' . $i . '::' . $oClient->get('key.' . $i.$i) . "\n";
}

echo $fTotalTime = microtime(true)-$fStart;
