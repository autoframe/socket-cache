<?php
declare(strict_types=1);

require_once(__DIR__ . '/../vendor/autoload.php');

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;
use Autoframe\Components\SocketCache\Client\AfrSocketClient;
use Autoframe\Components\SocketCache\Ury;

if(0){
    $oCache = Ury::getInstance();
    $oCache->set('cheie','valoare');
    echo $oCache->get('cheie');

   /* $fStart = microtime(true);
    for($i=0;$i<50000;$i++){
        echo '#'.$i.$oClient->sendRequest($oClient::generateRandomText(10000))[0]."\n";
        //print_r($oClient->sendRequest($oClient::generateRandomText()));
        if(microtime(true) - $fStart>1){
            break;
        }
    }*/
    die;

}




$oClient = new AfrSocketClient(
    new AfrCacheSocketConfig('TODO-REPLACE-ME!')
);

print_r($oClient->sendRequest('ABCDEFGHI'));
//sleep(2);
print_r($aReply = $oClient->sendRequest('JKLMNOP'));
if($aReply[1]){
    $fStart = microtime(true);
    for($i=0;$i<5;$i++){
        echo '#'.$i.$oClient->sendRequest($oClient::generateRandomText(500))[0]."\n";
        //print_r($oClient->sendRequest($oClient::generateRandomText()));
        if(microtime(true) - $fStart>1){
            break;
        }
    }
}


