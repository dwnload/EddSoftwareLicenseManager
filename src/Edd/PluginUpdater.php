<?php
/**
 * Allows plugins to use their own update API.
 *
 * @forked from:
 * @author Easy Digital Downloads
 * @link https://github.com/easydigitaldownloads/EDD-License-handler/blob/master/EDD_SL_Plugin_Updater.php
 * @version 1.6.14
 */

namespace Dwnload\EddSoftwareLicenseManager\Edd;

use Dwnload\EddSoftwareLicenseManager\Edd\Models\PluginData;
use TheFrosty\WpUtilities\Plugin\WpHooksInterface;

/**
 * Class PluginUpdater
 *
 * @package Dwnload\EddSoftwareLicenseManager\Edd
 */
class PluginUpdater implements WpHooksInterface {

    /** @var  PluginData $plugin_data */
    private $plugin_data;

    // @formatter:off
    /**
     * PluginUpdater constructor.
     *
     * @param array $args {
     *      @type string $api_url        URL of your website where EDD lives, ie: `trailingslashit( https://dwnload.io )`
     *      @type string $plugin_file    Base file path of the root plugin file, ie: __FILE__
     *      @type array $api_data        Additional data to send with the API calls.
     *      {
     *          @type string $version        Current version number
     *          @type string $license        License key (ex. use get_option to retrieve from DB)
     *          @type string $item_name      Name of the plugin (title) (matching your EDD Download title)
     *          @type string $author         Author of the plugin
     *          @type bool $beta
     *      }
     *      @type string $license        License Key.
     *      @type string $name           plugin_basename( __FILE__ )
     *      @type string $slug           basename( __FILE__, '.php' )
     *      @type string $version        Current version
     *      @type bool $wp_override      Override WP? Defaults to false.
     *      @type bool $beta             Allow beta updates? Defaults to false.
     * }
     */
    // @formatter:on
    public function __construct( array $args = [] ) {
        global $edd_plugin_data;

        $this->plugin_data = new PluginData( $this->formatPluginData( $args ) );
        $edd_plugin_data[ $this->plugin_data->getSlug() ] = $this->plugin_data->getApiData();
    }

    /**
     * Set up WordPress filters to hook into WP's update process.
     */
    public function addHooks() {
        $name = $this->plugin_data->getName();
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'checkUpdate' ] );
        add_filter( 'plugins_api', [ $this, 'pluginsApiFilter' ], 10, 3 );
        remove_action( 'after_plugin_row_' . $name, 'wp_plugin_update_row', 10 );
        add_action( 'after_plugin_row_' . $name, [ $this, 'showUpdateNotification' ], 10, 2 );
        add_action( 'admin_init', [ $this, 'showChangelog' ] );
    }

    /**
     * Check for Updates at the defined API endpoint and modify the update array.
     *
     * This function dives into the update API just when WordPress creates its update array,
     * then adds a custom API call and injects the custom plugin data retrieved from the API.
     * It is reassembled from parts of the native WordPress plugin update code.
     * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
     *
     * @uses getApiRequest()
     *
     * @param mixed $value Update array build by WordPress.
     *
     * @return array Modified update array with custom plugin data.
     */
    public function checkUpdate( $value ) {
        global $pagenow;

        if ( is_multisite() && 'plugins.php' === $pagenow ) {
            return $value;
        }

        $_transient_data = $value;

        if ( ! is_object( $_transient_data ) ) {
            $_transient_data = new \stdClass();
        }

        if ( ! empty( $_transient_data->response ) &&
            ! empty( $_transient_data->response[ $this->plugin_data->getName() ] ) &&
            ! $this->plugin_data->getWpOverride()
        ) {
            return $value;
        }

        $version_info = $this->getCachedVersionInfo();

        if ( $version_info === false ) {
            $version_info = $this->getApiRequest( 'plugin_latest_version',
                [
                    'slug' => $this->plugin_data->getSlug(),
                    'beta' => $this->plugin_data->getBeta(),
                ]
            );

            $this->setVersionInfoCache( $version_info );
        }

        if ( $version_info !== false && is_object( $version_info ) && isset( $version_info->new_version ) ) {

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
     * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
     * @param array $plugin_data An array of plugin data.
     */
    public function showUpdateNotification( string $plugin_file, array $plugin_data ) {
        if ( is_network_admin() ||
            ! current_user_can( 'update_plugins' ) ||
            ! is_multisite() ||
            $this->plugin_data->getName() !== $plugin_file
        ) {
            return;
        }

        // Remove our filter on the site transient
        remove_filter( 'pre_set_site_transient_update_plugins', [ $this, 'checkUpdate' ], 10 );

        $update_cache = get_site_transient( 'update_plugins' );
        $update_cache = is_object( $update_cache ) ? $update_cache : new \stdClass();

        if ( empty( $update_cache->response ) || empty( $update_cache->response[ $this->plugin_data->getName() ] ) ) {

            $version_info = $this->getCachedVersionInfo();

            if ( $version_info === false ) {
                $version_info = $this->getApiRequest( 'plugin_latest_version', [
                    'slug' => $this->plugin_data->getSlug(),
                    'beta' => $this->plugin_data->getBeta(),
                ] );

                $this->setVersionInfoCache( $version_info );
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
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'checkUpdate' ] );

        if ( ! empty( $update_cache->response[ $this->plugin_data->getName() ] ) &&
            version_compare( $this->plugin_data->getVersion(), $version_info->new_version, '<' )
        ) {
            // build a plugin list row, with update notification
            echo '<tr class="plugin-update-tr" id="' . $this->plugin_data->getSlug() . '-update" data-slug="' .
                $this->plugin_data->getSlug() . '" data-plugin="' . $this->plugin_data->getSlug() . '/' . $plugin_file . '">';
            echo '<td colspan="3" class="plugin-update colspanchange">';
            echo '<div class="update-message notice inline notice-warning notice-alt">';

            $changelog_link = self_admin_url(
                'index.php?edd_sl_action=view_plugin_changelog&plugin=' . $this->plugin_data->getName() .
                '&slug=' . $this->plugin_data->getSlug() . '&TB_iframe=true&width=772&height=911'
            );

            if ( empty( $version_info->download_link ) ) {
                printf(
                    __( 'There is a new version of %1$s available. %2$sView version %3$s details%4$s.', 'dwnload' ),
                    esc_html( $version_info->name ),
                    '<a target="_blank" class="thickbox" href="' . esc_url( $changelog_link ) . '">',
                    esc_html( $version_info->new_version ),
                    '</a>'
                );
            } else {
                printf(
                    __( 'There is a new version of %1$s available. %2$sView version %3$s details%4$s or %5$supdate now%6$s.', 'dwnload' ),
                    esc_html( $version_info->name ),
                    '<a target="_blank" class="thickbox" href="' . esc_url( $changelog_link ) . '">',
                    esc_html( $version_info->new_version ),
                    '</a>',
                    '<a href="' . esc_url( wp_nonce_url(
                        self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) .
                        $this->plugin_data->getName(), 'upgrade-plugin_' . $this->plugin_data->getName()
                    ) ) . '">',
                    '</a>'
                );
            }

            /**
             *
             * @param array $plugin_data An array of plugin data.
             * @param \stdClass $version_info
             */
            do_action( "in_plugin_update_message-{$plugin_file}", $plugin_data, $version_info );

            echo '</div></td></tr>';
        }
    }

    /**
     * Updates information on the "View version x.x details" page with custom data.
     *
     * @uses getApiRequest()
     *
     * @param mixed $_data
     * @param string $_action
     * @param object $_args
     *
     * @return object $_data
     */
    public function pluginsApiFilter( $_data, $_action = '', $_args = null ) {
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
        $edd_api_request_transient = $this->getCachedVersionInfo( $cache_key );

        // If we have no transient-saved value, run the API, set a fresh transient with the API value, and return that value too right now.
        if ( empty( $edd_api_request_transient ) ) {

            $api_response = $this->getApiRequest( 'plugin_information', $to_send );

            // Expires in 3 hours
            $this->setVersionInfoCache( $api_response, $cache_key );

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
    public function getModifiedHttpRequestArgs( $args, $url ) {
        if ( strpos( $url, 'https://' ) !== false && strpos( $url, 'edd_action=package_download' ) ) {
            $args['sslverify'] = $this->sslVerify();
        }

        return $args;
    }

    /**
     * Helper function to show the changelog.
     */
    public function showChangelog() {
        global $edd_plugin_data;

        if ( empty( $_REQUEST['edd_sl_action'] ) || $_REQUEST['edd_sl_action'] !== 'view_plugin_changelog' ) {
            return;
        }

        if ( empty( $_REQUEST['plugin'] ) || empty( $_REQUEST['slug'] ) ) {
            return;
        }

        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die(
                esc_html__( 'You do not have permission to install plugin updates', 'dwnload' ),
                esc_html__( 'Error', 'dwnload' ),
                [ 'response' => \WP_Http::FORBIDDEN ]
            );
        }

        $data = $edd_plugin_data[ $_REQUEST['slug'] ];
        $beta = ! empty( $data['beta'] ) ? true : false;
        $cache_key = md5( 'edd_plugin_' . sanitize_key( $_REQUEST['plugin'] ) . '_' . $beta . '_version_info' );
        $version_info = $this->getCachedVersionInfo( $cache_key );

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
                'sslverify' => $this->sslVerify(),
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

            $this->setVersionInfoCache( $version_info, $cache_key );
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
    protected function getCachedVersionInfo( string $cache_key = '' ) {
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
    protected function setVersionInfoCache( string $value = '', string $cache_key = '' ) {
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
     * @param string $action The requested action.
     * @param array $args Parameters for the API action.
     *
     * @return false|object
     */
    private function getApiRequest( string $action, array $args ) {
        $data = array_merge( $this->plugin_data->getApiData(), $args );

        if ( $this->plugin_data->getSlug() !== $data['slug'] ) {
            return false;
        }

        if ( $this->plugin_data->getApiUrl() === trailingslashit( home_url() ) ) {
            return false; // Don't allow a plugin to ping itself
        }

        $api_params = [
            'edd_action' => $action, // 'get_version',
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
            'sslverify' => $this->sslVerify(),
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
     * @return bool
     */
    private function sslVerify(): bool {
        return (bool) apply_filters( 'edd_sl_api_request_verify_ssl', true, $this );
    }

    /**
     * @param array $args
     *
     * @return array
     */
    private function formatPluginData( array $args ): array {
        // Make sure $plugin_file is the first key in the array!
        $args = [ 'plugin_file' => $args['plugin_file'] ] + $args;

        $license = isset( $args['license'] ) ? $args['license'] : $args['api_data']['license'] ?? '';

        return $args + [ 'license' => $license ];
    }
}
