<?php

namespace Botble\Assets;

use Collective\Html\HtmlBuilder;
use Illuminate\Config\Repository;

/**
 * Class Assets.
 * @author Sang Nguyen
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
     * @author Sang Nguyen
     * @param Repository $config
     * @param HtmlBuilder $htmlBuilder
     */
    public function __construct(Repository $config, HtmlBuilder $htmlBuilder)
    {
        $this->config = $config->get('assets');
        $this->htmlBuilder = $htmlBuilder;

        $this->scripts = $this->config['scripts'];
        $this->styles = $this->config['styles'];

        $this->build = $this->config['enable_version'] ? '?v='.$this->config['version'] : '';
    }

    /**
     * Add scripts to current module.
     *
     * @param array $assets
     * @return $this
     * @author Sang Nguyen
     */
    public function addScripts($assets)
    {
        if (! is_array($assets)) {
            $assets = [$assets];
        }
        $this->scripts = array_merge($this->scripts, $assets);

        return $this;
    }

    /**
     * Add Css to current module.
     *
     * @param array $assets
     * @return $this
     * @author Sang Nguyen
     */
    public function addStyles($assets)
    {
        if (! is_array($assets)) {
            $assets = [$assets];
        }
        $this->styles = array_merge($this->styles, $assets);

        return $this;
    }

    /**
     * @param $assets
     * @return $this
     * @author Sang Nguyen
     */
    public function addStylesDirectly($assets)
    {
        if (! is_array($assets)) {
            $assets = func_get_args();
        }
        foreach ($assets as &$item) {
            $item = $item.$this->build;
            if (! in_array($item, $this->appendedStyles)) {
                $this->appendedStyles[] = [
                    'src'        => $item,
                    'attributes' => [],
                ];
            }
        }

        return $this;
    }

    /**
     * @param $assets
     * @param string $location
     * @return $this
     * @author Sang Nguyen
     */
    public function addScriptsDirectly($assets, $location = self::ASSETS_SCRIPT_POSITION_FOOTER)
    {
        if (! is_array($assets)) {
            $assets = func_get_args();
        }

        foreach ($assets as &$item) {
            $item = $item.$this->build;
            if (! in_array($item, $this->appendedScripts[$location])) {
                $this->appendedScripts[$location][] = [
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
     * @param array $assets
     * @return $this
     * @author Sang Nguyen
     */
    public function removeStyles($assets)
    {
        if (! is_array($assets)) {
            $assets = [$assets];
        }
        foreach ($assets as $rem) {
            unset($this->styles[array_search($rem, $this->styles)]);
        }

        return $this;
    }

    /**
     * Add scripts.
     *
     * @param array $assets
     * @return $this
     * @author Sang Nguyen
     */
    public function removeScripts($assets)
    {
        if (! is_array($assets)) {
            $assets = [$assets];
        }
        foreach ($assets as $rem) {
            unset($this->scripts[array_search($rem, $this->scripts)]);
        }

        return $this;
    }

    /**
     * Get All scripts in current module.
     *
     * @param string $location : top or bottom
     * @return array
     * @author Sang Nguyen
     */
    public function getScripts($location = null)
    {
        $scripts = [];
        $this->scripts = array_unique($this->scripts);
        foreach ($this->scripts as $script) {
            $configName = 'resources.scripts.'.$script;

            if (array_has($this->config, $configName)) {
                if ($location !== array_get($this->config, $configName.'.location')) {
                    continue; // Skip assets that don't match this location
                }

                $scripts = array_merge($scripts, $this->getScriptItem($location, $configName, $script));
            }
        }

        if (isset($this->appendedScripts[$location])) {
            $scripts = array_merge($scripts, $this->appendedScripts[$location]);
        }

        return $scripts;
    }

    /**
     * Get All CSS in current module.
     *
     * @param array $lastModules : append last CSS to current module
     * @return array
     * @author Sang Nguyen
     */
    public function getStyles($lastModules = [])
    {
        $styles = [];
        if (! empty($lastModules)) {
            $this->styles = array_merge($this->styles, $lastModules);
        }

        $this->styles = array_unique($this->styles);
        foreach ($this->styles as $style) {
            $configName = 'resources.styles.'.$style;
            if (array_has($this->config, $configName)) {
                $src = array_get($this->config, $configName.'.src.local');
                $attributes = array_get($this->config, $configName.'.attributes', []);
                if (array_get($this->config, $configName.'.use_cdn') && ! $this->config['offline']) {
                    $src = array_get($this->config, $configName.'.src.cdn');
                    $attributes = [];
                }

                foreach ((array) $src as $s) {
                    $styles[] = [
                        'src'        => $s.$this->build,
                        'attributes' => $attributes,
                    ];
                }
            }
        }

        return array_merge($styles, $this->appendedStyles);
    }

    /**
     * @param $name
     * @author Sang Nguyen
     */
    public function scriptToHtml($name)
    {
        return $this->itemToHtml($name, 'script');
    }

    /**
     * @param $name
     * @author Sang Nguyen
     */
    public function styleToHtml($name)
    {
        return $this->itemToHtml($name, 'style');
    }

    /**
     * @return string
     * @throws \Throwable
     * @author Sang Nguyen
     */
    public function renderHeader()
    {
        $styles = $this->getStyles(['core']);
        $headScripts = $this->getScripts(self::ASSETS_SCRIPT_POSITION_HEADER);

        return view('assets::header', compact('styles', 'headScripts'))->render();
    }

    /**
     * @return string
     * @throws \Throwable
     * @author Sang Nguyen
     */
    public function renderFooter()
    {
        $bodyScripts = $this->getScripts(self::ASSETS_SCRIPT_POSITION_FOOTER);

        return view('assets::footer', compact('bodyScripts'))->render();
    }

    /**
     * @param $location
     * @param $configName
     * @param $script
     * @return array
     */
    protected function getScriptItem($location, $configName, $script)
    {
        $scripts = [];
        $src = array_get($this->config, $configName.'.src.local');
        $cdn = false;
        $attributes = array_get($this->config, $configName.'.attributes', []);

        if (array_get($this->config, $configName.'.use_cdn') && ! $this->config['offline']) {
            $src = array_get($this->config, $configName.'.src.cdn');
            $cdn = true;
            $attributes = [];
        }

        if (array_get($this->config, $configName.'.include_style')) {
            $this->addStyles([$script]);
        }

        if (! is_array($src)) {
            $scripts[] = ['src' => $src.$this->build, 'attributes' => $attributes];
        } else {
            foreach ($src as $s) {
                $scripts[] = ['src' => $s.$this->build, 'attributes' => $attributes];
            }
        }

        if (empty($src) &&
            $cdn && $location === self::ASSETS_SCRIPT_POSITION_HEADER &&
            array_has($this->config, $configName.'.fallback')
        ) {
            $scripts[] = $this->getFallbackScript($src, $configName);
        }

        return $scripts;
    }

    /**
     * Fallback to local script if CDN fails.
     * @param $src
     * @param $configName
     * @return array
     */
    protected function getFallbackScript($src, $configName)
    {
        return [
            'src'         => $src,
            'fallback'    => array_get($this->config, $configName.'.fallback'),
            'fallbackURL' => array_get($this->config, $configName.'.src.local'),
        ];
    }

    /**
     * @param $name
     * @param string $type
     * @return null|string
     */
    protected function itemToHtml($name, $type = 'style')
    {
        if (! in_array($type, ['style', 'script'])) {
            return;
        }

        $config = 'resources.styles.'.$name;
        if ($type === 'script') {
            $config = 'resources.scripts.'.$name;
        }

        if (array_has($this->config, $config)) {
            $src = array_get($this->config, $config.'.src.local');
            if (array_get($this->config, $config.'.use_cdn') && ! $this->config['offline']) {
                $src = array_get($this->config, $config.'.src.cdn');
            }

            if (! is_array($src)) {
                $src = [$src];
            }

            $html = '';
            foreach ($src as $item) {
                $html .= $this->htmlBuilder->{$type}($item.$this->build, ['class' => 'hidden'])->toHtml();
            }

            return $html;
        }
    }
}
