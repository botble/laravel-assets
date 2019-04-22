# Laravel Assets management

[![Latest Version](https://img.shields.io/github/release/botble/laravel-assets.svg?style=flat-square)](https://github.com/botble/laravel-assets/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/botble/laravel-assets/master.svg?style=flat-square)](https://travis-ci.org/botble/laravel-assets)
[![Quality Score](https://img.shields.io/scrutinizer/g/botble/laravel-assets.svg?style=flat-square)](https://scrutinizer-ci.com/g/botble/laravel-assets)
[![StyleCI](https://styleci.io/repos/154250020/shield)](https://styleci.io/repos/154250020)
[![Total Downloads](https://img.shields.io/packagist/dt/botble/assets.svg?style=flat-square)](https://packagist.org/packages/botble/assets)
[![Maintainability](https://api.codeclimate.com/v1/badges/a6e4612307e3b3bf8252/maintainability)](https://codeclimate.com/github/botble/laravel-assets/maintainability)

## Installation

```bash
composer require botble/assets
```

For version <= 5.4:

Add to section `providers` of `config/app.php`:

```php
// config/app.php
'providers' => [
    ...
    Botble\Assets\Providers\AssetsServiceProvider::class,
];
```

And add to `aliases` section:

```php
// config/app.php
'aliases' => [
    ...
    'Assets' => Botble\Assets\Facades\AssetsFacade::class,
];
```

All assets resource will be manage in config file so we need to publish config to use.

```bash
php artisan vendor:publish --provider="Botble\Assets\Providers\AssetsServiceProvider" --tag=config
```

Add to your master layout view, in `head` tag:

```php
{!! \Assets::renderHeader() !!}
```

and before `body` tag close:

```php
{!! \Assets::renderFooter() !!}
```

## Methods

### Add scripts

```php
\Assets::addScripts(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::addScripts(['app', 'bootstrap', 'jquery']);
```

### Add styles

```php
\Assets::addStyles(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::addStyles(['bootstrap', 'font-awesome']);
```

### Remove scripts

```php
\Assets::removeScripts(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::removeScripts(['bootstrap']);
```

### Remove styles

```php
\Assets::removeStyles(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::removeStyles(['font-awesome']);
```

### Others

- Set version for assets. Add to `.env`

```bash
ASSETS_VERSION=1.0
```

Then all assets will be added `?v=1.0`

- Change to online mode

```bash
ASSETS_OFFLINE=false
```

Then assets will be loaded from CDN if it's defined in config file.

- To disable versioning:

```bash
ASSETS_ENABLE_VERSION=false
```

## Contributors

- [Sang Nguyen](https://github.com/sangnguyenplus)
- [Dinh Quoc Han](https://github.com/dinhquochan)

## License
[MIT](LICENSE) © Sang Nguyen
