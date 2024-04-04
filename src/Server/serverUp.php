<?php
declare(strict_types=1);

$insideVendorDir = strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoframe') !== false;
require_once(__DIR__ . ($insideVendorDir ? '/../../../../autoload.php' : '/../../vendor/autoload.php'));


\Autoframe\Components\SocketCache\AfrCacheSocketConfig::up($_SERVER['argv'][1]);

