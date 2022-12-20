<?php

namespace Botble\Assets;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\HtmlString;

class HtmlBuilder
{
    /**
     * The URL generator instance.
     *
     * @var UrlGenerator
     */
    protected $url;

    public function __construct(UrlGenerator $urlGenerator)
    {
        $this->url = $urlGenerator;
    }

    /**
     * Generate a link to a JavaScript file.
     */
    public function script(string $url, array $attributes = [], ?bool $secure = null): HtmlString
    {
        if (! $url) {
            return new HtmlString();
        }

        $attributes['src'] = $this->url->asset($url, $secure);

        return $this->toHtmlString('<script' . $this->attributes($attributes) . '></script>');
    }

    /**
     * Generate a link to a CSS file.
     */
    public function style(string $url, array $attributes = [], ?bool $secure = null): HtmlString
    {
        if (! $url) {
            return new HtmlString();
        }

        $defaults = [
            'media' => 'all',
            'type' => 'text/css',
            'rel' => 'stylesheet',
        ];

        $attributes = array_merge($defaults, $attributes);

        $attributes['href'] = $this->url->asset($url, $secure);

        return $this->toHtmlString('<link' . $this->attributes($attributes) . '>');
    }

    /**
     * Build an HTML attribute string from an array.
     */
    public function attributes(array $attributes): string
    {
        $html = [];

        foreach ((array)$attributes as $key => $value) {
            $element = is_numeric($key) ? $key : $this->attributeElement($key, $value);

            if (empty($element)) {
                continue;
            }

            $html[] = $element;
        }

        return count($html) > 0 ? ' ' . implode(' ', $html) : '';
    }

    /**
     * Transform the string to an Html serializable object.
     */
    protected function toHtmlString(?string $html): HtmlString
    {
        return new HtmlString($html);
    }

    /**
     * Build a single attribute element.
     */
    protected function attributeElement(string $key, $value)
    {
        // Treat boolean attributes as HTML properties
        if (is_bool($value) && $key !== 'value') {
            return $value ? $key : '';
        }

        if (is_array($value) && $key === 'class') {
            return 'class="' . implode(' ', $value) . '"';
        }

        if (! empty($value)) {
            return $key . '="' . e($value, false) . '"';
        }

        return $value;
    }
}
