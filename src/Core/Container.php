<?php
/**
 * Lightweight service container for TranslatePlus.
 *
 * @package TranslatePlus
 */

namespace TranslatePlus\Core;

final class Container {

    /**
     * @var array<string, mixed>
     */
    private array $entries = array();

    /**
     * @var array<string, callable(self): mixed>
     */
    private array $factories = array();

    /**
     * @param mixed $value
     */
    public function set(string $id, $value): void {
        $this->entries[ $id ] = $value;
    }

    /**
     * @param callable(self): mixed $factory
     */
    public function factory(string $id, callable $factory): void {
        $this->factories[ $id ] = $factory;
    }

    /**
     * @return mixed|null
     */
    public function get(string $id) {
        if (array_key_exists($id, $this->entries)) {
            return $this->entries[ $id ];
        }

        if (isset($this->factories[ $id ])) {
            $this->entries[ $id ] = ($this->factories[ $id ])($this);

            return $this->entries[ $id ];
        }

        return null;
    }
}
