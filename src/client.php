<?php


error_reporting(E_ALL);

function testSockClient(&$kbTotal, $cid = 0)
{
    $tmp = explode(" ", microtime(true));
    $start_time = (isset($tmp[1]) ? $tmp[1] + $tmp[0] : $tmp[0]);
    echo "<h2>TCP/IP Connection</h2>\n";

    /* Get the port for the WWW service. */
    $service_port = 11317;

    /* Get the IP address for the target host. */
//$address = gethostbyname('www.example.com');
    $address = '127.0.0.1';

    /* Create a TCP/IP socket. */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
    } else {
        echo "OK.\n";
    }

    socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 1, 'usec' => 50));
    socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 1, 'usec' => 50));
    echo "Attempting to connect to '$address' on port '$service_port'...";
    $result = socket_connect($socket, $address, $service_port);

    if ($result === false) {
        echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
    } else {
        echo "OK.\n";
    }

    $in = 'shutdown';
    $in = prepareTalkback($cid);
    //$in = '<<mesaj test IN>>';
    $out = '';

    echo "Sending HTTP HEAD request...";
    socket_write($socket, $in, strlen($in));
    socket_shutdown($socket, 1); //off reading(0);writing(1);both(2)

    echo "OK.\n";

    echo "Reading response:\n\n";
    $i = 0;
    while ($buf = socket_read($socket, 2048)) {
        $i++;
        $out .= $buf;
    }

    echo "Closing socket...";
    @socket_shutdown($socket, 0);  //off reading(0);writing(1);both(2)
    socket_close($socket);
    echo "OK.\n\n";

    $tmp = explode(" ", microtime(true));
    $sec = (float)((isset($tmp[1]) ? $tmp[1] + $tmp[0] : $tmp[0]) - $start_time);
    $msec = ($sec * 1000);
    $msec = round($msec, 4);
    $kb = round(strlen($out) / 1024, 2);
    $kbTotal += $kb;
    echo "$sec seconds\n$msec ms\n$kb KB\n\n\n";
    $peak_memory = number_format(memory_get_peak_usage() / 1024, 0, '.', ',') . " kb";
    $end_memory = number_format(memory_get_usage() / 1024, 0, '.', ',') . " kb";
    echo "peak_memory : $peak_memory\nend_memory : $end_memory\n";

    return microtime(true);
}

function prepareTalkback($key = 0, string $sRead = 'in'): string
{
    $repeatA = 102;
    $repeatB = 25;

    $repeatA = 10;
    $repeatB = 7;

    $talkback = '';
    for ($i = 0; $i < 10; $i++) {
        for ($j = 0; $j < 10; $j++) {
            $talkback .= str_repeat((string)$i, $repeatA) . "\n";
        }
    }

    $md5 = substr($sRead, 0, 32);
    $talkback = "#{$key}~~$md5~~" . strlen($sRead) . "~~\n" . str_repeat($talkback, rand(1, $repeatB)) . '~~' . $sRead;
    return $talkback;
}

$aMark = [microtime(true)];
$kbTotal = 0;

ob_start();
for ($i = 0; $i < 500; $i++) {
    $aMark[] = testSockClient($kbTotal, $i);
}
ob_end_clean();

echo "\nRequests $i\nTOTAL  " . round($aMark[count($aMark) - 1] - $aMark[0], 4) . " sec\n";
echo "TOTAL  " . round($kbTotal / 1024, 4) . " MB\n";

//print_r($aMark);
