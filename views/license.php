<?php

declare(strict_types=1);

use Dwnload\EddSoftwareLicenseManager\Edd\AbstractLicenceManager;
use Dwnload\EddSoftwareLicenseManager\Edd\LicenseManager;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\LicenseStatus;
use Dwnload\WpSettingsApi\Api\Options;

if (!($this instanceof LicenseManager)) {
    wp_die();
}

if (!isset($plugin_id) || !isset($section_id)) {
    return;
}
$license_key = Options::getOption($plugin_id, $section_id);
$license_data = get_option(AbstractLicenceManager::LICENSE_SETTING, []);
$license_expires = $license_data[$plugin_id]['expires'] ?? '';
$license_status = $license_data[$plugin_id]['status'] ?? LicenseStatus::LICENSE_INACTIVE;

ob_start();

echo "<div class='EddSoftwareLicenseManager'>";
printf('License Status: <span class="license-status %1$s">%1$s</span>', $license_status);
if (!empty($license_expires)) {
    printf(
        ' &mdash; License Expires: <span class="license-expires">%1$s</span>',
        date_i18n(get_option('date_format'), strtotime($license_expires))
    );
}

echo '<br><hr>';

if (!empty($license_data) && $license_status === LicenseStatus::LICENSE_ACTIVE) {
    $this->buildSubmitButton(
        $plugin_id,
        'button-primary',
        LicenseStatus::LICENSE_DEACTIVATE,
        $license_status,
    );
    echo '&nbsp;&nbsp;';
    $this->buildSubmitButton(
        $plugin_id,
        'button-secondary',
        LicenseStatus::LICENSE_CHECK_LICENSE,
        $license_status
    );
} else {
    $this->buildSubmitButton(
        $plugin_id,
        empty($license_key) ? 'button-primary disabled' : 'button-primary',
        LicenseStatus::LICENSE_ACTIVATE,
        $license_status
    );
}
echo '</div>';

return ob_get_clean();
