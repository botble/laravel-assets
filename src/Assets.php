<?php

namespace Botble\Assets;

use Illuminate\Config\Repository;
use Illuminate\Support\Arr;

/**
 * Class Assets.
 *
 * @since 22/07/2015 11:23 PM
 */
class Assets
{
    /**
     * @var Repository
     */
    protected $config;

    /**
     * @var HtmlBuilder
     */
    protected $htmlBuilder;

    /**
     * @var array
     */
    protected $scripts = [];

    /**
     * @var array
     */
    protected $styles = [];

    /**
     * @var array
     */
    protected $appendedScripts = [
        'header' => [],
        'footer' => [],
    ];

    /**
     * @var array
     */
    protected $appendedStyles = [];

    /**
     * @var string
     */
    protected $build = '';

    const ASSETS_SCRIPT_POSITION_HEADER = 'header';

    const ASSETS_SCRIPT_POSITION_FOOTER = 'footer';

    /**
     * Assets constructor.
     *
     * @param  Repository  $config
     * @param  HtmlBuilder  $htmlBuilder
     */
    public function __construct(Repository $config, HtmlBuilder $htmlBuilder)
    {
        $this->config = $config->get('assets');

        $this->scripts = $this->config['scripts'];

        $this->styles = $this->config['styles'];

        $this->htmlBuilder = $htmlBuilder;
    }

    /**
     * Add scripts to current module.
     *
     * @param  array  $assets
     * @return $this
     */
    public function addScripts($assets)
    {
        $this->scripts = array_merge($this->scripts, (array)$assets);

        return $this;
    }

    /**
     * Add Css to current module.
     *
     * @param  string[]  $assets
     * @return $this
     */
    public function addStyles($assets)
    {
        $this->styles = array_merge($this->styles, (array)$assets);

        return $this;
    }

    /**
     * Add styles directly.
     *
     * @param  array|string  $assets
     * @return $this
     */
    public function addStylesDirectly($assets)
    {
        foreach ((array)$assets as &$item) {
            $item = ltrim(trim($item), '/');

            if (!in_array($item, $this->appendedStyles)) {
                $this->appendedStyles[$item] = [
                    'src'        => $item,
                    'attributes' => [],
                ];
            }
        }

        return $this;
    }

    /**
     * Add scripts directly.
     *
     * @param  string|array  $assets
     * @param  string  $location
     * @return $this
     */
    public function addScriptsDirectly($assets, $location = self::ASSETS_SCRIPT_POSITION_FOOTER)
    {
        foreach ((array)$assets as &$item) {
            $item = ltrim(trim($item), '/');

            if (!in_array($item, $this->appendedScripts[$location])) {
                $this->appendedScripts[$location][$item] = [
                    'src'        => $item,
                    'attributes' => [],
                ];
            }
        }

        return $this;
    }

    /**
     * Remove Css to current module.
     *
     * @param  array  $assets
     * @return $this
     */
    public function removeStyles($assets)
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

    /**
     * Add scripts.
     *
     * @param  array  $assets
     * @return $this
     */
    public function removeScripts($assets)
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

    /**
     * Remove script/style items directly.
     *
     * @param  array|string  $assets
     * @param  string|null  $location  `header` or `footer`
     * @return $this
     */
    public function removeItemDirectly($assets, ?string $location = null)
    {
        foreach ((array)$assets as $item) {
            $item = ltrim(trim($item), '/');

            if ($location && in_array($location, [self::ASSETS_SCRIPT_POSITION_HEADER, self::ASSETS_SCRIPT_POSITION_FOOTER])) {
                Arr::forget($this->appendedScripts[$location], $item);
            } else {
                Arr::forget($this->appendedScripts[self::ASSETS_SCRIPT_POSITION_HEADER], $item);
                Arr::forget($this->appendedScripts[self::ASSETS_SCRIPT_POSITION_FOOTER], $item);
            }
        }

        return $this;
    }

    /**
     * Get All scripts in current module.
     *
     * @param  string  $location  `header` or `footer`
     * @return array
     */
    public function getScripts($location = null)
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
     * Get All CSS in current module.
     *
     * @param  array  $lastStyles  Append last CSS to current module
     * @return array
     */
    public function getStyles($lastStyles = [])
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
     *
     * @param  string  $name
     * @return string|null
     */
    public function scriptToHtml($name)
    {
        return $this->itemToHtml($name, 'script');
    }

    /**
     * Convert style to html.
     *
     * @param  string  $name
     */
    public function styleToHtml($name)
    {
        return $this->itemToHtml($name, 'style');
    }

    /**
     * Get script item.
     *
     * @param  string  $location
     * @param  string  $configName
     * @param  string  $script
     * @return array
     */
    protected function getScriptItem($location, $configName, $script)
    {
        $scripts = $this->getSource($configName, $location);

        if (Arr::get($this->config, $configName . '.include_style')) {
            $this->addStyles([$script]);
        }

        return $scripts;
    }

    /**
     * Convert item to html.
     *
     * @param  string  $name
     * @param  string  $type
     * @return string
     */
    protected function itemToHtml($name, $type = 'style')
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
     * @param  string  $configName
     * @return string|array
     */
    protected function getSourceUrl($configName)
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

    /**
     * @param  string  $configName
     * @return bool
     */
    protected function isUsingCdn($configName)
    {
        return Arr::get($this->config, $configName . '.use_cdn', false) && !$this->config['offline'];
    }

    /**
     * @param  string  $configName
     * @param  string  $location
     * @return array
     */
    protected function getSource($configName, $location = null)
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
                'src'        => $s,
                'attributes' => $attributes,
            ];
        }

        if (empty($src) &&
            $isUsingCdn &&
            $location === self::ASSETS_SCRIPT_POSITION_HEADER &&
            Arr::has($this->config, $configName . '.fallback')) {
            $scripts[] = [
                'src'         => $src,
                'fallback'    => Arr::get($this->config, $configName . '.fallback'),
                'fallbackURL' => Arr::get($this->config, $configName . '.src.local'),
            ];
        }

        return $scripts;
    }

    /**
     * @return string
     */
    public function getBuildVersion()
    {
        return $this->build = $this->config['enable_version'] ? '?v=' . $this->config['version'] : '';
    }

    /**
     * @return HtmlBuilder
     */
    public function getHtmlBuilder()
    {
        return $this->htmlBuilder;
    }

    /**
     * Render assets to header.
     *
     * @param  array  $lastStyles
     * @return string
     *
     * @throws \Throwable
     */
    public function renderHeader($lastStyles = [])
    {
        $styles = $this->getStyles($lastStyles);

        $headScripts = $this->getScripts(self::ASSETS_SCRIPT_POSITION_HEADER);

        return view('assets::header', compact('styles', 'headScripts'))->render();
    }

    /**
     * Render assets to footer.
     *
     * @return string
     *
     * @throws \Throwable
     */
    public function renderFooter()
    {
        $bodyScripts = $this->getScripts(self::ASSETS_SCRIPT_POSITION_FOOTER);

        return view('assets::footer', compact('bodyScripts'))->render();
    }
}
