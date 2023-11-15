<?php

declare(strict_types=1);

namespace Dwnload\EddSoftwareLicenseManager\Edd;

use TheFrosty\WpUtilities\Plugin\HooksTrait;
use TheFrosty\WpUtilities\Plugin\WpHooksInterface;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Allows plugins to use their own update API.
 * @version 1.9.2
 */
class PluginUpdater implements WpHooksInterface
{

    use HooksTrait;

    private string $api_url;
    private ?array $api_data;
    private string $plugin_file;
    private string $name;
    private string $slug;
    private string $version;
    private bool $wp_override;
    private bool $beta;
    private string $failed_request_cache_key;

    /**
     * Class constructor.
     * @param string $api_url The URL pointing to the custom API endpoint.
     * @param string $plugin_file Path to the plugin file.
     * @param array|null $api_data Optional data to send with API calls.
     */
    public function __construct(string $api_url, string $plugin_file, ?array $api_data = null)
    {
        global $edd_plugin_data;

        $this->api_url = trailingslashit($api_url);
        $this->api_data = $api_data;
        $this->plugin_file = $plugin_file;
        $this->name = plugin_basename($plugin_file);
        $this->slug = basename($plugin_file, '.php');
        $this->version = $api_data['version'];
        $this->wp_override = isset($api_data['wp_override']) && $api_data['wp_override'];
        $this->beta = !empty($this->api_data['beta']);
        $this->failed_request_cache_key = 'edd_sl_failed_http_' . md5($this->api_url);

        $edd_plugin_data[$this->slug] = $this->api_data;

        /**
         * Fires after the $edd_plugin_data is set up.
         * @param array $edd_plugin_data Array of EDD SL plugin data.
         */
        do_action('post_edd_sl_plugin_updater_setup', $edd_plugin_data);
    }

    /**
     * Add class hooks.
     */
    public function addHooks(): void
    {
        $this->addFilter('pre_set_site_transient_update_plugins', [$this, 'checkUpdate']);
        $this->addFilter('plugins_api', [$this, 'pluginsApiFilter'], 10, 3);
        $this->addAction('after_plugin_row', [$this, 'showUpdateNotification'], 10, 2);
        $this->addAction('admin_init', [$this, 'showChangelog']);
    }

    /**
     * Check for Updates at the defined API endpoint and modify the update array.
     *
     * This function dives into the update API just when WordPress creates its update array,
     * then adds a custom API call and injects the custom plugin data retrieved from the API.
     * It is reassembled from parts of the native WordPress plugin update code.
     * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
     *
     * @param mixed $value Update array build by WordPress.
     * @return array|\stdClass Modified update array with custom plugin data.
     */
    protected function checkUpdate(mixed $value): array|\stdClass
    {
        if (!is_object($value)) {
            $value = new \stdClass();
        }

        if (!empty($value->response) && !empty($value->response[$this->name]) && false === $this->wp_override) {
            return $value;
        }

        $current = $this->getRepoApiData();
        if (false !== $current && is_object($current) && isset($current->new_version)) {
            if (version_compare($this->version, $current->new_version, '<')) {
                $value->response[$this->name] = $current;
            } else {
                // Populating the no_update information is required to support auto-updates in WordPress 5.5.
                $value->no_update[$this->name] = $current;
            }
        }
        $value->last_checked = time();
        $value->checked[$this->name] = $this->version;

        return $value;
    }

    /**
     * Updates information on the "View version x.x details" page with custom data.
     * @param mixed $result
     * @param string $action
     * @param object|null $args
     * @return mixed
     */
    protected function pluginsApiFilter(mixed $result, string $action = '', object $args = null): mixed
    {
        if ('plugin_information' !== $action) {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $to_send = [
            'slug' => $this->slug,
            'is_ssl' => is_ssl(),
            'fields' => [
                'banners' => [],
                'reviews' => false,
                'icons' => [],
            ],
        ];

        // Get the transient where we store the api request for this plugin for 24 hours
        $edd_api_request_transient = $this->getCachedVersionInfo();

        //If we have no transient-saved value, run the API, set a fresh transient with the API value, and return that value too right now.
        if (empty($edd_api_request_transient)) {
            $api_response = $this->apiRequest('plugin_information', $to_send);

            if ($api_response !== false) {
                $this->setVersionInfoCache($api_response);
                $result = $api_response;
            }
        } else {
            $result = $edd_api_request_transient;
        }

        // Convert sections into an associative array, since we're getting an object, but Core expects an array.
        if (isset($result->sections) && !is_array($result->sections)) {
            $result->sections = $this->convertObjectToArray($result->sections);
        }

        // Convert banners into an associative array, since we're getting an object, but Core expects an array.
        if (isset($result->banners) && !is_array($result->banners)) {
            $result->banners = $this->convertObjectToArray($result->banners);
        }

        // Convert icons into an associative array, since we're getting an object, but Core expects an array.
        if (isset($result->icons) && !is_array($result->icons)) {
            $result->icons = $this->convertObjectToArray($result->icons);
        }

        // Convert contributors into an associative array, since we're getting an object, but Core expects an array.
        if (isset($result->contributors) && !is_array($result->contributors)) {
            $result->contributors = $this->convertObjectToArray($result->contributors);
        }

        if (!isset($result->plugin)) {
            $result->plugin = $this->name;
        }

        return $result;
    }
    
    /**
     * Show the update notification on multisite subsites.
     * @param string $file
     * @param array $plugin
     */
    protected function showUpdateNotification(string $file, array $plugin): void
    {
        // Return early if in the network admin, or if this is not a multisite install.
        if (is_network_admin() || !is_multisite()) {
            return;
        }

        // Allow single site admins to see that an update is available.
        if (!current_user_can('activate_plugins')) {
            return;
        }

        if ($this->name !== $file) {
            return;
        }

        // Do not print any message if update does not exist.
        $update_cache = get_site_transient('update_plugins');

        if (!isset($update_cache->response[$this->name])) {
            if (!is_object($update_cache)) {
                $update_cache = new \stdClass();
            }
            $update_cache->response[$this->name] = $this->getRepoApiData();
        }

        // Return early if this plugin isn't in the transient->response or if the site is running the current or newer version of the plugin.
        if (
            empty($update_cache->response[$this->name]) || 
            version_compare($this->version, $update_cache->response[$this->name]->new_version, '>=')
        ) {
            return;
        }

        printf(
            '<tr class="plugin-update-tr %3$s" id="%1$s-update" data-slug="%1$s" data-plugin="%2$s">',
            $this->slug,
            $file,
            in_array($this->name, $this->getActivePlugins(), true) ? 'active' : 'inactive'
        );

        echo '<td colspan="3" class="plugin-update colspanchange">';
        echo '<div class="update-message notice inline notice-warning notice-alt"><p>';

        $changelog_link = '';
        if (!empty($update_cache->response[$this->name]->sections->changelog)) {
            $changelog_link = add_query_arg(
                [
                    'edd_sl_action' => 'view_plugin_changelog',
                    'plugin' => urlencode($this->name),
                    'slug' => urlencode($this->slug),
                    'TB_iframe' => 'true',
                    'width' => 77,
                    'height' => 911,
                ],
                self_admin_url('index.php')
            );
        }
        $update_link = add_query_arg(
            [
                'action' => 'upgrade-plugin',
                'plugin' => urlencode($this->name),
            ],
            self_admin_url('update.php')
        );

        printf(
        /* translators: the plugin name. */
            esc_html__('There is a new version of %1$s available.', 'edd-software-license-manager'),
            esc_html($plugin['Name'])
        );

        if (!current_user_can('update_plugins')) {
            echo ' ';
            esc_html_e('Contact your network administrator to install the update.', 'edd-software-license-manager');
        } elseif (empty($update_cache->response[$this->name]->package) && !empty($changelog_link)) {
            echo ' ';
            printf(
            /* translators: 1. opening anchor tag, do not translate 2. the new plugin version 3. closing anchor tag, do not translate. */
                __('%1$sView version %2$s details%3$s.', 'edd-software-license-manager'),
                '<a target="_blank" class="thickbox open-plugin-details-modal" href="' . esc_url(
                    $changelog_link
                ) . '">',
                esc_html($update_cache->response[$this->name]->new_version),
                '</a>'
            );
        } elseif (!empty($changelog_link)) {
            echo ' ';
            printf(
                __('%1$sView version %2$s details%3$s or %4$supdate now%5$s.', 'edd-software-license-manager'),
                '<a target="_blank" class="thickbox open-plugin-details-modal" href="' . esc_url(
                    $changelog_link
                ) . '">',
                esc_html($update_cache->response[$this->name]->new_version),
                '</a>',
                '<a target="_blank" class="update-link" href="' . esc_url(
                    wp_nonce_url($update_link, 'upgrade-plugin_' . $file)
                ) . '">',
                '</a>'
            );
        } else {
            printf(
                ' %1$s%2$s%3$s',
                '<a target="_blank" class="update-link" href="' . esc_url(
                    wp_nonce_url($update_link, 'upgrade-plugin_' . $file)
                ) . '">',
                esc_html__('Update now.', 'edd-software-license-manager'),
                '</a>'
            );
        }

        do_action("in_plugin_update_message-$file", $plugin, $plugin);

        echo '</p></div></td></tr>';
    }

    /**
     * If available, show the changelog.
     */
    protected function showChangelog(): void
    {
        if (empty($_REQUEST['edd_sl_action']) || 'view_plugin_changelog' !== $_REQUEST['edd_sl_action']) {
            return;
        }

        if (empty($_REQUEST['plugin'])) {
            return;
        }

        if (empty($_REQUEST['slug']) || $this->slug !== $_REQUEST['slug']) {
            return;
        }

        if (!current_user_can('update_plugins')) {
            wp_die(
                esc_html__('You do not have permission to install plugin updates', 'edd-software-license-manager'),
                esc_html__('Error', 'edd-software-license-manager'),
                ['response' => 403]
            );
        }

        $version_info = $this->getRepoApiData();
        if (isset($version_info->sections)) {
            $sections = $this->convertObjectToArray($version_info->sections);
            if (!empty($sections['changelog'])) {
                echo '<div style="background:#fff;padding:10px;">' . wp_kses_post($sections['changelog']) . '</div>';
            }
        }

        exit;
    }

    /**
     * Get repo API data from store.
     * Save to cache.
     * @return false|object
     */
    private function getRepoApiData(): false|object
    {
        $version_info = $this->getCachedVersionInfo();

        if ($version_info === false) {
            $version_info = $this->apiRequest(
                'plugin_latest_version',
                [
                    'slug' => $this->slug,
                    'beta' => $this->beta,
                ]
            );
            if (!$version_info) {
                return false;
            }

            // This is required for your plugin to support auto-updates in WordPress 5.5.
            $version_info->plugin = $this->name;
            $version_info->id = $this->name;
            $version_info->tested = $this->getTestedVersion($version_info);

            $this->setVersionInfoCache($version_info);
        }

        return $version_info;
    }

    /**
     * Gets the plugin's tested version.
     *
     * @param object $version_info
     * @return null|string
     */
    private function getTestedVersion(object $version_info): ?string
    {
        // There is no tested version.
        if (empty($version_info->tested)) {
            return null;
        }

        // Strip off extra version data so the result is x.y or x.y.z.
        [$current_wp_version] = explode('-', get_bloginfo('version'));

        // The tested version is greater than or equal to the current WP version, no need to do anything.
        if (version_compare($version_info->tested, $current_wp_version, '>=')) {
            return $version_info->tested;
        }

        $current_version_parts = explode('.', $current_wp_version);
        $tested_parts = explode('.', $version_info->tested);

        // The current WordPress version is x.y.z, so update the tested version to match it.
        if (isset($current_version_parts[2]) && $current_version_parts[0] === $tested_parts[0] && $current_version_parts[1] === $tested_parts[1]) {
            $tested_parts[2] = $current_version_parts[2];
        }

        return implode('.', $tested_parts);
    }

    /**
     * Gets the plugins active in a multisite network.
     * @return array
     */
    private function getActivePlugins(): array
    {
        $active_plugins = (array)get_option('active_plugins');
        $active_network_plugins = (array)get_site_option('active_sitewide_plugins');

        return array_merge($active_plugins, array_keys($active_network_plugins));
    }

    /**
     * Convert some objects to arrays when injecting data into the update API
     * Some data like sections, banners, and icons are expected to be an associative array, however due to the JSON
     * decoding, they are objects. This method allows us to pass in the object and return an associative array.
     *
     * @param mixed $data
     * @return array
     */
    private function convertObjectToArray(mixed $data): array
    {
        if (!is_array($data) && !is_object($data)) {
            return [];
        }

        $new_data = [];
        foreach ($data as $key => $value) {
            $new_data[$key] = is_object($value) ? $this->convertObjectToArray($value) : $value;
        }

        return $new_data;
    }

    /**
     * Calls the API and, if successful, returns the object delivered by the API.
     *
     * @param string $action The requested action.
     * @param array $data Parameters for the API action.
     * @return object|bool
     */
    private function apiRequest(string $action, array $data): object|bool
    {
        $data = array_merge($this->api_data, $data);

        if ($data['slug'] !== $this->slug) {
            return false;
        }

        // Don't allow a plugin to ping itself
        if (trailingslashit(home_url()) === $this->api_url) {
            return false;
        }

        if ($this->requestRecentlyFailed()) {
            return false;
        }

        return $this->getVersionFromRemote();
    }

    /**
     * Determines if a request has recently failed.
     * @return bool
     */
    private function requestRecentlyFailed(): bool
    {
        $failed_request_details = get_option($this->failed_request_cache_key);

        // Request has never failed.
        if (empty($failed_request_details) || !is_numeric($failed_request_details)) {
            return false;
        }

        /*
         * Request previously failed, but the timeout has expired.
         * This means we're allowed to try again.
         */
        if (time() > $failed_request_details) {
            delete_option($this->failed_request_cache_key);

            return false;
        }

        return true;
    }

    /**
     * Logs a failed HTTP request for this API URL.
     * We set a timestamp for 1 hour from now. This prevents future API requests from being
     * made to this domain for 1 hour. Once the timestamp is in the past, API requests
     * will be allowed again. This way if the site is down for some reason we don't bombard
     * it with failed API requests.
     * @see EDD_SL_Plugin_Updater::requestRecentlyFailed
     */
    private function logFailedRequest(): void
    {
        update_option($this->failed_request_cache_key, strtotime('+1 hour'));
    }

    /**
     * Gets the current version information from the remote site.
     * @return object|bool
     */
    private function getVersionFromRemote(): object|bool
    {
        $api_params = [
            'edd_action' => 'get_version',
            'license' => !empty($this->api_data['license']) ? $this->api_data['license'] : '',
            'item_name' => $this->api_data['item_name'] ?? false,
            'item_id' => $this->api_data['item_id'] ?? false,
            'version' => $this->api_data['version'] ?? false,
            'slug' => $this->slug,
            'author' => $this->api_data['author'],
            'url' => home_url(),
            'beta' => $this->beta,
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo('version'),
        ];

        /**
         * Filters the parameters sent in the API request.
         *
         * @param array $api_params The array of data sent in the request.
         * @param array $this ->api_data    The array of data set up in the class constructor.
         * @param string $this ->plugin_file The full path and filename of the file.
         */
        $api_params = apply_filters(
            'edd_sl_plugin_updater_api_params',
            $api_params,
            $this->api_data,
            $this->plugin_file
        );

        $request = wp_remote_post(
            $this->api_url,
            [
                'timeout' => 15,
                'sslverify' => $this->verifySsl(),
                'body' => $api_params,
            ]
        );

        if (is_wp_error($request) || (200 !== wp_remote_retrieve_response_code($request))) {
            $this->logFailedRequest();

            return false;
        }

        $request = json_decode(wp_remote_retrieve_body($request));

        if ($request && isset($request->sections)) {
            $request->sections = maybe_unserialize($request->sections);
        } else {
            $request = false;
        }

        if ($request && isset($request->banners)) {
            $request->banners = maybe_unserialize($request->banners);
        }

        if ($request && isset($request->icons)) {
            $request->icons = maybe_unserialize($request->icons);
        }

        if (!empty($request->sections)) {
            foreach ($request->sections as $key => $section) {
                $request->$key = (array)$section;
            }
        }

        return $request;
    }

    /**
     * Get the version info from the cache, if it exists.
     * @param string $cache_key
     * @return object|bool
     */
    private function getCachedVersionInfo(string $cache_key = ''): object|bool
    {
        if (empty($cache_key)) {
            $cache_key = $this->getCacheKey();
        }

        $cache = get_option($cache_key);

        // Cache is expired
        if (empty($cache['timeout']) || time() > $cache['timeout']) {
            return false;
        }

        // We need to turn the icons into an array, thanks to WP Core forcing these into an object at some point.
        $cache['value'] = json_decode($cache['value']);
        if (!empty($cache['value']->icons)) {
            $cache['value']->icons = (array)$cache['value']->icons;
        }

        return $cache['value'];
    }

    /**
     * Adds the plugin version information to the database.
     * @param object $value
     * @param string|null $cache_key
     */
    private function setVersionInfoCache(object $value, ?string $cache_key = null): void
    {
        if (empty($cache_key)) {
            $cache_key = $this->getCacheKey();
        }

        $data = [
            'timeout' => strtotime('+3 hours', time()),
            'value' => wp_json_encode($value),
        ];

        update_option($cache_key, $data, 'no');

        // Delete the duplicate option
        delete_option('edd_api_request_' . md5(serialize($this->slug . $this->api_data['license'] . $this->beta)));
    }

    /**
     * Returns if the SSL of the store should be verified.
     * @return bool
     */
    private function verifySsl(): bool
    {
        return (bool)apply_filters('edd_sl_api_request_verify_ssl', true, $this);
    }

    /**
     * Gets the unique key (option name) for a plugin.
     * @return string
     */
    private function getCacheKey(): string
    {
        $string = $this->slug . $this->api_data['license'] . $this->beta;

        return 'edd_sl_' . md5(serialize($string));
    }
}
