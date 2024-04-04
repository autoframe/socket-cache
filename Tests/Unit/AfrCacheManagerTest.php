<?php
declare(strict_types=1);

namespace Unit;

use Autoframe\Components\SocketCache\Client\AfrClientStore;
use Autoframe\Components\SocketCache\App\AfrCacheApp;
use Autoframe\Components\SocketCache\Facade\AfrCache;
use Autoframe\Components\SocketCache\Facade\AfrRepositoryAutoSelector;
use PHPUnit\Framework\TestCase;

class AfrCacheManagerTest extends TestCase
{

    public static function insideProductionVendorDir(): bool
    {
        return strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false;
    }

    protected function setUp(): void
    {

        /*    $oManager = AfrCache::setManager(
                new AfrCacheManager(
                    AfrCacheApp::getInstance()
                )
            );*/









    }


    public static function putProvider(): array
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $aReturn = [];
        $aPrefix = ['First\\', 'Da/taX'];
        for ($i = 0; $i < 2; $i++) {
            $sData = self::generateRandomSockText(rand(1, 2));
            $sKey = $aPrefix[$i % 2] . $i . md5($sData);
            $oClass = new \stdClass();
            $oClass->prop = $sKey;

            $aReturn[] = [substr($sKey, 0, 1) . 's', $sData]; //str
            //    return $aReturn;
            $aReturn[] = [$sKey . 'i', $i]; //int
            //    break;
            $aReturn[] = [$sKey . 'd', $i + 0.001]; //decimal / float
            $aReturn[] = [$sKey . 'a', [substr($sData, 2, 10)]];//array
            $aReturn[] = [$sKey . 'c', $oClass];//class
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
    public function sockTest(string $sKey, $mData): void
        // public function sockTest(): void
    {
        $oApp = AfrCacheApp::getInstance();
        if (!$oApp->testSock()) {
            $this->assertSame(true, true);
            return;
        }
        $sSockDriverName = 'afrsock';
        $oApp->setSockConfig([
            'driver' => $sSockDriverName,
            'iAutoShutdownServerAfterXSeconds' => 40,
            'bServerAutoPowerOnByConfigViaCliOnLocal' => true,
            //     'bObfuscateCommunicationBetweenClientServer' => true,
            'iServerMemoryMb' => 16,
            //     'socketPort' => 27501 + strlen($sSockDriverName),// + rand(0, 99);
        ], true);
        $oManager = AfrCache::getManager();

        $sInfo = print_r($oApp, true);
        $oRepo = $oManager->store();
        $this->assertSame($sSockDriverName, $oManager->getDefaultDriver(), $sInfo);

        //    foreach (self::putProvider() as $aDataSet){
        //        list($sKey,$mData) = $aDataSet;
        $this->assertSame(true, $oRepo->put($sKey, $mData, 5));
        if (is_object($mData)) {
            $this->assertEquals($mData, $oRepo->get($sKey));
        } else {
            $this->assertSame($mData, $oRepo->get($sKey));
        }
        //     }


        $this->assertSame(true, $oRepo->getStore() instanceof AfrClientStore);
        if (rand(1, 140) == 37) {
            $this->assertSame(true, is_array($oRepo->getStore()->shutdownServer()));
        }

    }


    /**
     * @test
     */
    public function nullStoreTest(): void
    {
        AfrCacheApp::getInstance()->setNullConfig(true);
        // OR $oManager->setDefaultDriver('null');
        $oManager = AfrCache::getManager();

        $sInfo = print_r(AfrCacheApp::getInstance()['config'], true);
        $oStore = $oManager->getStore();
        $this->assertSame('null', $oManager->getDefaultDriver(), $sInfo);
        $this->assertSame(true, is_bool($oStore->put('ff', 4, 5)));
        $this->assertSame(null, $oStore->get('ff'));
        $this->assertSame(true, strpos(get_class($oStore), 'NullStore') !== false);
    }

    /**
     * @test
     * @dataProvider putProvider
     */
    public function arrayStoreTest(string $sKey, $mData): void
    {
        $sDriverName = 'array' . (substr($sKey, -1) === 'i' ? 'i' : '');
        $oApp = AfrCacheApp::getInstance()->setArrayConfig(
            (bool)rand(0, 1),
            true,
            [
                'driver' => $sDriverName,
                //    'extend' => 'array',
            ]
        );
        $oManager = AfrCache::getManager();

        $sInfo = print_r($oApp, true);
        $oRepo = $oManager->store();
        $this->assertSame($sDriverName, $oManager->getDefaultDriver(), $sInfo);
        $this->assertSame(true, $oRepo->put($sKey, $mData, 5));
        if (is_object($mData)) {
            $this->assertEquals($mData, $oRepo->get($sKey));
        } else {
            $this->assertSame($mData, $oRepo->get($sKey));
        }

        //$this->assertSame(true, strpos(get_class($oRepo),'ArrayStore')!==false);
        $this->assertSame(true, $oRepo->getStore()  instanceof \Autoframe\Components\SocketCache\LaravelPort\Cache\ArrayStore);

    }


    /**
     * @test
     * @dataProvider putProvider
     */
    public function fileStoreTest(string $sKey, $mData): void
    {
        $sDriverName = 'file' . (substr($sKey, -1) === 'i' ? 'i' : '');
        $sDriverName = 'file';
        $oApp = AfrCacheApp::getInstance()->setFileConfig(
            true,
            [
                'driver' => $sDriverName,
                'path' => __DIR__ . DIRECTORY_SEPARATOR . 'fileCache',
            ]
        );
        // OR $oManager->setDefaultDriver('null');
        $oManager = AfrCache::getManager();

        $sInfo = print_r($oApp, true);
        $oRepo = $oManager->store();
        $this->assertSame($sDriverName, $oManager->getDefaultDriver(), $sInfo);
        $this->assertSame(true, $oRepo->put($sKey, $mData, 5));
        if (is_object($mData)) {
            $this->assertEquals($mData, $oRepo->get($sKey));
        } else {
            $this->assertSame($mData, $oRepo->get($sKey));
        }

        //$this->assertSame(true, strpos(get_class($oRepo),'ArrayStore')!==false);
        $this->assertSame(true, $oRepo->getStore() instanceof \Autoframe\Components\SocketCache\LaravelPort\Cache\FileStore);

    }

    /**
     * @test
     * @dataProvider putProvider
     */
    public function redisStoreTest(string $sKey, $mData): void
    { //TODO not tested!!!
        $oApp = AfrCacheApp::getInstance();
        if (!$oApp->testRedis() || 1) {
            $this->assertSame(true, true);
            return;
        }

        $sDriverName = 'redis';
        $oApp->setRedisConfig(
            true,
            [
                'driver' => $sDriverName,
            ]
        );
        // OR $oManager->setDefaultDriver('null');
        $oManager = AfrCache::getManager();

        $sInfo = print_r($oApp, true);
        $oRepo = $oManager->store();
        $this->assertSame($sDriverName, $oManager->getDefaultDriver(), $sInfo);
        $this->assertSame(true, $oRepo->put($sKey, $mData, 5));
        if (is_object($mData)) {
            $this->assertEquals($mData, $oRepo->get($sKey));
        } else {
            $this->assertSame($mData, $oRepo->get($sKey));
        }

    }


    /**
     * @test
     * @dataProvider putProvider
     */
    public function memcachedStoreTest(string $sKey, $mData): void
    {
        $oApp = AfrCacheApp::getInstance();
        if (!$oApp->testMemcached()) {
            $this->assertSame(true, true);
            return;
        }

        if ($pf = @fsockopen(
            'localhost',
            11211,
            $err,
            $err_string,
            1 / 250)
        ) {
            fclose($pf);
        }
        else{
            echo 'Memcached supported by server is unavailable';// die;
            $this->assertSame(true, true);
            return;
        }

        $sDriverName = 'memcached';
        $oApp->setMemcachedConfig(
            true,
            [
                'driver' => $sDriverName,
                'servers' => $oApp->parseMemcachedServers('localhost:11211:100'),
            ]
        );
        // OR $oManager->setDefaultDriver('null');
        $oManager = AfrCache::getManager();

        $sInfo = print_r($oApp, true);
        $oRepo = $oManager->store();
        $this->assertSame($sDriverName, $oManager->getDefaultDriver(), $sInfo);
        $this->assertSame(true, $oRepo->put($sKey, $mData, 3500));
        if (is_object($mData)) {
            $this->assertEquals($mData, $oRepo->get($sKey));
        } else {
            $this->assertSame($mData, $oRepo->get($sKey));
        }

        //$this->assertSame(true, strpos(get_class($oRepo),'ArrayStore')!==false);
        $this->assertSame(true, $oRepo->getStore() instanceof  \Autoframe\Components\SocketCache\LaravelPort\Cache\MemcachedStore);

    }


    /**
     * @test
     * @dataProvider putProvider
     */
    public function apcStoreTest(string $sKey, $mData): void
    {

        $oApp = AfrCacheApp::getInstance();
        if (!$oApp->testApc()) {
            $this->assertSame(true, true);
            return;
        }

        if ($mData === false) {
            $mData = null; // apc will store false as null ... c'est la vie
        }

        $sDriverName = 'apc';
        $oApp->setApcConfig(true);
        $oManager = AfrCache::getManager();

        $sInfo = print_r($oApp, true);
        $oRepo = $oManager->store();
        $this->assertSame($sDriverName, $oManager->getDefaultDriver(), $sInfo);
        $this->assertSame(true, $oRepo->put($sKey, $mData, 20));
        if (is_object($mData)) {
            $this->assertEquals($mData, $oRepo->get($sKey));
        } else {
            $this->assertSame($mData, $oRepo->get($sKey));
        }

        //$this->assertSame(true, strpos(get_class($oRepo),'ArrayStore')!==false);
        $this->assertSame(true, $oRepo->getStore() instanceof \Autoframe\Components\SocketCache\LaravelPort\Cache\ApcStore);
    }

    /**
     * @test
     */
    public function appTest(): void
    {
        $this->assertSame(true, is_array(AfrCacheApp::getInstance()['config']));
        $this->assertSame(true, is_array(AfrCacheApp::getInstance()->getAvailableRepositoryNames()));
        $this->assertSame(true, is_array(AfrCacheApp::getInstance()->getSupportedRepositoryTypes()));
    }


    /**
     * @test
     */
    public function AfrRepositoryAutoSelectorTest(): void
    {
        $sKeyName = $sKeyVal = 'sKeyName';
        AfrRepositoryAutoSelector::setToUseRepositories(
            AfrRepositoryAutoSelector::SECONDARY_LOAD,
            ['file']
        );
        $oRepo = AfrRepositoryAutoSelector::selectRepoByKeyNs(
            AfrRepositoryAutoSelector::prefixKeyForRepo(
                $sKeyName,
                AfrRepositoryAutoSelector::SECONDARY_LOAD
            )
        );
        $oRepo->set($sKeyName,$sKeyVal,2);

        $oRepo = AfrRepositoryAutoSelector::selectRepoByKeyNs('H2\\2\\' . $sKeyName);
        $this->assertSame(true, $oRepo instanceof \Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Repository);

        $this->assertSame($sKeyVal, $oRepo->get($sKeyName));
        $oRepo->clear();
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