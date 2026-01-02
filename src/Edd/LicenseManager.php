<?php

declare(strict_types=1);

namespace Dwnload\EddSoftwareLicenseManager\Edd;

use Dwnload\EddSoftwareLicenseManager\Edd\Models\LicenseStatus;
use Dwnload\WpSettingsApi\Api\SettingField;
use Dwnload\WpSettingsApi\Api\SettingSection;
use Dwnload\WpSettingsApi\Settings\FieldManager;
use Dwnload\WpSettingsApi\Settings\FieldTypes;
use Dwnload\WpSettingsApi\Settings\SectionManager;
use Dwnload\WpSettingsApi\WpSettingsApi;
use TheFrosty\WpUtilities\Plugin\HooksTrait;
use TheFrosty\WpUtilities\Plugin\WpHooksInterface;
use function __;
use function admin_url;
use function apply_filters;
use function array_key_exists;
use function check_ajax_referer;
use function current_time;
use function defined;
use function dirname;
use function esc_html__;
use function plugin_basename;
use function plugins_url;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function str_replace;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_next_scheduled;
use function wp_schedule_event;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use const SCRIPT_DEBUG;
use const WEEK_IN_SECONDS;

/**
 * Class LicenseManager
 * @package Dwnload\EddSoftwareLicenseManager\Edd
 */
class LicenseManager extends AbstractLicenceManager implements WpHooksInterface
{

    use HooksTrait;

    public const string AJAX_ACTION = __CLASS__;
    public const string HANDLE = 'license-manager';
    public const string HOOK_WEEKLY = 'edd_license_manager_weekly_scheduled_events';
    public const string VERSION = '2.3.0';

    public function addHooks(): void
    {
        $this->addAction(WpSettingsApi::HOOK_INIT, [$this, 'init'], 299, 3);
        $this->addAction('wp_ajax_' . sanitize_key(self::AJAX_ACTION), [$this, 'licenseAjax']);
        $this->addAction('wp', [$this, 'scheduleEvents']);
        $this->addFilter('cron_schedules', [$this, 'maybeAddSchedule']);
        $this->addAction(self::HOOK_WEEKLY, [$this, 'cron']);
    }

    /**
     * Initiate our setting to the Section & Field Manager classes.
     * @param SectionManager $section_manager
     * @param FieldManager $field_manager
     * @param WpSettingsApi $wp_settings_api
     */
    protected function init(
        SectionManager $section_manager,
        FieldManager $field_manager,
        WpSettingsApi $wp_settings_api
    ): void {
        if (!$wp_settings_api->isCurrentMenuSlug($this->parent->getSlug())) {
            return;
        }
        $this->addAction('admin_enqueue_scripts', [$this, 'enqueueScripts']);

        $section_id = $section_manager->addSection(
            new SettingSection([
                SettingSection::SECTION_ID => 'edd_license_manager',
                SettingSection::SECTION_TITLE => 'License',
            ])
        );

        $plugin_id = $this->parent->getSlug();
        $field_manager->addField(
            new SettingField(
                [
                    SettingField::NAME => $this->parent->getSlug(),
                    SettingField::LABEL => sprintf(
                        __('%s License', 'edd-software-license-manager'),
                        $this->pluginData->getItemName()
                    ),
                    SettingField::TYPE => FieldTypes::FIELD_TYPE_TEXT,
                    SettingField::DESC => include dirname(__DIR__, 2) . '/views/license.php',
                    SettingField::ATTRIBUTES => [
                        'pattern' => '^[a-fA-F0-9]{32}$',
                    ],
                    SettingField::SECTION_ID => $section_id,
                ]
            )
        );
    }

    /**
     * Enqueue License only script
     */
    protected function enqueueScripts(): void
    {
        $use_local = apply_filters('dwnload_edd_slm_use_local_scripts', false);
        $get_src = static function (string $path) use ($use_local): string {
            if ($use_local) {
                return plugins_url($path, dirname(__DIR__));
            }

            $debug = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;

            return sprintf(
                'https://cdn.jsdelivr.net/gh/dwnload/EddSoftwareLicenseManager@%s/%s',
                apply_filters('dwnload_edd_slm_scripts_version', self::VERSION),
                $debug === true ? $path : str_replace(['.css', '.js'], ['.min.css', '.min.js'], $path)
            );
        };

        wp_enqueue_style(
            self::HANDLE,
            $get_src('assets/css/licensemanager.css'),
            [],
            self::VERSION
        );
        wp_enqueue_script(
            self::HANDLE,
            $get_src('assets/js/licensemanager.js'),
            ['jquery'],
            self::VERSION,
            true
        );
        wp_localize_script(self::HANDLE, 'EddLicenseManager', [
            'action' => sanitize_key(self::AJAX_ACTION),
            'nonce' => wp_create_nonce(plugin_basename(__FILE__) . self::AJAX_ACTION . '-nonce'),
            'loading' => admin_url('/images/spinner-2x.gif'),
        ]);
    }

    /**
     * Licence check AJAX listener.
     */
    protected function licenseAjax(): void
    {
        check_ajax_referer(plugin_basename(__FILE__) . self::AJAX_ACTION . '-nonce', 'nonce');

        if (empty($_POST) || empty($_POST['license_key'])) {
            wp_send_json_error();
        }

        $license_key = sanitize_text_field(wp_unslash($_POST['license_key']));
        $plugin_action = sanitize_text_field(wp_unslash($_POST['plugin_action']));
        $plugin_id = sanitize_text_field(wp_unslash($_POST['plugin_id']));

        switch ($plugin_action) {
            case LicenseStatus::LICENSE_ACTIVATE:
                $response = $this->activateLicense(
                    $license_key,
                    $plugin_id,
                    $this->pluginData->getItemId()
                );
                if ($response !== false) {
                    wp_send_json_success($response);
                }
                break;
            case LicenseStatus::LICENSE_DEACTIVATE:
                $response = $this->deactivateLicense(
                    $license_key,
                    $plugin_id,
                    $this->pluginData->getItemId()
                );
                if ($response !== false) {
                    wp_send_json_success($response);
                }
                break;
            case LicenseStatus::LICENSE_CHECK_LICENSE:
                $response = $this->checkLicense($license_key, $plugin_id, true);
                wp_send_json_success($response);
                break;
        }

        wp_send_json_error();
    }

    /**
     * Schedules cron events.
     */
    protected function scheduleEvents(): void
    {
        if (!wp_next_scheduled(self::HOOK_WEEKLY)) {
            wp_schedule_event(current_time('timestamp'), 'weekly', self::HOOK_WEEKLY, [$this->parent->getSlug()]);
        }
    }

    /**
     * Registers a new cron schedule "weekly" if it doesn't exist.
     * @param array $schedules
     * @return array
     */
    protected function maybeAddSchedule(array $schedules = []): array
    {
        if (!array_key_exists('weekly', $schedules)) {
            $schedules['weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display' => esc_html__('Once Weekly', 'edd-software-license-manager'),
            ];
        }

        return $schedules;
    }

    /**
     * Cron callback.
     */
    protected function cron(string $plugin_id): void
    {
        $data = License::getLicenseData();
        if (empty($data[$plugin_id]) || empty($data[$plugin_id]['license'])) {
            return;
        }
        $license_key = $data[$plugin_id]['license'];
        $this->checkLicense($license_key, $plugin_id, true);
    }
}
