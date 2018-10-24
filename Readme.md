# Laravel Assets management

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
    Botble\Media\Providers\MediaServiceProvider::class,
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
{!! \Assets::renderHeader(); !!}
```

and before `body` tag close:

```php
{!! \Assets::renderFooter(); !!}
```

## Methods

### Add javascript

```php
\Assets::addJavascript(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::addJavascript(['app', 'bootstrap', 'jquery']);
```

### Add stylesheets

```php
\Assets::addStylesheets(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::addStylesheets(['bootstrap', 'font-awesome']);
```

### Remove javascript

```php
\Assets::removeJavascript(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::removeJavascript(['bootstrap']);
```

### Remove stylesheets

```php
\Assets::removeStylesheets(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::removeStylesheets(['font-awesome']);
```

### Others

- Set version for assets. Add to .env

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