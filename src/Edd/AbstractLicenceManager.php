<?php

namespace Dwnload\EddSoftwareLicenseManager\Edd;

use Dwnload\EddSoftwareLicenseManager\Edd\Models\ActivateLicense;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\CheckLicense;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\DeactivateLicense;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\LicenseStatus;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\PluginData;
use Dwnload\WpEmailDownload\EmailDownload;

/**
 * Class LicenceManager
 *
 * @package Dwnload\EddSoftwareLicenseManager\Edd
 */
abstract class AbstractLicenceManager {

    const ACTIVATE_LICENCE = 'activate_license';
    const CHECK_LICENCE = 'check_license';
    const DEACTIVATE_LICENCE = 'deactivate_license';
    const TRANSIENT_PREFIX = 'dwnload_edd_slm_';
    const LICENSE_SETTING = 'dwnload_license_data';

    /** @var  string $api_url */
    private $api_url;

    /**
     * Activates the license key.
     *
     * @param string $license The incoming POST license key
     * @param string $plugin_slug
     * @param int $item_id
     *
     * @return bool
     */
    protected function activateLicense( string $license, string $plugin_slug, int $item_id ): bool {
        if ( empty( $license ) ) {
            return false;
        }

        $api_params = [
            'edd_action' => self::ACTIVATE_LICENCE,
            'license' => $license,
            'item_id' => $item_id,
        ];

        $response = $this->getActivateLicense( $api_params );

        if ( $response->isValidResponse() &&
            strcasecmp( $response->getLicense(), LicenseStatus::LICENSE_ACTIVE ) === 0
        ) {
            $key = $this->getTransientKey( $plugin_slug . '_license_message' );
            $option = get_option( self::LICENSE_SETTING, [] );
            $option[ $plugin_slug ]['license'] = trim( $license );
            $option[ $plugin_slug ]['status'] = trim( $response->getLicense() );

            update_option( self::LICENSE_SETTING, $option );

            return delete_transient( $key );
        }

        return false;
    }

    /**
     * Deactivates the license key.
     *
     * @param string $license The incoming POST license key
     * @param string $plugin_slug
     * @param int $item_id
     *
     * @return bool
     */
    protected function deactivateLicense( string $license, string $plugin_slug, int $item_id ): bool {
        $api_params = [
            'edd_action' => self::DEACTIVATE_LICENCE,
            'license' => $license,
            'item_id' => $item_id,
        ];

        $response = $this->getDeactivateLicense( $api_params );

        if ( $response->isValidResponse() &&
            strcasecmp( $response->getLicense(), LicenseStatus::LICENSE_DEACTIVATED ) === 0
        ) {
            $key = $this->getTransientKey( $plugin_slug . '_license_message' );
            $option = get_option( self::LICENSE_SETTING, [] );
            $option[ $plugin_slug ]['license'] = trim( $license );
            $option[ $plugin_slug ]['status'] = '';

            update_option( self::LICENSE_SETTING, $option );

            return delete_transient( $key );
        }

        return false;
    }

    /**
     * Checks if license is valid and gets expire date.
     *
     * @param string $license The incoming POST license key
     * @param PluginData $plugin_data
     * @param bool $update_option
     *
     * @return string $message License status message.
     */
    protected function checkLicense( string $license = null, PluginData $plugin_data, bool $update_option = false ): string {
        if ( empty( $license ) ) {
            return $this->getStrings()['enter-key'];
        }

        $api_params = [
            'edd_action' => self::CHECK_LICENCE,
            'license' => $license,
            'item_id' => $plugin_data->getItemId(),
        ];

        $response = $this->getCheckLicense( $api_params );

        // If response doesn't include license data, return
        if ( ! $response->isValidResponse() ) {
            return $this->getStrings()['license-unknown'];
        }

        $expires = date_i18n( get_option( 'date_format' ), strtotime( $response->getExpires() ) );
        $renew_link = '<a href="' . esc_url( $this->getRenewalUrl( $license, $plugin_data->getItemId() ) ) . '" target="_blank">' . $this->getStrings()['renew'] . '</a>';


        // Unlimited ??
        if ( $response->getLicenseLimit() === 0 ) {
            $license_limit = $this->getStrings()['unlimited'];
        }

        if ( strcasecmp( $response->getLicense(), LicenseStatus::LICENSE_ACTIVE ) === 0 ) {
            $message = $this->getStrings()['license-key-is-active'] . ' ';
            $message .= sprintf( $this->getStrings()['expires%s'], $expires ) . ' ';
            $message .= sprintf( $this->getStrings()['%1$s/%2$-sites'], $response->getSiteCount(), $response->getLicenseLimit() );
        } elseif ( strcasecmp( $response->getLicense(), LicenseStatus::LICENSE_EXPIRED ) === 0 ) {
            if ( strtotime( $response->getExpires() ) > time() ) {
                $message = sprintf( $this->getStrings()['license-key-expired-%s'], $expires );
            } else {
                $message = $this->getStrings()['license-key-expired'];
                $message .= ' ' . $renew_link;
            }
        } elseif ( strcasecmp( $response->getLicense(), LicenseStatus::LICENSE_INVALID ) === 0 ) {
            $message = $this->getStrings()['license-keys-do-not-match'];
        } elseif ( strcasecmp( $response->getLicense(), LicenseStatus::LICENSE_INACTIVE ) === 0 ) {
            $message = $this->getStrings()['license-is-inactive'];
        } elseif ( strcasecmp( $response->getLicense(), LicenseStatus::LICENSE_DISABLED ) === 0 ) {
            $message = $this->getStrings()['license-key-is-disabled'];
        } elseif ( strcasecmp( $response->getLicense(), LicenseStatus::LICENSE_SITE_INACTIVE ) === 0 ) {
            $message = $this->getStrings()['site-is-inactive'];
        } else {
            $message = $this->getStrings()['license-status-unknown'];
        }

        $option = get_option( self::LICENSE_SETTING, [] );
        $status = isset( $option[ $plugin_data->getItemId() ]['status'] ) ? $option[ $plugin_data->getItemId() ]['status'] : '';
        $option[ $plugin_data->getItemId() ]['status'] = $response->getLicense();
        $key = $this->getTransientKey( $plugin_data->getItemId() . '_license_message' );

        if ( $update_option ) {
            if ( ! empty( $status ) && $status != $option[ $plugin_data->getItemId() ]['status'] ) {
                update_option( self::LICENSE_SETTING, $option );
                delete_transient( $key );
            }
        }

        return $message;
    }

    /**
     * Get an array of translation strings.
     *
     * @return array
     */
    protected function getStrings(): array {
        return [
            'plugin-license' => __( 'Plugin License', 'edd-software-license-manager' ),
            'enter-key' => __( 'Enter your plugin license key.', 'edd-software-license-manager' ),
            'license-key' => __( 'License Key', 'edd-software-license-manager' ),
            'license-action' => __( 'License Action', 'edd-software-license-manager' ),
            'deactivate-license' => __( 'Deactivate License', 'edd-software-license-manager' ),
            'activate-license' => __( 'Activate License', 'edd-software-license-manager' ),
            'check-license' => __( 'Check License Status', 'edd-software-license-manager' ),
            'status-unknown' => __( 'License status is unknown.', 'edd-software-license-manager' ),
            'renew' => __( 'Renew?', 'edd-software-license-manager' ),
            'unlimited' => __( 'unlimited', 'edd-software-license-manager' ),
            'license-key-is-active' => __( 'License key is active.', 'edd-software-license-manager' ),
            'expires%s' => __( 'Expires %s.', 'edd-software-license-manager' ),
            '%1$s/%2$-sites' => __( 'You have %1$s / %2$s sites activated.', 'edd-software-license-manager' ),
            'license-key-expired-%s' => __( 'License key expired %s.', 'edd-software-license-manager' ),
            'license-key-expired' => __( 'License key has expired.', 'edd-software-license-manager' ),
            'license-keys-do-not-match' => __( 'License keys do not match.', 'edd-software-license-manager' ),
            'license-is-inactive' => __( 'License is inactive.', 'edd-software-license-manager' ),
            'license-key-is-disabled' => __( 'License key is disabled.', 'edd-software-license-manager' ),
            'site-is-inactive' => __( 'Site is inactive.', 'edd-software-license-manager' ),
            'license-status-unknown' => __( 'License status is unknown.', 'edd-software-license-manager' ),
            'update-notice' => __( "Updating this plugin will lose any customizations you have made. 'Cancel' to stop, 'OK' to update.", 'edd-software-license-manager' ),
            'update-available' => __( '<strong>%1$s %2$s</strong> is available. <a href="%3$s" class="thickbox" title="%4s">Check out what\'s new</a> or <a href="%5$s"%6$s>update now</a>.', 'edd-software-license-manager' ),
        ];
    }

    /**
     * @param string $value
     * @param string $class
     * @param string $name
     * @param string $status
     */
    protected function getSubmitButton( string $value, string $class, string $name, string $status ) {
        printf(
            '<input name="%3$s" id="EddSoftwareLicenseManagerButton" class="button %2$s" value="%1$s" data-status="%4$s" type="button">',
            $value,
            $class,
            $name,
            $status
        );
    }

    /**
     * @param array $api_params
     *
     * @return ActivateLicense
     */
    private function getActivateLicense( array $api_params ): ActivateLicense {
        return new ActivateLicense( $this->getApiResponse( $api_params ) );
    }

    /**
     * @param array $api_params
     *
     * @return DeactivateLicense
     */
    private function getDeactivateLicense( array $api_params ): DeactivateLicense {
        return new DeactivateLicense( $this->getApiResponse( $api_params ) );
    }

    /**
     * @param array $api_params
     *
     * @return CheckLicense
     */
    private function getCheckLicense( array $api_params ): CheckLicense {
        return new CheckLicense( $this->getApiResponse( $api_params ) );
    }

    /**
     * Makes a call to the API.
     *
     * @param array $api_params to be used for wp_remote_get.
     *
     * @return array $response decoded JSON response.
     */
    private function getApiResponse( array $api_params ): array {
        $response = wp_remote_get(
            esc_url_raw( add_query_arg( $api_params, $this->api_url ) ),
            [
                'timeout' => 15,
                'sslverify' => true,
            ]
        );

        // Make sure the response came back okay.
        if ( is_wp_error( $response ) ) {
            return [];
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Constructs a renewal link
     *
     * @param string $license_key
     * @param int $item_id
     *
     * @since 1.0.0
     * @return string
     */
    private function getRenewalUrl( string $license_key, int $item_id ) {
        if ( ! empty( $license_key ) || ! empty( $item_id ) ) {
            $url = add_query_arg(
                [
                    'edd_license' => $license_key,
                    'download_id' => $item_id,
                    'utm_source' => 'wordpress',
                    'utm_medium' => 'frosty-media-renew',
                    'utm_campaign' => 'frosty-media-license',
                ],
                sprintf( '%s/checkout/', untrailingslashit( EmailDownload::API_URL ) )
            );

            return $url;
        }

        return $this->api_url;
    }

    /**
     * Get's the cached transient key.
     *
     * @param string $input
     *
     * @return string
     */
    private function getTransientKey( string $input ): string {
        $prefix = self::TRANSIENT_PREFIX;

        return $prefix . substr( md5( $input ), 0, 45 - strlen( $prefix ) );
    }
}
