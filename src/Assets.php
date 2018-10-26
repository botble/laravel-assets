<?php

namespace Botble\Assets;

use Collective\Html\HtmlBuilder;
use Illuminate\Config\Repository;

/**
 * Class Assets
 * @package Botble\Assets
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
    protected $javascript = [];

    /**
     * @var array
     */
    protected $stylesheets = [];

    /**
     * @var string
     */
    protected $build = '';

    /**
     * @var array
     */
    protected $appendedJs = [
        'top'    => [],
        'bottom' => [],
    ];

    /**
     * @var array
     */
    protected $appendedCss = [];

    /**
     * Assets constructor.
     * @author Sang Nguyen
     * @param Repository $config
     * @param HtmlBuilder $htmlBuilder
     */
    public function __construct(Repository $config, HtmlBuilder $htmlBuilder)
    {
        $this->config = $config;
        $this->htmlBuilder = $htmlBuilder;

        $this->javascript = $this->config->get('assets.javascript');
        $this->stylesheets = $this->config->get('assets.stylesheets');

        $this->build = $this->config->get('assets.enable_version') ? '?v=' . $this->config->get('assets.version') : '';
    }

    /**
     * Add Javascript to current module
     *
     * @param array $assets
     * @return $this
     * @author Sang Nguyen
     */
    public function addJavascript($assets)
    {
        if (!is_array($assets)) {
            $assets = [$assets];
        }
        $this->javascript = array_merge($this->javascript, $assets);
        return $this;
    }

    /**
     * Add Css to current module
     *
     * @param array $assets
     * @return $this
     * @author Sang Nguyen
     */
    public function addStylesheets($assets)
    {
        if (!is_array($assets)) {
            $assets = [$assets];
        }
        $this->stylesheets = array_merge($this->stylesheets, $assets);
        return $this;
    }

    /**
     * @param $assets
     * @return $this
     * @author Sang Nguyen
     */
    public function addStylesheetsDirectly($assets)
    {
        if (!is_array($assets)) {
            $assets = func_get_args();
        }
        foreach ($assets as &$item) {
            $item = $item . $this->build;
            if (!in_array($item, $this->appendedCss)) {
                $this->appendedCss[] = [
                    'src' => $item,
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
    public function addJavascriptDirectly($assets, $location = 'bottom')
    {
        if (!is_array($assets)) {
            $assets = func_get_args();
        }

        foreach ($assets as &$item) {
            $item = $item . $this->build;
            if (!in_array($item, $this->appendedJs[$location])) {
                $this->appendedJs[$location][] = ['src' => $item, 'attributes' => []];
            }
        }
        return $this;
    }

    /**
     * Remove Css to current module
     *
     * @param array $assets
     * @return $this
     * @author Sang Nguyen
     */
    public function removeStylesheets($assets)
    {
        if (!is_array($assets)) {
            $assets = [$assets];
        }
        foreach ($assets as $rem) {
            unset($this->stylesheets[array_search($rem, $this->stylesheets)]);
        }
        return $this;
    }

    /**
     * Add Javascript to current module
     *
     * @param array $assets
     * @return $this
     * @author Sang Nguyen
     */
    public function removeJavascript($assets)
    {
        if (!is_array($assets)) {
            $assets = [$assets];
        }
        foreach ($assets as $rem) {
            unset($this->javascript[array_search($rem, $this->javascript)]);
        }
        return $this;
    }

    /**
     * Get All Javascript in current module
     *
     * @param string $location : top or bottom
     * @param boolean $version : show version?
     * @return array
     * @author Sang Nguyen
     */
    public function getJavascript($location = null, $version = true)
    {
        $scripts = [];
        $this->javascript = array_unique($this->javascript);
        foreach ($this->javascript as $script) {
            $configName = 'assets.resources.javascript.' . $script;

            if ($this->config->has($configName)) {
                if (!empty($location) && $location != $this->config->get($configName . '.location')) {
                    continue; // Skip assets that don't match this location
                }

                $src = $this->config->get($configName . '.src.local');
                $cdn = false;
                $attributes = $this->config->get($configName . '.attributes', []);
                if ($this->config->get($configName . '.use_cdn') && !$this->config->get('assets.offline')) {
                    $src = $this->config->get($configName . '.src.cdn');
                    $cdn = true;
                    $attributes = [];
                }

                if ($this->config->get($configName . '.include_style')) {
                    $this->addStylesheets([$script]);
                }

                $version = $version ? $this->build : '';
                if (!is_array($src)) {
                    $scripts[] = ['src' => $src . $version, 'attributes' => $attributes];
                } else {
                    foreach ($src as $s) {
                        $scripts[] = ['src' => $s . $version, 'attributes' => $attributes];
                    }
                }

                if (empty($src) && $cdn && $location === 'top' && $this->config->has($configName . '.fallback')) {
                    // Fallback to local script if CDN fails
                    $fallback = $this->config->get($configName . '.fallback');
                    $scripts[] = [
                        'src'         => $src,
                        'fallback'    => $fallback,
                        'fallbackURL' => $this->config->get($configName . '.src.local'),
                    ];
                }
            }
        }

        if (isset($this->appendedJs[$location])) {
            $scripts = array_merge($scripts, $this->appendedJs[$location]);
        }

        return $scripts;
    }

    /**
     * Get All CSS in current module
     *
     * @param array $lastModules : append last CSS to current module
     * @param boolean $version : show version?
     * @return array
     * @author Sang Nguyen
     */
    public function getStylesheets($lastModules = [], $version = true)
    {
        $stylesheets = [];
        if (!empty($lastModules)) {
            $this->stylesheets = array_merge($this->stylesheets, $lastModules);
        }

        $this->stylesheets = array_unique($this->stylesheets);
        foreach ($this->stylesheets as $style) {
            $configName = 'assets.resources.stylesheets.' . $style;
            if ($this->config->has($configName)) {
                $src = $this->config->get($configName . '.src.local');
                $attributes = $this->config->get($configName . '.attributes', []);
                if ($this->config->get($configName . '.use_cdn') && !$this->config->get('assets.offline')) {
                    $src = $this->config->get($configName . '.src.cdn');
                    $attributes = [];
                }

                foreach ((array)$src as $s) {
                    $stylesheets[] = [
                        'src'        => $s . ($version ? $this->build : ''),
                        'attributes' => $attributes,
                    ];
                }
            }
        }

        return array_merge($stylesheets, $this->appendedCss);
    }

    /**
     * @param $name
     * @param bool $version
     * @author Sang Nguyen
     */
    public function javascriptToHtml($name, $version = true)
    {
        return $this->itemToHtml($name, $version, 'script');
    }

    /**
     * @param $name
     * @param bool $version
     * @author Sang Nguyen
     */
    public function stylesheetToHtml($name, $version = true)
    {
        return $this->itemToHtml($name, $version, 'style');
    }

    /**
     * @param $name
     * @param bool $version
     * @param string $type
     * @return null|string
     */
    protected function itemToHtml($name, $version = true, $type = 'style')
    {
        if (!in_array($type, ['style', 'script'])) {
            return null;
        }

        $config = 'assets.resources.stylesheets.' . $name;
        if ($type === 'script') {
            $config = 'assets.resources.javascript.' . $name;
        }

        if ($this->config->has($config)) {

            $src = $this->config->get($config . '.src.local');
            if ($this->config->get($config . '.use_cdn') && !$this->config->get('assets.offline')) {
                $src = $this->config->get($config . '.src.cdn');
            }

            if (!is_array($src)) {
                $src = [$src];
            }

            $html = '';
            foreach ($src as $item) {
                $html .= $this->htmlBuilder->{$type}($item . ($version ? $this->build : ''), ['class' => 'hidden'])->toHtml();
            }

            return $html;
        }

        return null;
    }

    /**
     * @return string
     * @throws \Throwable
     * @author Sang Nguyen
     */
    public function renderHeader()
    {
        $stylesheets = $this->getStylesheets(['core']);
        $headScripts = $this->getJavascript('top');
        return view('assets::header', compact('stylesheets', 'headScripts'))->render();
    }

    /**
     * @return string
     * @throws \Throwable
     * @author Sang Nguyen
     */
    public function renderFooter()
    {
        $bodyScripts = $this->getJavascript('bottom');
        return view('assets::footer', compact('bodyScripts'))->render();
    }
}
