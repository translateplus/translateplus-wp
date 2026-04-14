<?php
/**
 * Module loader orchestrates registration and lifecycle hooks.
 *
 * @package TranslatePlus
 */

namespace TranslatePlus\Core\Module;

use TranslatePlus\Core\Container;

final class ModuleLoader {

    private ModuleRegistry $registry;

    private Container $container;

    public function __construct(ModuleRegistry $registry, Container $container) {
        $this->registry  = $registry;
        $this->container = $container;
    }

    public function register_all(): void {
        foreach ($this->registry->all() as $module) {
            $module->register($this->container);
        }
    }

    public function boot_all(): void {
        foreach ($this->registry->all() as $module) {
            $module->boot($this->container);
        }
    }

    public function activate_all(): void {
        foreach ($this->registry->all() as $module) {
            $module->activate($this->container);
        }
    }

    public function deactivate_all(): void {
        foreach ($this->registry->all() as $module) {
            $module->deactivate($this->container);
        }
    }
}
