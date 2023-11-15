<?php

namespace Dwnload\EddSoftwareLicenseManager\Edd;

use Dwnload\EddSoftwareLicenseManager\Edd\Models\ActivateLicense;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\CheckLicense;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\DeactivateLicense;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\LicenseStatus;
use Dwnload\EddSoftwareLicenseManager\Edd\Models\PluginData;
use Dwnload\WpSettingsApi\Settings\FieldManager;
use Dwnload\WpSettingsApi\Settings\SectionManager;
use Dwnload\WpSettingsApi\WpSettingsApi;
use TheFrosty\WpUtilities\Api\TransientsTrait;
use TheFrosty\WpUtilities\Plugin\Plugin;
use function __;

/**
 * Class AbstractLicenceManager
 * @package Dwnload\EddSoftwareLicenseManager\Edd
 */
abstract class AbstractLicenceManager
{

    use TransientsTrait;

    public const ACTIVATE_LICENCE = 'activate_license';
    public const CHECK_LICENCE = 'check_license';
    public const DEACTIVATE_LICENCE = 'deactivate_license';
    public const TRANSIENT_PREFIX = 'dwnload_edd_slm_';
    public const LICENSE_SETTING = 'dwnload_license_data';
    protected PluginData $pluginData;

    /**
     * AbstractLicenceManager constructor.
     * @param Plugin $parent
     * @param array $data
     */
    public function __construct(protected Plugin $parent, array $data)
    {
        $this->pluginData = new PluginData($data);
    }

    /**
     * Build the HTML submit button.
     * @param string $plugin_id
     * @param string $class
     * @param string $action
     * @param string $status
     */
    public function buildSubmitButton(string $plugin_id, string $class, string $action, string $status): void
    {
        $text = match ($action) {
            LicenseStatus::LICENSE_DEACTIVATE => $this->getStrings()['deactivate-license'],
            LicenseStatus::LICENSE_CHECK_LICENSE => $this->getStrings()['check-license'],
            LicenseStatus::LICENSE_ACTIVATE => $this->getStrings()['activate-license'],
            default => 'Unknown',
        };

        \printf(
            '<a href="javascript:;" id="EddSoftwareLicenseManagerButton_%3$s" class="button %2$s" data-action="%3$s" data-plugin_id="%5$s" data-status="%4$s">%1$s</a>',
            $text,
            $class,
            $action,
            $status,
            $plugin_id
        );
    }

    /**
     * Get an array of translation strings.
     *
     * @return array
     */
    public function getStrings(): array
    {
        return [
            'plugin-license' => __('Plugin License', 'edd-software-license-manager'),
            'enter-key' => __('Enter your plugin license key.', 'edd-software-license-manager'),
            'license-key' => __('License Key', 'edd-software-license-manager'),
            'license-action' => __('License Action', 'edd-software-license-manager'),
            'deactivate-license' => __('Deactivate License', 'edd-software-license-manager'),
            'activate-license' => __('Activate License', 'edd-software-license-manager'),
            'check-license' => __('Check License Status', 'edd-software-license-manager'),
            'status-unknown' => __('License status is unknown.', 'edd-software-license-manager'),
            'renew' => __('Renew?', 'edd-software-license-manager'),
            'unlimited' => __('unlimited', 'edd-software-license-manager'),
            'license-key-is-active' => __('License key is active.', 'edd-software-license-manager'),
            'expires%s' => __('Expires %s.', 'edd-software-license-manager'),
            '%1$s/%2$-sites' => __('You have %1$s / %2$s sites activated.', 'edd-software-license-manager'),
            'license-key-expired-%s' => __('License key expired %s.', 'edd-software-license-manager'),
            'license-key-expired' => __('License key has expired.', 'edd-software-license-manager'),
            'license-keys-do-not-match' => __('License keys do not match.', 'edd-software-license-manager'),
            'license-is-inactive' => __('License is inactive.', 'edd-software-license-manager'),
            'license-key-is-disabled' => __('License key is disabled.', 'edd-software-license-manager'),
            'site-is-inactive' => __('Site is inactive.', 'edd-software-license-manager'),
            'license-status-unknown' => __('License status is unknown.', 'edd-software-license-manager'),
            'update-notice' => __(
                "Updating this plugin will lose any customizations you have made. 'Cancel' to stop, 'OK' to update.",
                'edd-software-license-manager'
            ),
            'update-available' => __(
                '<strong>%1$s %2$s</strong> is available. <a href="%3$s" class="thickbox" title="%4s">Check out what\'s new</a> or <a href="%5$s"%6$s>update now</a>.',
                'edd-software-license-manager'
            ),
        ];
    }

    /**
     * Initiate the addon setting to the Section & Field Manager classes.
     * @param SectionManager $section_manager
     * @param FieldManager $field_manager
     * @param WpSettingsApi $wp_settings_api
     */
    abstract protected function init(
        SectionManager $section_manager,
        FieldManager $field_manager,
        WpSettingsApi $wp_settings_api
    ): void;

    /**
     * Activates the license key.
     *
     * @param string $license The incoming POST license key
     * @param string $plugin_id
     * @param int $item_id
     * @return false|array
     */
    protected function activateLicense(string $license, string $plugin_id, int $item_id): false|array
    {
        if (empty($license)) {
            return false;
        }

        $api_params = [
            'edd_action' => self::ACTIVATE_LICENCE,
            'license' => $license,
            'item_id' => $item_id,
            'item_name' => rawurlencode($this->pluginData->getItemName()),
            'url' => home_url(),
            'environment' => wp_get_environment_type(),
        ];

        $response = $this->getActivateLicense($api_params);

        if ($response->isValidResponse()) {
            $key = $this->getTransientKey($plugin_id . '_license_message', self::TRANSIENT_PREFIX);
            $option = \get_option(self::LICENSE_SETTING, []);
            $option[$plugin_id]['license'] = trim($license);
            $option[$plugin_id]['expires'] = trim($response->getExpires());
            $option[$plugin_id]['status'] = trim($response->getLicense());

            \update_option(self::LICENSE_SETTING, $option);
            \delete_transient($key);

            return $option;
        }

        return false;
    }

    /**
     * Deactivates the license key.
     *
     * @param string $license The incoming POST license key
     * @param string $plugin_id
     * @param int $item_id
     * @return false|array
     */
    protected function deactivateLicense(string $license, string $plugin_id, int $item_id): false|array
    {
        $api_params = [
            'edd_action' => self::DEACTIVATE_LICENCE,
            'license' => $license,
            'item_id' => $item_id,
            'item_name' => rawurlencode($this->pluginData->getItemName()),
            'url' => home_url(),
            'environment' => wp_get_environment_type(),
        ];

        $response = $this->getDeactivateLicense($api_params);

        if ($response->isValidResponse()) {
            $key = $this->getTransientKey($plugin_id . '_license_message', self::TRANSIENT_PREFIX);
            $option = get_option(self::LICENSE_SETTING, []);
            $option[$plugin_id]['license'] = trim($license);
            $option[$plugin_id]['expires'] = trim($response->getExpires());
            $option[$plugin_id]['status'] = trim($response->getLicense());

            \update_option(self::LICENSE_SETTING, $option);
            \delete_transient($key);

            return $option;
        }

        return false;
    }

    /**
     * Checks if license is valid and gets expire date.
     *
     * @param string $license The incoming POST license key
     * @param string $plugin_id
     * @param bool $update_option
     * @return string $message License status message.
     */
    protected function checkLicense(string $license, string $plugin_id, bool $update_option = false): string
    {
        if (empty($license)) {
            return $this->getStrings()['enter-key'];
        }

        $api_params = [
            'edd_action' => self::CHECK_LICENCE,
            'license' => $license,
            'item_id' => $this->pluginData->getItemId(),
            'item_name' => rawurlencode($this->pluginData->getItemName()),
            'url' => home_url(),
            'environment' => wp_get_environment_type(),
        ];

        $response = $this->getCheckLicense($api_params);

        // If response doesn't include license data, return
        if (!$response->isValidResponse()) {
            return $this->getStrings()['license-unknown'];
        }

        $expires = date_i18n(get_option('date_format'), strtotime($response->getExpires()));
        $renew_link = \sprintf(
            '<a href="%1$s" target="_blank">%2$s</a>',
            esc_url($this->getRenewalUrl($license, $this->pluginData->getItemId())),
            $this->getStrings()['renew']
        );


        // Unlimited ??
        if ($response->getLicenseLimit() === 0) {
            $license_limit = $this->getStrings()['unlimited'];
        }

        if (strcasecmp($response->getLicense(), LicenseStatus::LICENSE_ACTIVE) === 0) {
            $message = $this->getStrings()['license-key-is-active'] . ' ';
            $message .= sprintf($this->getStrings()['expires%s'], $expires) . ' ';
            $message .= sprintf(
                $this->getStrings()['%1$s/%2$-sites'],
                $response->getSiteCount(),
                $response->getLicenseLimit()
            );
        } elseif (strcasecmp($response->getLicense(), LicenseStatus::LICENSE_EXPIRED) === 0) {
            if (strtotime($response->getExpires()) > time()) {
                $message = sprintf($this->getStrings()['license-key-expired-%s'], $expires);
            } else {
                $message = $this->getStrings()['license-key-expired'];
                $message .= ' ' . $renew_link;
            }
        } elseif (strcasecmp($response->getLicense(), LicenseStatus::LICENSE_INVALID) === 0) {
            $message = $this->getStrings()['license-keys-do-not-match'];
        } elseif (strcasecmp($response->getLicense(), LicenseStatus::LICENSE_INACTIVE) === 0) {
            $message = $this->getStrings()['license-is-inactive'];
        } elseif (strcasecmp($response->getLicense(), LicenseStatus::LICENSE_DISABLED) === 0) {
            $message = $this->getStrings()['license-key-is-disabled'];
        } elseif (strcasecmp($response->getLicense(), LicenseStatus::LICENSE_SITE_INACTIVE) === 0) {
            $message = $this->getStrings()['site-is-inactive'];
        } else {
            $message = $this->getStrings()['license-status-unknown'];
        }

        $option = get_option(self::LICENSE_SETTING, []);
        $status = $option[$plugin_id]['status'] ?? '';
        $option[$plugin_id]['status'] = $response->getLicense();
        $key = $this->getTransientKey($this->pluginData->getItemId() . '_license_message', self::TRANSIENT_PREFIX);

        if ($update_option) {
            if (!empty($status) && $status !== $option[$plugin_id]['status']) {
                \update_option(self::LICENSE_SETTING, $option);
                \delete_transient($key);
            }
        }

        return $message;
    }

    /**
     * Get ActivateLicense object,
     * @param array $api_params
     * @return ActivateLicense
     */
    private function getActivateLicense(array $api_params): ActivateLicense
    {
        return new ActivateLicense($this->getApiResponse($api_params));
    }

    /**
     * Get DeactivateLicense object,
     * @param array $api_params
     * @return DeactivateLicense
     */
    private function getDeactivateLicense(array $api_params): DeactivateLicense
    {
        return new DeactivateLicense($this->getApiResponse($api_params));
    }

    /**
     * Get CheckLicense object,
     * @param array $api_params
     * @return CheckLicense
     */
    private function getCheckLicense(array $api_params): CheckLicense
    {
        return new CheckLicense($this->getApiResponse($api_params));
    }

    /**
     * Makes a call to the API.
     * @param array $api_params to be used for wp_remote_get.
     * @return array $response decoded JSON response.
     */
    private function getApiResponse(array $api_params): array
    {
        $response = \wp_remote_post(
            \esc_url($this->pluginData->getApiUrl()),
            [
                'timeout' => 15,
                'sslverify' => true,
                'body' => $api_params,
            ]
        );

        // Make sure the response came back okay.
        if (\is_wp_error($response)) {
            return [];
        }

        return \json_decode(\wp_remote_retrieve_body($response), true);
    }

    /**
     * Constructs a renewal link.
     * @param string $license_key
     * @param int|null $item_id
     * @return string
     */
    private function getRenewalUrl(string $license_key = '', ?int $item_id = null): string
    {
        if (!empty($license_key) || !empty($item_id)) {
            return \add_query_arg(
                [
                    'edd_license' => $license_key,
                    'download_id' => $item_id,
                    'utm_source' => 'wordpress',
                    'utm_medium' => 'edd-software-licence',
                    'utm_campaign' => 'licence',
                ],
                \sprintf('%s/checkout/', \untrailingslashit($this->pluginData->getApiUrl()))
            );
        }

        return $this->pluginData->getApiUrl();
    }
}
