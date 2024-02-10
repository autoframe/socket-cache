<?php
declare(strict_types=1);

namespace Unit;

use Autoframe\Components\Arr\AfrArrCollectionTrait;
use PHPUnit\Framework\TestCase;

class AfrArrMergeTest extends TestCase
{
    use AfrArrCollectionTrait;
	protected AfrFileMimeGeneratorClass $oGenerator;


	public static function insideProductionVendorDir(): bool
    {
        return strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false;
    }

    protected function setUp(): void
    {
        $this->oGenerator = new AfrFileMimeGeneratorClass();
    }
	
	protected function tearDown(): void
    {
        //cleanup between tests for static
    }

    public static function arrayMergeProfileProvider(): array
    {
        echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
        $aReturn = [];
        $aReturn[] = [ 1,2,3 ];
        return $aReturn;


    }

    /**
     * @test
     * @dataProvider arrayMergeProfileProvider
     */
    public function arrayMergeProfileTest(array $aOriginal, array $aNew, array $aExpected): void
    {
        $aMerged = $this->arrayMergeProfile($aOriginal, $aNew);
        $this->assertSame(serialize($aMerged), serialize($aExpected));
    }


}