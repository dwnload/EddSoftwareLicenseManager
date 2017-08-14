# Dwnload EDD Software License Manager [![Build Status](https://travis-ci.org/dwnload/EddSoftwareLicenseManager.svg?branch=master)](https://travis-ci.org/dwnload/EddSoftwareLicenseManager)
A PHP class abstraction for managing WordPress plugin licenses and auto-updates that are sold on an Easy Digital Downloads store.

## Package Installation (via Composer)

To install this package, edit your `composer.json` file:

```js
{
    "require": {
        "dwnload/edd-software-license-manager": "^0.1"
    }
}
```

Now run:

`$ composer install dwnload/edd-software-license-manager`

### How to use this package

```php
use Dwnload\EddSoftwareLicenseManager\Edd;

// In the root of your plugin 
$args = [
    'api_url' => trailingslashit( https://plugingarden.dwnload.io ),
    'plugin_file' => __FILE__,
    'api_data' => [
        'version' 	=> '1.0', // current version number
        'license' 	=> $license_key, // license key (used get_option above to retrieve from DB)
        'item_name' => 'Super Cool Plugin', // name of this plugin (matching your EDD Download title)
        'author' 	=> 'Austin Passy', // author of this plugin
        'beta' => false,
    ],
    'name' => plugin_basename( __FILE__ )
    'slug' => basename( __FILE__, '.php' )
    'version' => '
    'wp_override' => false,
    'beta' => false,
];

if ( is_admin() ) {
    ( new Init() )
        ->add( new PluginUpdater( $args )
        ->initialize();
}
```