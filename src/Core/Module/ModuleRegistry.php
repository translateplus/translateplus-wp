<?php
/**
 * Module registry.
 *
 * @package TranslatePlus
 */

namespace TranslatePlus\Core\Module;

final class ModuleRegistry {

    /**
     * @var list<ModuleInterface>
     */
    private array $modules = array();

    public function add(ModuleInterface $module): void {
        $this->modules[] = $module;
    }

    /**
     * @return list<ModuleInterface>
     */
    public function all(): array {
        return $this->modules;
    }
}
