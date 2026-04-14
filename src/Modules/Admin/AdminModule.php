<?php
/**
 * Admin module.
 *
 * @package TranslatePlus
 */

namespace TranslatePlus\Modules\Admin;

use TranslatePlus\Core\Container;
use TranslatePlus\Core\Module\ModuleInterface;
use TranslatePlus\Modules\Admin\Settings\SettingsHooks;

final class AdminModule implements ModuleInterface {

    private ?SettingsHooks $settings_hooks = null;

    public function register(Container $container): void {
        $this->settings_hooks = new SettingsHooks();
        $container->set('admin.settings.hooks', $this->settings_hooks);
    }

    public function boot(Container $container): void {
        if (! is_admin()) {
            return;
        }

        \TranslatePlus_Translation_Group::init();
        \TranslatePlus_Editor_Submitbox::init();
        \TranslatePlus_Translate_Now_Ajax::init();
        if ($this->settings_hooks !== null) {
            $this->settings_hooks->boot();
        }
        \TranslatePlus_Nav_Menu_Meta_Box::init();
        \TranslatePlus_Admin_List::init();
    }

    public function activate(Container $container): void {
        // No-op.
    }

    public function deactivate(Container $container): void {
        // No-op.
    }
}
