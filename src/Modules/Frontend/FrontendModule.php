<?php
/**
 * Frontend module.
 *
 * @package TranslatePlus
 */

namespace TranslatePlus\Modules\Frontend;

use TranslatePlus\Core\Container;
use TranslatePlus\Core\Module\ModuleInterface;

final class FrontendModule implements ModuleInterface {

    public function register(Container $container): void {
        // Reserved for future service registration.
    }

    public function boot(Container $container): void {
        \TranslatePlus_Rewrites::init();
        \TranslatePlus_Frontend_Lang_Dropdown::init();
        \TranslatePlus_SEO::init();
        \TranslatePlus_String_Translation::init();
        \TranslatePlus_Translation_Group::register_frontend_hooks();
        \TranslatePlus_Browser_Lang_Redirect::init();
        \TranslatePlus_Query_Main_En::init();
    }

    public function activate(Container $container): void {
        \TranslatePlus_Rewrites::activate();
    }

    public function deactivate(Container $container): void {
        \TranslatePlus_Rewrites::deactivate();
    }
}
