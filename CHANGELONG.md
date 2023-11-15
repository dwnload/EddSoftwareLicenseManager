# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.0.0 - 2023-11-15
- Bump min PHP version to 8.0.

## 1.4.0 - 2019-01-03
### Updated
- `thefrosty/wp-utilties` to v^1.3.1.
- `dwnload/wp-settings-api` to v^2.6.0.
- Bump min PHP version to 7.1.

## 1.3.0 - 2017-11-07
### Updated
- Update CSS to better reflect design on license page.
- Update JS to work with AJAX calls to the EDD store API.
- Add paragraph wrapper to the button for license management.
- Update LicenseManger class with better sanitization on AJAX action, and correct location of assets.
- Switch icon to spinner at 2x size in LicenseManger class.
- Add check for license key obfuscation before AJAX send.
- Make sure AJAX call always dies.

## 1.2.0 - 2017-11-06
### Updated
- Changed methods in PluginUpdater from snake case to camel case.
- Fixed incorrect variable variable calling plugin_data in PluginUpdater.

## 1.1.0 - 2017-08-17
### Updated
- Moved PluginData into the Models directory.

### Added
- The new License Manager and its license response models for responses from an EDD Api.

## 1.0.0 - 2017-08-13
- Initial commit.
### Added
- PluginUpdater (forked from EDD_SL_Plugin_Updater.php)