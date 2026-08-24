<?php

namespace Botble\Assets\Tests\Unit;

use Botble\Assets\Assets;
use Botble\Assets\HtmlBuilder;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Mockery;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    protected function makeAssets(array $overrides = []): Assets
    {
        $config = array_merge([
            'offline' => true,
            'enable_version' => true,
            'version' => '1.0.0',
            'scripts' => ['app'],
            'styles' => ['bootstrap'],
            'resources' => [
                'scripts' => [
                    'app' => [
                        'use_cdn' => false,
                        'location' => 'footer',
                        'src' => ['local' => '/js/app.js'],
                    ],
                    'modernizr' => [
                        'use_cdn' => false,
                        'location' => 'header',
                        'src' => ['local' => '/js/modernizr.js'],
                    ],
                    // Rendered in the footer, but ships its own stylesheet.
                    'tree' => [
                        'use_cdn' => false,
                        'location' => 'footer',
                        'include_style' => true,
                        'src' => ['local' => '/js/tree.js'],
                    ],
                ],
                'styles' => [
                    'bootstrap' => [
                        'use_cdn' => false,
                        'src' => ['local' => '/css/bootstrap.css'],
                        'attributes' => ['crossorigin' => 'anonymous'],
                    ],
                    'tree' => [
                        'use_cdn' => false,
                        'src' => ['local' => '/css/tree.css'],
                    ],
                ],
            ],
        ], $overrides);

        return new Assets(
            new Repository(['assets' => $config]),
            new HtmlBuilder(Mockery::mock(UrlGenerator::class))
        );
    }

    public function testGetScripts(): void
    {
        $scripts = $this->makeAssets()->getScripts('footer');

        $this->assertNotEmpty($scripts);
        $this->assertSame('/js/app.js', $scripts[0]['src']);
        $this->assertEmpty($scripts[0]['attributes']);
    }

    public function testGetStyles(): void
    {
        $styles = $this->makeAssets()->getStyles();

        $this->assertNotEmpty($styles);
        $this->assertSame('/css/bootstrap.css', $styles[0]['src']);
        $this->assertSame(['crossorigin' => 'anonymous'], $styles[0]['attributes']);
    }

    public function testGetScriptsWithoutLocationReturnsFlatList(): void
    {
        $assets = $this->makeAssets()->addScripts(['modernizr']);
        $assets->addScriptsDirectly('/js/appended.js');

        $scripts = $assets->getScripts();

        $this->assertCount(3, $scripts);
        $this->assertSame(
            ['/js/app.js', '/js/modernizr.js', 'js/appended.js'],
            array_values(array_column($scripts, 'src'))
        );
    }

    public function testScriptsAreFilteredByLocation(): void
    {
        $assets = $this->makeAssets()->addScripts(['modernizr']);

        $this->assertSame(['/js/modernizr.js'], array_column($assets->getScripts('header'), 'src'));
        $this->assertSame(['/js/app.js'], array_column($assets->getScripts('footer'), 'src'));
    }

    public function testIncludeStyleOfFooterScriptIsQueuedForTheHeader(): void
    {
        $assets = $this->makeAssets()->addScripts(['tree']);

        $this->assertContains('/css/tree.css', array_column($assets->getStyles(), 'src'));
    }

    public function testReAddingAnAssetDoesNotDiscardItsAttributes(): void
    {
        $assets = $this->makeAssets();
        $assets->addScriptsDirectly('/js/a.js', 'footer', ['defer' => true]);
        $assets->addScriptsDirectly('/js/a.js');

        $scripts = $assets->getScripts('footer');

        $this->assertSame(['defer' => true], $scripts['js/a.js']['attributes']);
    }

    public function testReAddingAnAssetWithAttributesUpdatesThem(): void
    {
        $assets = $this->makeAssets();
        $assets->addStylesDirectly('/css/a.css', ['media' => 'print']);
        $assets->addStylesDirectly('/css/a.css', ['media' => 'screen']);

        $this->assertSame('screen', $assets->getStyles()['css/a.css']['attributes']['media']);
    }

    public function testUnknownScriptLocationFallsBackToFooter(): void
    {
        $assets = $this->makeAssets();
        $assets->addScriptsDirectly('/js/a.js', 'nowhere');

        $this->assertArrayHasKey('js/a.js', $assets->getScripts('footer'));
    }

    public function testRemoveScriptsAndStyles(): void
    {
        $assets = $this->makeAssets()->addScripts(['modernizr']);

        $this->assertEmpty($assets->removeScripts(['app', 'modernizr'])->getScripts());
        $this->assertEmpty($assets->removeStyles(['bootstrap'])->getStyles());
    }

    public function testRemoveItemDirectly(): void
    {
        $assets = $this->makeAssets();
        $assets->addScriptsDirectly('/js/a.js', 'header');
        $assets->removeItemDirectly('/js/a.js');

        $this->assertArrayNotHasKey('js/a.js', $assets->getScripts('header'));
    }

    public function testUnknownAssetsAreIgnored(): void
    {
        $assets = $this->makeAssets()->addScripts(['does-not-exist'])->addStyles(['missing']);

        $this->assertCount(1, $assets->getScripts('footer'));
        $this->assertCount(1, $assets->getStyles());
    }

    public function testCdnSourceIsUsedOnlyWhenNotOffline(): void
    {
        $resources = [
            'scripts' => [
                'app' => [
                    'use_cdn' => true,
                    'location' => 'footer',
                    'src' => ['local' => '/js/app.js', 'cdn' => 'https://cdn.test/app.js'],
                    'attributes' => ['integrity' => 'sha384-x'],
                ],
            ],
            'styles' => [],
        ];

        $offline = $this->makeAssets(['resources' => $resources, 'styles' => []]);
        $this->assertSame('/js/app.js', $offline->getScripts('footer')[0]['src']);

        $online = $this->makeAssets(['offline' => false, 'resources' => $resources, 'styles' => []]);
        $script = $online->getScripts('footer')[0];
        $this->assertSame('https://cdn.test/app.js', $script['src']);
        // Local-only attributes such as SRI hashes must not leak onto the CDN tag.
        $this->assertEmpty($script['attributes']);
    }

    public function testBuildVersion(): void
    {
        $this->assertSame('?v=1.0.0', $this->makeAssets()->getBuildVersion());
        $this->assertSame('', $this->makeAssets(['enable_version' => false])->getBuildVersion());
    }

    public function testStyleTagDoesNotEmitObsoleteTypeAttribute(): void
    {
        $url = Mockery::mock(UrlGenerator::class);
        $url->shouldReceive('asset')->andReturn('/css/a.css');

        $html = (new HtmlBuilder($url))->style('/css/a.css')->toHtml();

        $this->assertStringNotContainsString('text/css', $html);
        $this->assertStringContainsString('rel="stylesheet"', $html);
    }
}
