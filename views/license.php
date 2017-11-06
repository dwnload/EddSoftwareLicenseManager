<?php

use Dwnload\EddSoftwareLicenseManager\Edd\LicenseManager;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\LicenseStatus;
use Dwnload\WpSettingsApi\Api\Options;

if ( ! ( $this instanceof LicenseManager ) ) {
    wp_die();
}

$field = $this->getSettingField();
$license_key = Options::getOption( $field->getName(), $field->getSectionId() );
$license_data = get_option( LicenseManager::LICENSE_SETTING, [] );

if ( empty( $license_key ) ) {
    echo 'Please save your license key.';
    return;
}

printf( '<h2>License Status: %s</h2>', $license_data['status'] ?? LicenseStatus::LICENSE_INACTIVE );

if ( ! empty( $license_data ) && $license_data['status'] === LicenseStatus::LICENSE_ACTIVE ) {
    submit_button(
        $this->getStrings()['deactivate-license'],
        'button-primary',
        LicenseStatus::LICENSE_DEACTIVATE,
        false
    );
    echo '&nbsp;&nbsp;';
    submit_button(
        $this->getStrings()['check-license'],
        'button-secondary',
        LicenseStatus::LICENSE_CHECK_LICENSE,
        false
    );
} else {
    submit_button(
        $this->getStrings()['activate-license'],
        'button-primary',
        LicenseStatus::LICENSE_ACTIVATE,
        false
    );
}