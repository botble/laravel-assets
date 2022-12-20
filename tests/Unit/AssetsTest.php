<?php

namespace Botble\Assets\Tests\Unit;

use Botble\Assets\Assets;
use Mockery;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase
{
    /**
     * @var Mockery\Mock|Assets
     */
    protected $assets;

    protected function setUp(): void
    {
        $this->assets = Mockery::mock(Assets::class);
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetScripts()
    {
        $this->assets
            ->shouldReceive('getScripts')
            ->once()
            ->andReturn([
                [
                    'src' => '/js/app.js?v=1.0',
                    'attributes' => [],
                ],
            ]);

        $response = $this->assets->getScripts();

        $this->assertNotEmpty($response);

        $this->assertEmpty($response[0]['attributes']);
    }

    public function testGetStyles()
    {
        $this->assets
            ->shouldReceive('getStyles')
            ->once()
            ->andReturn([
                [
                    'src' => '/css/bootstrap.css?v=1.0',
                    'attributes' => [
                        'integrity' => 'sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB',
                        'crossorigin' => 'anonymous',
                    ],
                ],
            ]);

        $response = $this->assets->getStyles();

        $this->assertNotEmpty($response);
        $this->assertNotEmpty($response[0]['attributes']);
    }
}
