<?php

namespace Botble\Assets\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use Botble\Assets\Assets;

class AssetsTest extends TestCase
{
    /**
     * @var Mockery\Mock|Assets
     */
    protected $assets;

    public function setUp()
    {
        $this->assets = Mockery::mock(Assets::class);
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testGetJavascript()
    {
        $this->assets
            ->shouldReceive('getJavascript')
            ->once()
            ->andReturn([
                [
                    'src'        => '/js/app.js?v=1.0',
                    'attributes' => [],
                ],
            ]);

        $response = $this->assets->getJavascript();

        $this->assertNotEmpty($response);
        $this->assertEmpty($response[0]['attributes']);
    }

    public function testGetStylesheets()
    {
        $this->assets
            ->shouldReceive('getStylesheets')
            ->once()
            ->andReturn([
                [
                    'src'        => '/css/bootstrap.css?v=1.0',
                    'attributes' => [
                        'integrity' => 'sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB',
                        'crossorigin' => 'anonymous',
                    ],
                ],
            ]);

        $response = $this->assets->getStylesheets();

        $this->assertNotEmpty($response);
        $this->assertNotEmpty($response[0]['attributes']);
    }
}