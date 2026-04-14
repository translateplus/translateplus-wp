<?php
/**
 * Translation module.
 *
 * @package TranslatePlus
 */

namespace TranslatePlus\Modules\Translation;

use TranslatePlus\Core\Container;
use TranslatePlus\Core\Module\ModuleInterface;

final class TranslationModule implements ModuleInterface {

    public function register(Container $container): void {
        // Reserved for future service registration.
    }

    public function boot(Container $container): void {
        \TranslatePlus_Auto_Sync::init();
    }

    public function activate(Container $container): void {
        // No-op.
    }

    public function deactivate(Container $container): void {
        \TranslatePlus_Auto_Sync::on_deactivate();
    }
}
