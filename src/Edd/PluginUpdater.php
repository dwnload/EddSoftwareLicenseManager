<?php
/**
 * Allows plugins to use their own update API.
 *
 * @author Easy Digital Downloads
 * @link https://github.com/easydigitaldownloads/EDD-License-handler/blob/master/EDD_SL_Plugin_Updater.php
 * @version 1.6.14
 */

namespace Dwnload\EddSoftwareLicenseManager\Edd;

use TheFrosty\WP\Utils\WpHooksInterface;

/**
 * Class PluginUpdater
 *
 * @package Dwnload\EddSoftwareLicenseManager\Edd
 */
class PluginUpdater implements WpHooksInterface {

    /** @var  PluginData $plugin_data */
    private $plugin_data;

    /**
     * PluginUpdater constructor.
     *
     * @param array $args {
     *
     * @type string $api_url        URL of your website where EDD lives, ie: trailingslashit( https://dwnload.io )
     * @type string $plugin_file    Base file path of the root plugin file, ie: __FILE__
     * @type array $api_data        Optional additional data to send with the API calls.
     * @type string $license        License Key.
     * @type string $name           plugin_basename( __FILE__ )
     * @type string $slug           basename( __FILE__, '.php' )
     * @type string $version        Current version
     * @type bool $wp_override      Override WP? Defaults to false.
     * @type bool $beta             Allow beta updates? Defaults to false.
     * }
     */
    public function __construct( array $args = [] ) {
        global $edd_plugin_data;

        // Make sure $plugin_file is the first key in the array!
        $args = [ 'plugin_file' => $args['plugin_file'] ] + $args;
        $args = $args + [ 'license' => $args['api_data']['license'] ?? '' ];
        $this->plugin_data = new PluginData( $args );
        $edd_plugin_data[ $this->plugin_data->getSlug() ] = $this->plugin_data->getApiData();
    }

    /**
     * Set up WordPress filters to hook into WP's update process.
     */
    public function addHooks() {
        $name = $this->plugin_data->getName();
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugins_api_filter' ], 10, 3 );
        remove_action( 'after_plugin_row_' . $name, 'wp_plugin_update_row', 10 );
        add_action( 'after_plugin_row_' . $name, [ $this, 'show_update_notification' ], 10, 2 );
        add_action( 'admin_init', [ $this, 'show_changelog' ] );
    }

    /**
     * Check for Updates at the defined API endpoint and modify the update array.
     *
     * This function dives into the update API just when WordPress creates its update array,
     * then adds a custom API call and injects the custom plugin data retrieved from the API.
     * It is reassembled from parts of the native WordPress plugin update code.
     * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
     *
     * @uses api_request()
     *
     * @param array $_transient_data Update array build by WordPress.
     *
     * @return array Modified update array with custom plugin data.
     */
    public function check_update( array $_transient_data ) {
        global $pagenow;

        if ( ! is_object( $_transient_data ) ) {
            $_transient_data = new \stdClass();
        }

        if ( is_multisite() && 'plugins.php' === $pagenow ) {
            return $_transient_data;
        }

        if ( ! empty( $_transient_data->response ) &&
            ! empty( $_transient_data->response[ $this->plugin_data->getName() ] ) &&
            $this->plugin_data->getWpOverride() === false
        ) {
            return $_transient_data;
        }

        $version_info = $this->get_cached_version_info();

        if ( false === $version_info ) {
            $version_info = $this->api_request( 'plugin_latest_version', [
                'slug' => $this->plugin_data->getSlug(),
                'beta' => $this->plugin_data->getBeta(),
            ] );

            $this->set_version_info_cache( $version_info );
        }

        if ( false !== $version_info && is_object( $version_info ) && isset( $version_info->new_version ) ) {

            if ( version_compare( $this->plugin_data->getVersion(), $version_info->new_version, '<' ) ) {
                $_transient_data->response[ $this->plugin_data->getName() ] = $version_info;
            }

            $_transient_data->last_checked = current_time( 'timestamp' );
            $_transient_data->checked[ $this->plugin_data->getName() ] = $this->plugin_data->getVersion();
        }

        return $_transient_data;
    }

    /**
     * show update notification row -- needed for Multisite subsites, because WP won't tell you
     * otherwise!
     *
     * @param string $file
     * @param array $plugin
     */
    public function show_update_notification( $file, $plugin ) {
        if ( is_network_admin() ) {
            return;
        }

        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }

        if ( ! is_multisite() ) {
            return;
        }

        if ( $this->plugin_data->getName() !== $file ) {
            return;
        }

        // Remove our filter on the site transient
        remove_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ], 10 );

        $update_cache = get_site_transient( 'update_plugins' );
        $update_cache = is_object( $update_cache ) ? $update_cache : new \stdClass();

        if ( empty( $update_cache->response ) || empty( $update_cache->response[ $this->plugin_data->getName() ] ) ) {

            $version_info = $this->get_cached_version_info();

            if ( $version_info === false ) {
                $version_info = $this->api_request( 'plugin_latest_version', [
                    'slug' => $this->plugin_data->getSlug(),
                    'beta' => $this->plugin_data->getBeta(),
                ] );

                $this->set_version_info_cache( $version_info );
            }

            if ( ! is_object( $version_info ) ) {
                return;
            }

            if ( version_compare( $this->plugin_data->getVersion(), $version_info->new_version, '<' ) ) {
                $update_cache->response[ $this->plugin_data->getName() ] = $version_info;
            }

            $update_cache->last_checked = current_time( 'timestamp' );
            $update_cache->checked[ $this->plugin_data->getName() ] = $this->plugin_data->getVersion();

            set_site_transient( 'update_plugins', $update_cache );
        } else {
            $version_info = $update_cache->response[ $this->plugin_data->getName() ];
        }

        // Restore our filter
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );

        if ( ! empty( $update_cache->response[ $this->plugin_data->getName() ] ) && version_compare( $this->plugin_data->getVersion(), $version_info->new_version, '<' ) ) {

            // build a plugin list row, with update notification
            echo '<tr class="plugin-update-tr" id="' . $this->plugin_data->getSlug() . '-update" data-slug="' . $this->plugin_data->getSlug() . '" data-plugin="' . $this->plugin_data->getSlug() . '/' . $file . '">';
            echo '<td colspan="3" class="plugin-update colspanchange">';
            echo '<div class="update-message notice inline notice-warning notice-alt">';

            $changelog_link = self_admin_url( 'index.php?edd_sl_action=view_plugin_changelog&plugin=' . $this->plugin_data->getName() . '&slug=' . $this->plugin_data->getSlug() . '&TB_iframe=true&width=772&height=911' );

            if ( empty( $version_info->download_link ) ) {
                printf(
                    __( 'There is a new version of %1$s available. %2$sView version %3$s details%4$s.', 'easy-digital-downloads' ),
                    esc_html( $version_info->name ),
                    '<a target="_blank" class="thickbox" href="' . esc_url( $changelog_link ) . '">',
                    esc_html( $version_info->new_version ),
                    '</a>'
                );
            } else {
                printf(
                    __( 'There is a new version of %1$s available. %2$sView version %3$s details%4$s or %5$supdate now%6$s.', 'easy-digital-downloads' ),
                    esc_html( $version_info->name ),
                    '<a target="_blank" class="thickbox" href="' . esc_url( $changelog_link ) . '">',
                    esc_html( $version_info->new_version ),
                    '</a>',
                    '<a href="' . esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $this->plugin_data->getName(), 'upgrade-plugin_' . $this->plugin_data->getName() ) ) . '">',
                    '</a>'
                );
            }

            do_action( "in_plugin_update_message-{$file}", $plugin, $version_info );

            echo '</div></td></tr>';
        }
    }

    /**
     * Updates information on the "View version x.x details" page with custom data.
     *
     * @uses api_request()
     *
     * @param mixed $_data
     * @param string $_action
     * @param object $_args
     *
     * @return object $_data
     */
    public function plugins_api_filter( $_data, $_action = '', $_args = null ) {
        if ( $_action !== 'plugin_information' ) {
            return $_data;
        }

        if ( ! isset( $_args->slug ) || ( $_args->slug != $this->plugin_data->getSlug() ) ) {
            return $_data;
        }

        $to_send = [
            'slug' => $this->plugin_data->getSlug(),
            'is_ssl' => is_ssl(),
            'fields' => [
                'banners' => [],
                'reviews' => false,
            ],
        ];

        $cache_key = 'edd_api_request_' .
            md5(
                serialize(
                    $this->plugin_data->getSlug() .
                    $this->plugin_data->getLicense() .
                    $this->plugin_data->getBeta()
                )
            );

        // Get the transient where we store the api request for this plugin for 24 hours
        $edd_api_request_transient = $this->get_cached_version_info( $cache_key );

        // If we have no transient-saved value, run the API, set a fresh transient with the API value, and return that value too right now.
        if ( empty( $edd_api_request_transient ) ) {

            $api_response = $this->api_request( 'plugin_information', $to_send );

            // Expires in 3 hours
            $this->set_version_info_cache( $api_response, $cache_key );

            if ( false !== $api_response ) {
                $_data = $api_response;
            }
        } else {
            $_data = $edd_api_request_transient;
        }

        // Convert sections into an associative array, since we're getting an object, but Core expects an array.
        if ( isset( $_data->sections ) && ! is_array( $_data->sections ) ) {
            $new_sections = [];
            foreach ( $_data->sections as $key => $value ) {
                $new_sections[ $key ] = $value;
            }

            $_data->sections = $new_sections;
        }

        // Convert banners into an associative array, since we're getting an object, but Core expects an array.
        if ( isset( $_data->banners ) && ! is_array( $_data->banners ) ) {
            $new_banners = [];
            foreach ( $_data->banners as $key => $value ) {
                $new_banners[ $key ] = $value;
            }

            $_data->banners = $new_banners;
        }

        return $_data;
    }

    /**
     * Disable SSL verification in order to prevent download update failures
     *
     * @param array $args
     * @param string $url
     *
     * @return array
     */
    public function http_request_args( $args, $url ) {
        if ( strpos( $url, 'https://' ) !== false && strpos( $url, 'edd_action=package_download' ) ) {
            $args['sslverify'] = $this->verify_ssl();
        }

        return $args;
    }

    /**
     * Helper function to show the changelog.
     */
    public function show_changelog() {
        global $edd_plugin_data;

        if ( empty( $_REQUEST['edd_sl_action'] ) || $_REQUEST['edd_sl_action'] !== 'view_plugin_changelog' ) {
            return;
        }

        if ( empty( $_REQUEST['plugin'] ) ||  empty( $_REQUEST['slug'] ) ) {
            return;
        }

        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( __( 'You do not have permission to install plugin updates', 'easy-digital-downloads' ), __( 'Error', 'easy-digital-downloads' ), [ 'response' => 403 ] );
        }

        $data = $edd_plugin_data[ $_REQUEST['slug'] ];
        $beta = ! empty( $data['beta'] ) ? true : false;
        $cache_key = md5( 'edd_plugin_' . sanitize_key( $_REQUEST['plugin'] ) . '_' . $beta . '_version_info' );
        $version_info = $this->get_cached_version_info( $cache_key );

        if ( $version_info === false ) {
            $api_params = [
                'edd_action' => 'get_version',
                'item_name' => isset( $data['item_name'] ) ? $data['item_name'] : false,
                'item_id' => isset( $data['item_id'] ) ? $data['item_id'] : false,
                'slug' => $_REQUEST['slug'],
                'author' => $data['author'],
                'url' => home_url(),
                'beta' => ! empty( $data['beta'] ),
            ];

            $request = wp_remote_post( esc_url_raw( $this->plugin_data->getApiUrl() ), [
                'timeout' => 15,
                'sslverify' => $this->verify_ssl(),
                'body' => $api_params,
            ] );

            if ( ! is_wp_error( $request ) ) {
                $version_info = json_decode( wp_remote_retrieve_body( $request ) );
            }

            if ( ! empty( $version_info ) && isset( $version_info->sections ) ) {
                $version_info->sections = maybe_unserialize( $version_info->sections );
            } else {
                $version_info = false;
            }

            if ( ! empty( $version_info ) ) {
                foreach ( $version_info->sections as $key => $section ) {
                    $version_info->$key = (array)$section;
                }
            }

            $this->set_version_info_cache( $version_info, $cache_key );
        }

        if ( ! empty( $version_info ) && isset( $version_info->sections['changelog'] ) ) {
            echo '<div style="background:#fff;padding:10px;">' . $version_info->sections['changelog'] . '</div>';
        }

        exit;
    }

    /**
     * @param string $cache_key
     *
     * @return array|bool|mixed|object
     */
    protected function get_cached_version_info( string $cache_key = '' ) {
        if ( empty( $cache_key ) ) {
            $cache_key = $this->plugin_data->getCacheKey();
        }

        $cache = get_option( $cache_key );

        if ( empty( $cache['timeout'] ) || current_time( 'timestamp' ) > $cache['timeout'] ) {
            return false; // Cache is expired
        }

        return json_decode( $cache['value'] );
    }

    /**
     * @param string $value
     * @param string $cache_key
     */
    protected function set_version_info_cache( string $value = '', string $cache_key = '' ) {
        if ( empty( $cache_key ) ) {
            $cache_key = $this->plugin_data->getCacheKey();
        }

        $data = [
            'timeout' => strtotime( '+3 hours', current_time( 'timestamp' ) ),
            'value' => json_encode( $value ),
        ];

        update_option( $cache_key, $data, 'no' );
    }

    /**
     * Calls the API and, if successful, returns the object delivered by the API.
     *
     * @uses get_bloginfo()
     * @uses wp_remote_post()
     * @uses is_wp_error()
     *
     * @param string $_action The requested action.
     * @param array $_data Parameters for the API action.
     *
     * @return false|object
     */
    private function api_request( string $_action, array $_data ) {
        $data = array_merge( $this->plugin_data->getApiData(), $_data );

        if ( $this->plugin_data->getSlug() !== $data['slug'] ) {
            return false;
        }

        if ( $this->$this->plugin_data->getApiUrl() === trailingslashit( home_url() ) ) {
            return false; // Don't allow a plugin to ping itself
        }

        $api_params = [
            'edd_action' => $_action, // 'get_version',
            'license' => ! empty( $data['license'] ) ? $data['license'] : '',
            'item_name' => isset( $data['item_name'] ) ? $data['item_name'] : false,
            'item_id' => isset( $data['item_id'] ) ? $data['item_id'] : false,
            'version' => isset( $data['version'] ) ? $data['version'] : false,
            'slug' => $data['slug'],
            'author' => $data['author'],
            'url' => home_url(),
            'beta' => ! empty( $data['beta'] ),
        ];

        $request = wp_remote_post( esc_url_raw( $this->plugin_data->getApiUrl() ), [
            'timeout' => 15,
            'sslverify' => $this->verify_ssl(),
            'body' => $api_params,
        ] );

        if ( ! is_wp_error( $request ) ) {
            $request = json_decode( wp_remote_retrieve_body( $request ) );
        }

        if ( $request && isset( $request->sections ) ) {
            $request->sections = maybe_unserialize( $request->sections );
        } else {
            $request = false;
        }

        if ( $request && isset( $request->banners ) ) {
            $request->banners = maybe_unserialize( $request->banners );
        }

        if ( ! empty( $request->sections ) ) {
            foreach ( $request->sections as $key => $section ) {
                $request->$key = (array)$section;
            }
        }

        return $request;
    }

    /**
     * Returns if the SSL of the store should be verified.
     *
     * @since  1.6.13
     * @return bool
     */
    private function verify_ssl(): bool {
        return (bool)apply_filters( 'edd_sl_api_request_verify_ssl', true, $this );
    }
}
