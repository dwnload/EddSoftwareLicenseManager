# EDD Software License Manager

[![PHP from Packagist](https://img.shields.io/packagist/php-v/dwnload/edd-software-license-manager.svg)]()
[![Latest Stable Version](https://img.shields.io/packagist/v/dwnload/edd-software-license-manager.svg)](https://packagist.org/packages/dwnload/edd-software-license-manager)
[![Total Downloads](https://img.shields.io/packagist/dt/dwnload/edd-software-license-manager.svg)](https://packagist.org/packages/dwnload/edd-software-license-manager)
[![License](https://img.shields.io/packagist/l/dwnload/edd-software-license-manager.svg)](https://packagist.org/packages/dwnload/edd-software-license-manager)
![Build Status](https://github.com/dwnload/WpSettingsApi/actions/workflows/main.yml/badge.svg)
[![codecov](https://codecov.io/gh/dwnload/WpSettingsApi//branch/develop/graph/badge.svg)](https://codecov.io/gh/dwnload/WpSettingsApi/)

A PHP class abstraction for managing WordPress plugin licenses and auto-updates that are sold on an Easy Digital Downloads store.

## Package Installation (via Composer)

To install this package, edit your `composer.json` file:

```json
{
    "require": {
        "dwnload/edd-software-license-manager": "^2.0"
    }
}
```

Now run:

`$ composer install dwnload/edd-software-license-manager`

### How to use this package

```php
$license = \get_option(\Dwnload\EddSoftwareLicenseManager\Edd\AbstractLicenceManager::LICENSE_SETTING, []);
$data = [
    'license' => $license[$plugin_id]['license'] ?? '',
    'item_name' => 'Custom Login Style Pack #1', // Name of this plugin (matching your EDD Download title).
    'author' => 'Frosty Media',
    'item_id' => (int),
    'version' => '1.0.0',
];
\TheFrosty\WpUtilities\Plugin\Plugin $plugin
    ->add(new Edd\LicenseManager($plugin, $data))
    ->add(new Edd\PluginUpdater('https://frosty.media/', __FILE__, $data))
->initialize();
```