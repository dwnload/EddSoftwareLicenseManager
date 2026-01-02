<?php

declare(strict_types=1);

use Dwnload\EddSoftwareLicenseManager\Edd\License;
use Dwnload\EddSoftwareLicenseManager\Edd\LicenseManager;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\LicenseStatus;

if (!($this instanceof LicenseManager)) {
    wp_die();
}

if (!isset($plugin_id)) {
    return;
}

$license_expires = License::getLicenseExpires($plugin_id);
$license_status = License::getLicenseStatus($plugin_id);
$active_or_valid = License::isActiveValid($plugin_id);
$is_expired = License::isExpired($plugin_id);
ob_start();

echo "<div class='EddSoftwareLicenseManager'>";
if ($is_expired) {
    echo '<span class="dashicons dashicons-warning"></span>&nbsp;';
}
printf('License Status: <span class="license-status %1$s">%1$s</span>', $license_status);
if (!empty($license_expires)) {
    printf(
        ' &mdash; License Expires: <span class="license-expires">%1$s</span>',
        $license_expires === 'lifetime' ?
            'Never (lifetime)' :
            date_i18n(get_option('date_format'), strtotime($license_expires))
    );
}

echo '<br><hr>';

$this->buildSubmitButton(
    $plugin_id,
    'button-primary',
    LicenseStatus::LICENSE_ACTIVATE,
    $license_status
);

echo '&nbsp;&nbsp;';

$this->buildSubmitButton(
    $plugin_id,
    $active_or_valid ? 'button-primary' : 'button-secondary disabled',
    $license_status === LicenseStatus::LICENSE_DEACTIVATED ? LicenseStatus::LICENSE_ACTIVATE : LicenseStatus::LICENSE_DEACTIVATE,
    $license_status,
);

if (!$active_or_valid || $is_expired) {
    echo '&nbsp;&nbsp;';
    $this->buildSubmitButton(
        $plugin_id,
        'button-secondary',
        LicenseStatus::LICENSE_CHECK_LICENSE,
        $license_status
    );
}
echo '</div>';

return ob_get_clean();
