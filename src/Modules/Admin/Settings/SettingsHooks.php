<?php
/**
 * Settings module hook registration.
 *
 * @package TranslatePlus
 */

namespace TranslatePlus\Modules\Admin\Settings;

final class SettingsHooks {

    public function boot(): void {
        add_action('admin_init', array(\TranslatePlus_Settings::class, 'register'));
        add_action('admin_init', array(\TranslatePlus_Settings::class, 'maybe_add_disconnect_notice'));
        add_action('admin_menu', array(\TranslatePlus_Settings::class, 'add_menu'));
        add_action('admin_enqueue_scripts', array(\TranslatePlus_Settings::class, 'enqueue_admin_assets'));
        add_action('admin_post_translateplus_disconnect', array(\TranslatePlus_Settings::class, 'handle_disconnect'));
        add_action('wp_ajax_translateplus_settings_account_summary', array(\TranslatePlus_Settings::class, 'ajax_settings_account_summary'));
        add_action('wp_ajax_translateplus_save_settings', array(\TranslatePlus_Settings::class, 'ajax_save_settings'));
        add_action('admin_notices', array(\TranslatePlus_Settings::class, 'render_dashboard_credits_notice'), 1);

        $clear_summary = array(\TranslatePlus_API::class, 'clear_account_summary_cache');
        add_action('update_option_' . \TranslatePlus_API::OPTION_API_KEY, $clear_summary);
        add_action('add_option_' . \TranslatePlus_API::OPTION_API_KEY, $clear_summary);
    }
}
