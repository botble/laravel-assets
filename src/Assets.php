<?php

namespace Botble\Assets;

use Illuminate\Config\Repository;
use Illuminate\Support\Arr;

/**
 * @since 22/07/2015 11:23 PM
 */
class Assets
{
    protected array $config;

    protected HtmlBuilder $htmlBuilder;

    protected array $scripts = [];

    protected array $styles = [];

    protected array $appendedScripts = [
        'header' => [],
        'footer' => [],
    ];

    protected array $appendedStyles = [];

    protected string $build = '';

    public const ASSETS_SCRIPT_POSITION_HEADER = 'header';

    public const ASSETS_SCRIPT_POSITION_FOOTER = 'footer';

    public function __construct(Repository $config, HtmlBuilder $htmlBuilder)
    {
        $this->config = $config->get('assets');

        $this->scripts = $this->config['scripts'];

        $this->styles = $this->config['styles'];

        $this->htmlBuilder = $htmlBuilder;
    }

    public function addScripts(array|string $assets): static
    {
        $this->scripts = array_merge($this->scripts, (array)$assets);

        return $this;
    }

    public function addStyles(array|string $assets): static
    {
        $this->styles = array_merge($this->styles, (array)$assets);

        return $this;
    }

    public function addStylesDirectly(array|string $assets, array $attributes = []): static
    {
        foreach ((array)$assets as &$item) {
            $item = ltrim(trim($item), '/');

            if (!in_array($item, $this->appendedStyles)) {
                $this->appendedStyles[$item] = [
                    'src' => $item,
                    'attributes' => $attributes,
                ];
            }
        }

        return $this;
    }

    public function addScriptsDirectly(
        array|string $assets,
        string $location = self::ASSETS_SCRIPT_POSITION_FOOTER,
        array $attributes = []
    ): static {
        foreach ((array)$assets as &$item) {
            $item = ltrim(trim($item), '/');

            if (!in_array($item, $this->appendedScripts[$location])) {
                $this->appendedScripts[$location][$item] = [
                    'src' => $item,
                    'attributes' => $attributes,
                ];
            }
        }

        return $this;
    }

    public function removeStyles(array|string $assets): static
    {
        if (empty($this->styles)) {
            return $this;
        }

        foreach ((array)$assets as $rem) {
            $index = array_search($rem, $this->styles);
            if ($index === false) {
                continue;
            }

            Arr::forget($this->styles, $index);
        }

        return $this;
    }

    public function removeScripts(array|string $assets): static
    {
        if (empty($this->scripts)) {
            return $this;
        }

        foreach ((array)$assets as $rem) {
            $index = array_search($rem, $this->scripts);
            if ($index === false) {
                continue;
            }

            Arr::forget($this->scripts, $index);
        }

        return $this;
    }

    public function removeItemDirectly(array|string $assets, ?string $location = null): static
    {
        foreach ((array)$assets as $item) {
            $item = ltrim(trim($item), '/');

            if (
                $location
                && in_array($location, [self::ASSETS_SCRIPT_POSITION_HEADER, self::ASSETS_SCRIPT_POSITION_FOOTER])
            ) {
                Arr::forget($this->appendedScripts[$location], $item);
            } else {
                Arr::forget($this->appendedScripts[self::ASSETS_SCRIPT_POSITION_HEADER], $item);
                Arr::forget($this->appendedScripts[self::ASSETS_SCRIPT_POSITION_FOOTER], $item);
            }
        }

        return $this;
    }

    /**
     * Get All scripts in current module based on location (`header` or `footer`)
     */
    public function getScripts(?string $location = null): array
    {
        $scripts = [];

        $this->scripts = array_unique($this->scripts);

        foreach ($this->scripts as $script) {
            $configName = 'resources.scripts.' . $script;

            if (!empty($location) && $location !== Arr::get($this->config, $configName . '.location')) {
                continue; // Skip assets that don't match this location
            }

            $scripts = array_merge($scripts, $this->getScriptItem($location, $configName, $script));
        }

        return array_merge($scripts, Arr::get($this->appendedScripts, $location, []));
    }

    /**
     * Get All CSS in current module. Append last CSS to current module
     */
    public function getStyles(array $lastStyles = []): array
    {
        $styles = [];
        if (!empty($lastStyles)) {
            $this->styles = array_merge($this->styles, $lastStyles);
        }

        $this->styles = array_unique($this->styles);

        foreach ($this->styles as $style) {
            $configName = 'resources.styles.' . $style;

            $styles = array_merge($styles, $this->getSource($configName));
        }

        return array_merge($styles, $this->appendedStyles);
    }

    /**
     * Convert script to html.
     */
    public function scriptToHtml(string $name): ?string
    {
        return $this->itemToHtml($name, 'script');
    }

    /**
     * Convert style to html.
     */
    public function styleToHtml(string $name): ?string
    {
        return $this->itemToHtml($name);
    }

    /**
     * Get script item.
     */
    protected function getScriptItem(string $location, string $configName, string $script): array
    {
        $scripts = $this->getSource($configName, $location);

        if (Arr::get($this->config, $configName . '.include_style')) {
            $this->addStyles([$script]);
        }

        return $scripts;
    }

    /**
     * Convert item to html.
     */
    protected function itemToHtml(string $name, string $type = 'style'): string
    {
        $html = '';

        if (!in_array($type, ['style', 'script'])) {
            return $html;
        }

        $configName = 'resources.' . $type . 's.' . $name;

        if (!Arr::has($this->config, $configName)) {
            return $html;
        }

        $src = $this->getSourceUrl($configName);

        foreach ((array)$src as $item) {
            $html .= $this->htmlBuilder->{$type}($item, ['class' => 'hidden'])->toHtml();
        }

        return $html;
    }

    /**
     * @return array|string
     */
    protected function getSourceUrl(string $configName)
    {
        if (!Arr::has($this->config, $configName)) {
            return '';
        }

        $src = Arr::get($this->config, $configName . '.src.local');

        if ($this->isUsingCdn($configName)) {
            $src = Arr::get($this->config, $configName . '.src.cdn');
        }

        return $src;
    }

    protected function isUsingCdn(string $configName): bool
    {
        return Arr::get($this->config, $configName . '.use_cdn', false) && !$this->config['offline'];
    }

    protected function getSource(string $configName, ?string $location = null): array
    {
        $isUsingCdn = $this->isUsingCdn($configName);

        $attributes = $isUsingCdn ? [] : Arr::get($this->config, $configName . '.attributes', []);

        $src = $this->getSourceUrl($configName);

        $scripts = [];

        foreach ((array)$src as $s) {
            if (!$s) {
                continue;
            }

            $scripts[] = [
                'src' => $s,
                'attributes' => $attributes,
            ];
        }

        if (empty($src) &&
            $isUsingCdn &&
            $location === self::ASSETS_SCRIPT_POSITION_HEADER &&
            Arr::has($this->config, $configName . '.fallback')) {
            $scripts[] = [
                'src' => $src,
                'fallback' => Arr::get($this->config, $configName . '.fallback'),
                'fallbackURL' => Arr::get($this->config, $configName . '.src.local'),
            ];
        }

        return $scripts;
    }

    public function getBuildVersion(): string
    {
        return $this->build = $this->config['enable_version'] ? '?v=' . $this->config['version'] : '';
    }

    public function getHtmlBuilder(): HtmlBuilder
    {
        return $this->htmlBuilder;
    }

    /**
     * Render assets to header.
     */
    public function renderHeader(array $lastStyles = []): string
    {
        $styles = $this->getStyles($lastStyles);

        $headScripts = $this->getScripts(self::ASSETS_SCRIPT_POSITION_HEADER);

        return view('assets::header', compact('styles', 'headScripts'))->render();
    }

    /**
     * Render assets to footer.
     */
    public function renderFooter(): string
    {
        $bodyScripts = $this->getScripts(self::ASSETS_SCRIPT_POSITION_FOOTER);

        return view('assets::footer', compact('bodyScripts'))->render();
    }
}
