<?php
declare(strict_types=1);

$insideProductionVendorDir = strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false;
require_once(__DIR__ . ($insideProductionVendorDir ? '/../../../../autoload.php' : '/../../vendor/autoload.php'));

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;

/** @var AfrCacheSocketConfig $oConfig */
$oConfig = unserialize(base64_decode($_SERVER['argv'][1]));

if($oConfig instanceof AfrCacheSocketConfig){
    if(DIRECTORY_SEPARATOR === '\\'){
        ob_start();
    }
    $oConfig->aErrors = [];
    $oConfig->fFailedToConnect = 0;

    $sServerClass = '\\'.trim($oConfig->sSocketServerFQCN,'\\ ');
    new $sServerClass($oConfig);
    if(DIRECTORY_SEPARATOR === '\\'){
        ob_end_flush();
    }
}
