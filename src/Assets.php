<?php

namespace Botble\Assets;

use Illuminate\Config\Repository;
use Illuminate\Support\HtmlString;
use Illuminate\Contracts\Routing\UrlGenerator;

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
     * The URL generator instance.
     *
     * @var \Illuminate\Contracts\Routing\UrlGenerator
     */
    protected $url;

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
     * @param  Repository $config
     * @param UrlGenerator $urlGenerator
     */
    public function __construct(Repository $config, UrlGenerator $urlGenerator)
    {
        $this->config = $config->get('assets');

        $this->scripts = $this->config['scripts'];

        $this->styles = $this->config['styles'];

        $this->url = $urlGenerator;
    }

    /**
     * Add scripts to current module.
     *
     * @param  array $assets
     * @return $this
     */
    public function addScripts($assets)
    {
        if (!is_array($assets)) {
            $assets = [$assets];
        }

        $this->scripts = array_merge($this->scripts, $assets);

        return $this;
    }

    /**
     * Add Css to current module.
     *
     * @param  array $assets
     * @return $this
     */
    public function addStyles($assets)
    {
        if (!is_array($assets)) {
            $assets = [$assets];
        }

        $this->styles = array_merge($this->styles, $assets);

        return $this;
    }

    /**
     * Add styles directly.
     *
     * @param  array|string $assets
     * @return $this
     */
    public function addStylesDirectly($assets)
    {
        if (!is_array($assets)) {
            $assets = [$assets];
        }

        foreach ($assets as &$item) {
            if (!in_array($item, $this->appendedStyles)) {
                $this->appendedStyles[] = [
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
     * @param  string|array $assets
     * @param  string $location
     * @return $this
     */
    public function addScriptsDirectly($assets, $location = self::ASSETS_SCRIPT_POSITION_FOOTER)
    {
        if (!is_array($assets)) {
            $assets = [$assets];
        }

        foreach ($assets as &$item) {
            if (!in_array($item, $this->appendedScripts[$location])) {
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
     * @param  array $assets
     * @return $this
     */
    public function removeStyles($assets)
    {
        if (!is_array($assets)) {
            $assets = [$assets];
        }

        foreach ($assets as $rem) {
            array_forget($this->styles, array_search($rem, $this->styles));
        }

        return $this;
    }

    /**
     * Add scripts.
     *
     * @param  array $assets
     * @return $this
     */
    public function removeScripts($assets)
    {
        if (!is_array($assets)) {
            $assets = [$assets];
        }

        foreach ($assets as $rem) {
            array_forget($this->scripts, array_search($rem, $this->scripts));
        }

        return $this;
    }

    /**
     * Get All scripts in current module.
     *
     * @param  string $location `header` or `footer`
     * @return array
     */
    public function getScripts($location = null)
    {
        $scripts = [];

        $this->scripts = array_unique($this->scripts);

        foreach ($this->scripts as $script) {
            $configName = 'resources.scripts.' . $script;

            if (array_has($this->config, $configName)) {
                if (!empty($location) && $location !== array_get($this->config, $configName . '.location')) {
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
     * @param  array $lastStyles Append last CSS to current module
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

            if (array_has($this->config, $configName)) {
                $src = array_get($this->config, $configName . '.src.local');

                $attributes = array_get($this->config, $configName . '.attributes', []);

                if (array_get($this->config, $configName . '.use_cdn') && !$this->config['offline']) {
                    $src = array_get($this->config, $configName . '.src.cdn');

                    $attributes = [];
                }

                foreach ((array)$src as $s) {
                    $styles[] = [
                        'src'        => $s,
                        'attributes' => $attributes,
                    ];
                }
            }
        }

        return array_merge($styles, $this->appendedStyles);
    }

    /**
     * Convert script to html.
     *
     * @param  string $name
     * @return  string|null
     */
    public function scriptToHtml($name)
    {
        return $this->itemToHtml($name, 'script');
    }

    /**
     * Convert style to html.
     *
     * @param  string $name
     */
    public function styleToHtml($name)
    {
        return $this->itemToHtml($name, 'style');
    }

    /**
     * Render assets to header.
     *
     * @param array $lastStyles
     * @return string
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
     * @throws \Throwable
     */
    public function renderFooter()
    {
        $bodyScripts = $this->getScripts(self::ASSETS_SCRIPT_POSITION_FOOTER);

        return view('assets::footer', compact('bodyScripts'))->render();
    }

    /**
     * Get script item.
     *
     * @param string $location
     * @param string $configName
     * @param string $script
     * @return array
     */
    protected function getScriptItem($location, $configName, $script)
    {
        $scripts = [];

        $src = array_get($this->config, $configName . '.src.local');

        $cdn = false;

        $attributes = array_get($this->config, $configName . '.attributes', []);

        if (array_get($this->config, $configName . '.use_cdn') && !$this->config['offline']) {
            $src = array_get($this->config, $configName . '.src.cdn');

            $cdn = true;

            $attributes = [];
        }

        if (!is_array($src)) {
            $scripts[] = [
                'src'        => $src,
                'attributes' => $attributes,
            ];
        } else {
            foreach ($src as $s) {
                $scripts[] = [
                    'src'        => $s,
                    'attributes' => $attributes,
                ];
            }
        }

        if (empty($src) &&
            $cdn &&
            $location === self::ASSETS_SCRIPT_POSITION_HEADER &&
            array_has($this->config, $configName . '.fallback')) {
            $scripts[] = $this->getFallbackScript($src, $configName);
        }

        if (array_get($this->config, $configName . '.include_style')) {
            $this->addStyles([$script]);
        }

        return $scripts;
    }

    /**
     * Fallback to local script if CDN fails.
     *
     * @param  string $src
     * @param  string $configName
     * @return array
     */
    protected function getFallbackScript($src, $configName)
    {
        return [
            'src'         => $src,
            'fallback'    => array_get($this->config, $configName . '.fallback'),
            'fallbackURL' => array_get($this->config, $configName . '.src.local'),
        ];
    }

    /**
     * Convert item to html.
     *
     * @param  string $name
     * @param  string $type
     * @return null|string
     */
    protected function itemToHtml($name, $type = 'style')
    {
        $html = '';

        if (!in_array($type, ['style', 'script'])) {
            return $html;
        }

        $config = 'resources.styles.' . $name;

        if ($type === 'script') {
            $config = 'resources.scripts.' . $name;
        }

        if (array_has($this->config, $config)) {
            $src = array_get($this->config, $config . '.src.local');

            if (array_get($this->config, $config . '.use_cdn') && !$this->config['offline']) {
                $src = array_get($this->config, $config . '.src.cdn');
            }

            if (!is_array($src)) {
                $src = [$src];
            }

            foreach ($src as $item) {
                $html .= $this->{$type}($item, ['class' => 'hidden'])->toHtml();
            }
        }

        return $html;
    }

    /**
     * @return string
     */
    public function getBuildVersion()
    {
        return $this->build = $this->config['enable_version'] ? '?v=' . $this->config['version'] : '';
    }

    /**
     * Generate a link to a JavaScript file.
     *
     * @param string $url
     * @param array $attributes
     * @param bool $secure
     *
     * @return \Illuminate\Support\HtmlString
     */
    public function script($url, $attributes = [], $secure = null)
    {
        $attributes['src'] = $this->url->asset($url, $secure);

        return $this->toHtmlString('<script' . $this->attributes($attributes) . '></script>');
    }

    /**
     * Generate a link to a CSS file.
     *
     * @param string $url
     * @param array $attributes
     * @param bool $secure
     *
     * @return \Illuminate\Support\HtmlString
     */
    public function style($url, $attributes = [], $secure = null)
    {
        $defaults = ['media' => 'all', 'type' => 'text/css', 'rel' => 'stylesheet'];

        $attributes = array_merge($defaults, $attributes);

        $attributes['href'] = $this->url->asset($url, $secure);

        return $this->toHtmlString('<link' . $this->attributes($attributes) . '>');
    }

    /**
     * Transform the string to an Html serializable object.
     *
     * @param $html
     *
     * @return \Illuminate\Support\HtmlString
     */
    protected function toHtmlString($html)
    {
        return new HtmlString($html);
    }

    /**
     * Build an HTML attribute string from an array.
     *
     * @param array $attributes
     *
     * @return string
     */
    public function attributes($attributes)
    {
        $html = [];

        foreach ((array)$attributes as $key => $value) {
            $element = $this->attributeElement($key, $value);

            if (!empty($element)) {
                $html[] = $element;
            }
        }

        return count($html) > 0 ? ' ' . implode(' ', $html) : '';
    }

    /**
     * Build a single attribute element.
     *
     * @param string $key
     * @param string $value
     *
     * @return string
     */
    protected function attributeElement($key, $value)
    {
        if (is_numeric($key)) {
            return $value;
        }

        // Treat boolean attributes as HTML properties
        if (is_bool($value) && $key !== 'value') {
            return $value ? $key : '';
        }

        if (is_array($value) && $key === 'class') {
            return 'class="' . implode(' ', $value) . '"';
        }

        if (!empty($value)) {
            return $key . '="' . e($value, false) . '"';
        }
    }
}
