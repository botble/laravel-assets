<?php

namespace Botble\Assets;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\HtmlString;

class HtmlBuilder
{
    /**
     * The URL generator instance.
     *
     * @var \Illuminate\Contracts\Routing\UrlGenerator
     */
    protected $url;

    /**
     * HtmlBuilder constructor.
     * @param UrlGenerator $urlGenerator
     */
    public function __construct(UrlGenerator $urlGenerator)
    {
        $this->url = $urlGenerator;
    }

    /**
     * Generate a link to a JavaScript file.
     *
     * @param string $url
     * @param array $attributes
     * @param bool $secure
     *
     * @return HtmlString|string
     */
    public function script($url, $attributes = [], $secure = null)
    {
        if (!$url) {
            return '';
        }

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
     * @return HtmlString|string
     */
    public function style($url, $attributes = [], $secure = null)
    {
        if (!$url) {
            return '';
        }

        $defaults = [
            'media' => 'all',
            'type'  => 'text/css',
            'rel'   => 'stylesheet',
        ];

        $attributes = array_merge($defaults, $attributes);

        $attributes['href'] = $this->url->asset($url, $secure);

        return $this->toHtmlString('<link' . $this->attributes($attributes) . '>');
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
     * Build a single attribute element.
     *
     * @param string $key
     * @param string $value
     *
     * @return string
     */
    protected function attributeElement($key, $value)
    {
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

        return $value;
    }
}
