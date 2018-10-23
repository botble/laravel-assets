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
        'top' => [],
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

        $version = env('ASSET_VERSION', time());
        $this->build = $this->config->get('assets.enable_version') ? '?v=' . $version : '';
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
                $this->appendedCss[] = ['src' => $item, 'attributes' => []];
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
     * @return $this;
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
     * @return $this;
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
        if (!empty($this->javascript)) {
            // get the final scripts need for page
            $this->javascript = array_unique($this->javascript);
            foreach ($this->javascript as $js) {
                $jsConfig = 'assets.resources.javascript.' . $js;

                if ($this->config->has($jsConfig)) {
                    if ($location != null && $this->config->get($jsConfig . '.location') !== $location) {
                        // Skip assets that don't match this location
                        continue;
                    }

                    $src = $this->config->get($jsConfig . '.src.local');
                    $cdn = false;
                    if ($this->config->get($jsConfig . '.use_cdn') && !$this->config->get('assets.offline')) {
                        $src = $this->config->get($jsConfig . '.src.cdn');
                        $cdn = true;
                    }

                    if ($this->config->get($jsConfig . '.include_style')) {
                        $this->addStylesheets([$js]);
                    }

                    $attributes = $this->config->get($jsConfig . '.attributes', []);
                    if ($cdn == false) {
                        array_forget($attributes, 'integrity');
                        array_forget($attributes, 'crossorigin');
                    }

                    $version = $version ? $this->build : '';
                    if (!is_array($src)) {
                        $scripts[] = ['src' => $src . $version, 'attributes' => $attributes];
                    } else {
                        foreach ($src as $s) {
                            $scripts[] = ['src' => $s . $version, 'attributes' => $attributes];
                        }
                    }

                    if (empty($src) && $cdn && $location === 'top' && $this->config->has($jsConfig . '.fallback')) {
                        // Fallback to local script if CDN fails
                        $fallback = $this->config->get($jsConfig . '.fallback');
                        $scripts[] = [
                            'src' => $src,
                            'fallback' => $fallback,
                            'fallbackURL' => $this->config->get($jsConfig . '.src.local'),
                        ];
                    }
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
        if (!empty($this->stylesheets)) {
            if (!empty($lastModules)) {
                $this->stylesheets = array_merge($this->stylesheets, $lastModules);
            }
            // get the final scripts need for page
            $this->stylesheets = array_unique($this->stylesheets);
            foreach ($this->stylesheets as $style) {
                if ($this->config->has('assets.resources.stylesheets.' . $style)) {
                    $src = $this->config->get('assets.resources.stylesheets.' . $style . '.src.local');
                    $cdn = false;
                    if ($this->config->get('assets.resources.stylesheets.' . $style . '.use_cdn') && !$this->config->get('assets.offline')) {
                        $src = $this->config->get('assets.resources.stylesheets.' . $style . '.src.cdn');
                        $cdn = true;
                    }

                    $attributes = $this->config->get('assets.resources.stylesheets.' . $style . '.attributes', []);
                    if ($cdn == false) {
                        array_forget($attributes, 'integrity');
                        array_forget($attributes, 'crossorigin');
                    }

                    if (!is_array($src)) {
                        $src = [$src];
                    }
                    foreach ($src as $s) {
                        $stylesheets[] = [
                            'src' => $s . ($version ? $this->build : ''),
                            'attributes' => $attributes,
                        ];
                    }
                }
            }
        }

        $stylesheets = array_merge($stylesheets, $this->appendedCss);

        return $stylesheets;
    }

    /**
     * @param $name
     * @param bool $version
     * @author Sang Nguyen
     */
    public function javascriptToHtml($name, $version = true)
    {
        $config = 'assets.resources.javascript.' . $name;
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
                $html .= $this->htmlBuilder->script($item . '?v=' . ($version ? $this->build : ''), ['class' => 'hidden'])->toHtml();
            }

            return $html;
        }

        return null;
    }

    /**
     * @param $name
     * @param bool $version
     * @author Sang Nguyen
     */
    public function stylesheetToHtml($name, $version = true)
    {
        $config = 'assets.resources.stylesheets.' . $name;
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
                $html .= $this->htmlBuilder->style($item . '?v=' . ($version ? $this->build : ''), ['class' => 'hidden'])->toHtml();
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
