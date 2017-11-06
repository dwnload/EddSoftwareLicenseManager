<?php

namespace Dwnload\EddSoftwareLicenseManager\Edd;

use Dwnload\EddSoftwareLicenseManager\Edd\Models\LicenseStatus;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\PluginData;
use Dwnload\WpSettingsApi\Api\SettingField;
use Dwnload\WpSettingsApi\Api\SettingSection;
use Dwnload\WpSettingsApi\App;
use TheFrosty\WP\Utils\WpHooksInterface;

/**
 * Class LicenseManager
 *
 * @package Dwnload\EddSoftwareLicenseManager\Edd
 */
class LicenseManager extends AbstractLicenceManager implements WpHooksInterface {

    const AJAX_ACTION = __CLASS__;
    const HANDLE = 'license-manager';

    /** @var App $app */
    private $app;

    /** @var  SettingField $field */
    private $field;

    /** @var  PluginData $plugin_data */
    private $plugin_data;

    /**
     * LicenseManager constructor.
     *
     * @param App $app
     * @param array $data
     */
    public function __construct( App $app, array $data ) {
        $this->app = $app;
        $this->plugin_data = new PluginData( $data );
    }

    /**
     * @param SettingField $field
     */
    public function setSettingField( SettingField $field ) {
        $this->field = $field;
    }

    /**
     * @return SettingField
     */
    public function getSettingField(): SettingField {
        return $this->field;
    }

    public function addHooks() {
        add_action( App::ACTION_PREFIX . 'settings_page_loaded', function() {
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        } );
        add_action( App::ACTION_PREFIX . 'form_top', [ $this, 'licenseData' ] );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'licenseAjax' ] );
    }

    /**
     * Enqueue License only script
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            self::HANDLE,
            plugins_url( '../assets/css/licensemanager.css', dirname( __FILE__ ) ),
            [],
            $this->app->getVersion()
        );
        wp_enqueue_script(
            self::HANDLE,
            plugins_url( '../assets/js/licensemanager.js', dirname( __FILE__ ) ),
            [ 'jquery' ],
            $this->app->getVersion(),
            true
        );
        wp_localize_script( self::HANDLE, 'EddLicenseManager', [
            'action' => self::AJAX_ACTION,
            'dirname' => __DIR__,
            'nonce' => wp_create_nonce( plugin_basename( __FILE__ ) . self::AJAX_ACTION . '-nonce' ),
            'loading' => admin_url( '/images/wpspin_light.gif' ),
        ] );
    }

    /**
     * Output additional HTML to the top of the section form.
     *
     * @param SettingSection $section
     */
    public function licenseData( SettingSection $section ) {
        if ( $section->getId() === $this->field->getSectionId() ) {
            include dirname( dirname( __DIR__ ) ) . '/views/license.php';
        }
    }

    public function licenseAjax() {
        check_ajax_referer( plugin_basename( __FILE__ ) . self::AJAX_ACTION . '-nonce', 'nonce' );

        if ( empty( $_POST ) || empty( $_POST[ $this->field->getSectionId() ][ $this->field->getName() ] ) ) {
            wp_send_json_error();
        }

        $license_key = esc_html( wp_unslash( $_POST[ $this->field->getSectionId() ][ $this->field->getName() ] ) );
        $plugin_action = sanitize_text_field( wp_unslash( $_POST['plugin_action'] ) );

        if ( $plugin_action === LicenseStatus::LICENSE_ACTIVATE ) {
            if ( $this->activateLicense( $license_key, $this->plugin_data->getSlug(), $this->plugin_data->getItemId() ) ) {
                wp_send_json_success();
            }
            wp_send_json_error();
        }

        if ( $plugin_action === LicenseStatus::LICENSE_DEACTIVATE ) {
            if ( $this->deactivateLicense( $license_key, $this->plugin_data->getSlug(), $this->plugin_data->getItemId() ) ) {
                wp_send_json_success();
            }
            wp_send_json_error();
        }

        if ( $plugin_action === LicenseStatus::LICENSE_CHECK_LICENSE ) {
            $message = $this->checkLicense( $license_key, $this->plugin_data, $update_option = true );
            wp_send_json_success( $message );
        }
    }
}
