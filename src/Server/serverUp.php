<?php
declare(strict_types=1);

$insideProductionVendorDir = strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false;
require_once(__DIR__ . ($insideProductionVendorDir ? '/../../../../autoload.php' : '/../../vendor/autoload.php'));


\Autoframe\Components\SocketCache\AfrCacheSocketConfig::up($_SERVER['argv'][1]);

