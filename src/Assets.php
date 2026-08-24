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
        $this->scripts = array_merge($this->scripts, (array) $assets);

        return $this;
    }

    public function addStyles(array|string $assets): static
    {
        $this->styles = array_merge($this->styles, (array) $assets);

        return $this;
    }

    public function addStylesDirectly(array|string $assets, array $attributes = []): static
    {
        foreach ((array) $assets as $item) {
            $item = ltrim(trim($item), '/');

            // Keyed lookup instead of an in_array() scan over the stored arrays: O(1), and
            // a bare re-registration can no longer wipe attributes an earlier caller set.
            if (isset($this->appendedStyles[$item]) && empty($attributes)) {
                continue;
            }

            $this->appendedStyles[$item] = [
                'src' => $item,
                'attributes' => $attributes,
            ];
        }

        return $this;
    }

    public function addScriptsDirectly(
        array|string $assets,
        string $location = self::ASSETS_SCRIPT_POSITION_FOOTER,
        array $attributes = []
    ): static {
        // An unknown location would auto-vivify a bucket that is never rendered,
        // silently dropping the asset.
        $location = $this->normalizeLocation($location);

        foreach ((array) $assets as $item) {
            $item = ltrim(trim($item), '/');

            if (isset($this->appendedScripts[$location][$item]) && empty($attributes)) {
                continue;
            }

            $this->appendedScripts[$location][$item] = [
                'src' => $item,
                'attributes' => $attributes,
            ];
        }

        return $this;
    }

    public function removeStyles(array|string $assets): static
    {
        $this->styles = array_values(array_diff($this->styles, (array) $assets));

        return $this;
    }

    public function removeScripts(array|string $assets): static
    {
        $this->scripts = array_values(array_diff($this->scripts, (array) $assets));

        return $this;
    }

    public function removeItemDirectly(array|string $assets, ?string $location = null): static
    {
        $locations = $location && in_array($location, $this->supportedLocations())
            ? [$location]
            : $this->supportedLocations();

        foreach ((array) $assets as $item) {
            $item = ltrim(trim($item), '/');

            foreach ($locations as $bucket) {
                unset($this->appendedScripts[$bucket][$item]);
            }
        }

        return $this;
    }

    /**
     * Get all scripts in current module based on location (`header` or `footer`).
     * An empty location returns every script, appended ones included.
     */
    public function getScripts(?string $location = null): array
    {
        $this->scripts = array_unique($this->scripts);

        $scripts = [];

        foreach ($this->scripts as $script) {
            // Resolve the resource node once. The previous implementation walked the
            // config tree separately for location/use_cdn/attributes/src (~7 dot-path
            // lookups per asset) on every call.
            $resource = $this->resolveResource('resources.scripts.' . $script);

            if (! $resource) {
                continue;
            }

            if (! empty($location) && $location !== Arr::get($resource, 'location')) {
                continue; // Skip assets that don't match this location
            }

            foreach ($this->resolveSource($resource, $location) as $item) {
                $scripts[] = $item;
            }
        }

        if (empty($location)) {
            foreach ($this->supportedLocations() as $bucket) {
                $scripts = array_merge($scripts, $this->appendedScripts[$bucket] ?? []);
            }

            return $scripts;
        }

        return array_merge($scripts, Arr::get($this->appendedScripts, $location, []));
    }

    /**
     * Get all CSS in current module. Append last CSS to current module.
     */
    public function getStyles(array $lastStyles = []): array
    {
        if (! empty($lastStyles)) {
            $this->styles = array_merge($this->styles, $lastStyles);
        }

        // Scripts flagged with `include_style` must contribute their stylesheet here,
        // no matter where the script itself renders. Resolving them from getScripts()
        // dropped the styles of every footer script, because the <head> was already out.
        $this->addStylesFromScripts();

        $this->styles = array_unique($this->styles);

        $styles = [];

        foreach ($this->styles as $style) {
            $resource = $this->resolveResource('resources.styles.' . $style);

            if (! $resource) {
                continue;
            }

            foreach ($this->resolveSource($resource) as $item) {
                $styles[] = $item;
            }
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
     * Queue the stylesheets of every registered script declaring `include_style`.
     */
    protected function addStylesFromScripts(): void
    {
        foreach (array_unique($this->scripts) as $script) {
            if (Arr::get($this->config, 'resources.scripts.' . $script . '.include_style')) {
                $this->styles[] = $script;
            }
        }
    }

    /**
     * Get script item.
     *
     * Kept for backwards compatibility only; the render path resolves the resource node
     * once and calls resolveSource() instead. `include_style` is handled up-front by
     * addStylesFromScripts(), so this method's addStyles() call is now redundant.
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

        if (! in_array($type, ['style', 'script'])) {
            return $html;
        }

        $resource = $this->resolveResource('resources.' . $type . 's.' . $name);

        if (! $resource) {
            return $html;
        }

        foreach ((array) $this->resolveSourceUrl($resource) as $item) {
            $html .= $this->htmlBuilder->{$type}($item, ['class' => 'hidden'])->toHtml();
        }

        return $html;
    }

    /**
     * Accepts either an already resolved resource node or a dot-path into the config.
     * Returns an empty array when the resource does not exist.
     */
    protected function resolveResource(array|string $resource): array
    {
        if (is_array($resource)) {
            return $resource;
        }

        $resolved = Arr::get($this->config, $resource);

        return is_array($resolved) ? $resolved : [];
    }

    /**
     * Signature preserved for subclasses that override it: narrowing a widened parameter
     * type is a fatal LSP error in PHP, so the config-path variants stay exactly as they
     * were and delegate to the resolved-node variants below.
     *
     * @return array|string
     */
    protected function getSourceUrl(string $configName)
    {
        return $this->resolveSourceUrl($this->resolveResource($configName));
    }

    protected function isUsingCdn(string $configName): bool
    {
        return $this->resolveUsingCdn($this->resolveResource($configName));
    }

    protected function getSource(string $configName, ?string $location = null): array
    {
        return $this->resolveSource($this->resolveResource($configName), $location);
    }

    /**
     * @return array|string
     */
    protected function resolveSourceUrl(array $resource)
    {
        if (! $resource) {
            return '';
        }

        return $this->resolveUsingCdn($resource)
            ? Arr::get($resource, 'src.cdn')
            : Arr::get($resource, 'src.local');
    }

    protected function resolveUsingCdn(array $resource): bool
    {
        return Arr::get($resource, 'use_cdn', false) && ! Arr::get($this->config, 'offline', true);
    }

    protected function resolveSource(array $resource, ?string $location = null): array
    {
        if (! $resource) {
            return [];
        }

        $isUsingCdn = $this->resolveUsingCdn($resource);

        $attributes = $isUsingCdn ? [] : Arr::get($resource, 'attributes', []);

        $src = $this->resolveSourceUrl($resource);

        $scripts = [];

        foreach ((array) $src as $s) {
            if (! $s) {
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
            isset($resource['fallback'])) {
            $scripts[] = [
                'src' => $src,
                'fallback' => $resource['fallback'],
                'fallbackURL' => Arr::get($resource, 'src.local'),
            ];
        }

        return $scripts;
    }

    protected function normalizeLocation(string $location): string
    {
        return in_array($location, $this->supportedLocations())
            ? $location
            : self::ASSETS_SCRIPT_POSITION_FOOTER;
    }

    protected function supportedLocations(): array
    {
        return [self::ASSETS_SCRIPT_POSITION_HEADER, self::ASSETS_SCRIPT_POSITION_FOOTER];
    }

    public function getBuildVersion(): string
    {
        return $this->build = Arr::get($this->config, 'enable_version')
            ? '?v=' . Arr::get($this->config, 'version')
            : '';
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
