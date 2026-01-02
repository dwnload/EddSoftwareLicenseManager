<?php

declare(strict_types=1);

namespace Dwnload\EddSoftwareLicenseManager\Edd;

use Dwnload\EddSoftwareLicenseManager\Edd\Models\LicenseStatus;
use Dwnload\WpSettingsApi\Api\Options;
use function in_array;
use function sanitize_text_field;
use function strtotime;
use function time;
use function update_option;
use const MINUTE_IN_SECONDS;

/**
 * Class License
 * @package Dwnload\EddSoftwareLicenseManager\Edd
 */
class License
{

    public static array $data = [];

    /**
     * Get the license settings data array from the options database.
     * @return array
     */
    public static function getLicenseData(): array
    {
        if (!empty(self::$data)) {
            return self::$data;
        }

        self::$data = (array)get_option(AbstractLicenceManager::LICENSE_SETTING, []);
        return self::$data;
    }

    /**
     * Get the current plugin's expiration date.
     * @param string $plugin_id The plugin slug.
     * @return string
     */
    public static function getLicenseExpires(string $plugin_id): string
    {
        $data = self::getLicenseData();
        return sanitize_text_field($data[$plugin_id]['expires'] ?? '');
    }

    /**
     * Get the current plugin's license status.
     * @param string $plugin_id The plugin slug.
     * @return string
     */
    public static function getLicenseStatus(string $plugin_id): string
    {
        $data = self::getLicenseData();
        return sanitize_text_field($data[$plugin_id]['status'] ?? LicenseStatus::LICENSE_INACTIVE);
    }

    public static function getLicenseKey(string $plugin_id, string $section_id): string
    {
        return sanitize_text_field(Options::getOption($plugin_id, $section_id));
    }

    /**
     * Is the current license active or valid?
     * @param string $plugin_id The plugin slug.
     * @return bool
     */
    public static function isActiveValid(string $plugin_id): bool
    {
        $status = self::getLicenseStatus($plugin_id);
        return in_array($status, [LicenseStatus::LICENSE_ACTIVE, LicenseStatus::LICENSE_VALID], true);
    }

    /**
     * Is the current license expired?
     * @param string $plugin_id The plugin slug.
     * @return bool
     */
    public static function isExpired(string $plugin_id): bool
    {
        $expires = self::getLicenseExpires($plugin_id);
        return !empty($expires) && (time() - strtotime($expires)) > MINUTE_IN_SECONDS;
    }

    /**
     * Update our settings data.
     * @param array $data
     * @return bool
     */
    public static function updateData(array $data): bool
    {
        self::$data = [];
        return update_option(AbstractLicenceManager::LICENSE_SETTING, $data);
    }
}
