<?php
/**
 * Module contract for plugin lifecycle.
 *
 * @package TranslatePlus
 */

namespace TranslatePlus\Core\Module;

use TranslatePlus\Core\Container;

interface ModuleInterface {

    public function register(Container $container): void;

    public function boot(Container $container): void;

    public function activate(Container $container): void;

    public function deactivate(Container $container): void;
}
