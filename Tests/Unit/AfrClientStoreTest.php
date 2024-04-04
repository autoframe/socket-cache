<?php
declare(strict_types=1);

namespace Unit;

use Autoframe\Components\SocketCache\AfrCacheSocketConfig;
use Autoframe\Components\SocketCache\Client\AfrClientStore;
use Autoframe\Components\SocketCache\Client\AfrSocketClient;
use Autoframe\Components\SocketCache\Exception\AfrCacheSocketException;
use Autoframe\Components\SocketCache\Common\AfrCacheSocketStore;

use PHPUnit\Framework\TestCase;

class AfrClientStoreTest extends TestCase
{
//    use SockTestsTrait;

    protected ?AfrClientStore $oAfrClientStore;

    public static function insideProductionVendorDir(): bool
    {
        return strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false;
    }

    protected function setUp(): void
    {
        try {
            $oConfig = new AfrCacheSocketConfig(basename(__FILE__));
        //    $oConfig->iAutoShutdownServerAfterXSeconds = 20;
            $oConfig->bServerAutoPowerOnByConfigViaCliOnLocal = true;
            $oConfig->iServerMemoryMb = 24;
        //    $oConfig->socketPort = 27499;// + rand(0, 99);

            AfrCacheSocketConfig::serverUp($oConfig);

            $this->oAfrClientStore = new AfrClientStore($oConfig);
        } catch (AfrCacheSocketException $e) {
            $this->oAfrClientStore = null;
        }
    }

    protected function tearDown(): void
    {
        //cleanup between tests for static
        $this->oAfrClientStore->flush();
    }

    public static function putProvider(): array
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $aReturn = [];
        for ($i = 0; $i < 2; $i++) {
            $sData = self::generateRandomSockText(rand(1, 2));
            $sKey = $i . md5($sData);
            $oClass = new \stdClass();
            $oClass->prop = $sKey;

            $aReturn[] = [$sKey . 's', $sData]; //str
            $aReturn[] = [$sKey . 'i', $i]; //int
        //    break;
            $aReturn[] = [$sKey . 'd', $i + 0.001]; //decimal / float
            $aReturn[] = [$sKey . 'a', [substr($sData, 2, 10)]];//array
            if (empty(func_get_args()[0])) {
                $aReturn[] = [$sKey . 'c', $oClass];//class
            }
            $aReturn[] = [$sKey . 'n', null];//null
            $aReturn[] = [$sKey . 'bf', false];//bool
            $aReturn[] = [$sKey . 'bt', true];//bool
        }
        return $aReturn;
    }


    /**
     * @test
     * @dataProvider putProvider
     */
    public function putTest(string $sKey, $mData): void
    {
        $bPut = $this->oAfrClientStore->put($sKey, $mData, 2);
        $this->assertSame(true, $bPut);

        if (is_object($mData)) {
            $this->assertSame(
                get_class($mData),
                get_class($this->oAfrClientStore->get($sKey))
            );
        } else {
            $this->assertSame(
                $mData,
                $this->oAfrClientStore->get($sKey)
            );
        }
    }

    /**
     * @test
     */
    public function putManyTest(): void
    {

        //check empty
        $this->assertSame([], $this->oAfrClientStore->getAllKeys());

        $aData = [];
        foreach (self::putProvider() as $aRow) {
            $aData[$aRow[0]] = is_string($aRow[1]) ? substr($aRow[1], 0, 10) : $aRow[1];
        }

        //put
        $this->assertSame(true, $this->oAfrClientStore->putMany($aData, 1));
        //count keys
        $this->assertSame(count($aData), count($this->oAfrClientStore->getAllKeys()));

        //check data
        $aCached = $this->oAfrClientStore->many(array_keys($aData));
        $this->assertSame(
            serialize($aData),
            serialize($aCached),
            'Expected:' . print_r($aData, true) .
            'Reveived:' . print_r($aCached, true)
        );

        //clear
        $this->oAfrClientStore->flush();

    }

    /**
     * @test
     */
    public function getTest(): void
    {
        $sKeySet = 'is-set';
        $sKeySetValue = 'ok';

        $this->assertSame(true, $this->oAfrClientStore->put($sKeySet, $sKeySetValue, 2));
        $this->assertSame($sKeySetValue, $this->oAfrClientStore->get($sKeySet));
        $this->assertSame($sKeySetValue, $this->oAfrClientStore->get([$sKeySet])[$sKeySet]);
        $this->assertSame($sKeySetValue, $this->oAfrClientStore->get([$sKeySet => 'some default value not'])[$sKeySet]);

        $sKeyNotSet = 'not set and returning with default value';
        $sDefaultValue = 'default value';

        $this->assertSame(null, $this->oAfrClientStore->get($sKeyNotSet));

        $this->assertSame(
            $sDefaultValue,
            $this->oAfrClientStore->get(
                $sKeyNotSet,
                $sDefaultValue
            )
        );

        $mDefaultFromCache = $this->oAfrClientStore->get([$sKeyNotSet => $sDefaultValue]);
        $this->assertSame(
            $sDefaultValue,
            $mDefaultFromCache[$sKeyNotSet],
            print_r($mDefaultFromCache, true)
        );

    }

    /**
     * @test
     */
    public function getSocketClientTest(): void
    {
        $this->assertSame(
            true,
            $this->oAfrClientStore->_getSocketClient() instanceof AfrSocketClient
        );
    }

    /**
     * @test
     */
    public function getCacheSocketConfigTest(): void
    {
        $this->assertSame(
            true,
            $this->oAfrClientStore->_getCacheSocketConfig() instanceof AfrCacheSocketConfig
        );
    }

    /**
     * @test
     */
    public function getSockResponseTest(): void
    {
        //$this->aSockResponse = ['{serialized data}', success: false/true, 'err info' ];

        $this->assertSame([], $this->oAfrClientStore->getSockResponse());
        $this->oAfrClientStore->get('something-not-set');
        $this->assertSame(
            ['N;', true, ''],
            $this->oAfrClientStore->getSockResponse(),
            print_r($this->oAfrClientStore->getSockResponse(), true)
        );

        $aRaw = $this->oAfrClientStore->_getSocketClient()->sendRequest('invalid-cmd');
        $this->assertSame(false, $aRaw[1]);

        $sKey = 'something-set';
        $sVal = 'k';
        $this->assertSame(true, $this->oAfrClientStore->put($sKey, $sVal, 2));
        $this->oAfrClientStore->get($sKey);
        $this->assertSame(
            ['s:1:"k";', true, ''],
            $this->oAfrClientStore->getSockResponse()
        );
    }

     /**
     * @test
     */
    public function incrementDecrementTest(): void
    {
        $sKey = __FUNCTION__;
        $iComp = 0;

        $this->assertSame(null, $this->oAfrClientStore->get($sKey));
        $this->assertSame($iComp, $this->oAfrClientStore->increment($sKey,$iComp));
        $this->assertSame($iComp, $this->oAfrClientStore->get($sKey) );

        $iComp+=5;
        $this->assertSame($iComp, $this->oAfrClientStore->increment($sKey,5));
        $this->assertSame($iComp, $this->oAfrClientStore->get($sKey) );

        $iComp-=2;
        $this->assertSame($iComp, $this->oAfrClientStore->decrement($sKey,2));
        $this->assertSame($iComp, $this->oAfrClientStore->get($sKey) );

        $sKey.='d';
        $this->assertSame(-2, $this->oAfrClientStore->decrement($sKey,2));
        $this->assertSame(-2, $this->oAfrClientStore->get($sKey) );

    }
    /**
     * @test
     */
    public function foreverForgetTest(): void
    {
        $sKeySet = 'to-be';
        $aRandVals = self::putProvider(true);
        $mVal = $aRandVals[rand(0, count($aRandVals) - 1)];
        $sInfo = print_r([$sKeySet,$mVal],true);

        $this->assertSame(true, $this->oAfrClientStore->get($sKeySet) === null,$sInfo);
        $this->assertSame(true, $this->oAfrClientStore->forever($sKeySet, $mVal),$sInfo);
        $this->assertSame(true, $this->oAfrClientStore->get($sKeySet) === $mVal,$sInfo);

        $this->assertSame(true, $this->oAfrClientStore->forever($sKeySet, $mVal),$sInfo);
        $this->assertSame(true, $this->oAfrClientStore->get($sKeySet) === $mVal,$sInfo);

        $this->assertSame(true, $this->oAfrClientStore->forget($sKeySet),$sInfo);
        $this->assertSame(true, $this->oAfrClientStore->get($sKeySet) === null,$sInfo);
        $this->assertSame(false, $this->oAfrClientStore->forget($sKeySet),$sInfo);

    }

    /**
     * @test
     */
    public function prefixTest(): void //TODO ??
    {
        $this->assertSame(true, true);
        //$this->assertSame(true, microtime(false));

    }

    /**
     * @test
     */
    public function deleteTest(): void
    {
        $sKey = 'toBeDeleted';
        $sKeyDelay = 'toBeDeletedDelay';
        $sVal = '...';
        $this->assertSame(true, $this->oAfrClientStore->put($sKey, $sVal, 10));
        $this->assertSame($sVal, $this->oAfrClientStore->get($sKey));
        $this->assertSame(true, $this->oAfrClientStore->delete($sKey));
        $this->assertSame(false, $this->oAfrClientStore->delete($sKey));

        //delay
        $this->assertSame(true, $this->oAfrClientStore->put($sKeyDelay, $sVal, 10));
        $this->assertSame(true, $this->oAfrClientStore->delete($sKeyDelay, 1));
        $this->assertSame($sVal, $this->oAfrClientStore->get($sKeyDelay));
        sleep(2);
        $this->assertSame([], $this->oAfrClientStore->getAllKeys());
        $this->assertSame(false, $this->oAfrClientStore->delete($sKeyDelay, 1));

        //negative delay is instant
        $this->assertSame(true, $this->oAfrClientStore->put($sKeyDelay, $sVal, 10));
        $this->assertSame(true, $this->oAfrClientStore->delete($sKeyDelay, -1));
        $this->assertSame(false, $this->oAfrClientStore->delete($sKeyDelay, -1));
        $this->assertSame([], $this->oAfrClientStore->getAllKeys());

    }

    /**
     * @test
     */
    public function getMemoryUsageInfoTest(): void
    {
        $this->assertSame(
            true,
            is_array($this->oAfrClientStore->getMemoryUsageInfo()),
            print_r($this->oAfrClientStore->getMemoryUsageInfo(), true)
        );
        $this->assertSame(
            true,
            count($this->oAfrClientStore->getMemoryUsageInfo()) >= 8
        );
    }


    /**
     * @param int $iKb
     * @param string $sOneKbEll
     * @return string
     */
    public static function generateRandomSockText(int $iKb = 10, string $sOneKbEll = "\n"): string
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