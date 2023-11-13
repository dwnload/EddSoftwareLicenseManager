<?php

declare(strict_types=1);

use Dwnload\EddSoftwareLicenseManager\Edd\AbstractLicenceManager;
use Dwnload\EddSoftwareLicenseManager\Edd\LicenseManager;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\LicenseStatus;
use Dwnload\WpSettingsApi\Api\Options;

if (!($this instanceof LicenseManager)) {
    wp_die();
}

$field = $this->getSettingField();
$license_key = Options::getOption($field->getName(), $field->getSectionId());
$license_data = get_option(AbstractLicenceManager::LICENSE_SETTING, []);
$license_status = $license_data['status'] ?? LicenseStatus::LICENSE_INACTIVE;

if (empty($license_key)) {
    echo 'Please save your license key.';

    return;
}

printf('<h2>License Status: <span class="license-status %1$s">%1$s</span></h2>', $license_status);

if (!empty($license_data) && $license_data['status'] === LicenseStatus::LICENSE_ACTIVE) {
    $this->buildSubmitButton(
        $this->getStrings()['deactivate-license'],
        'button-primary',
        LicenseStatus::LICENSE_DEACTIVATE,
        $license_status
    );
    echo '&nbsp;&nbsp;';
    $this->buildSubmitButton(
        $this->getStrings()['check-license'],
        'button-secondary',
        LicenseStatus::LICENSE_CHECK_LICENSE,
        $license_status
    );
} else {
    $this->buildSubmitButton(
        $this->getStrings()['activate-license'],
        'button-primary',
        LicenseStatus::LICENSE_ACTIVATE,
        $license_status
    );
}
