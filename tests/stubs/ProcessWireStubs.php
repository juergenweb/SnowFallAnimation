<?php
declare(strict_types=1);

/*
 * Minimal ProcessWire stubs for the unit tests of the SnowFallAnimation module.
 * They only implement what the module really uses, so the tests run without a ProcessWire installation and database.
 */

namespace ProcessWire;

interface Module {}
interface ConfigurableModule {}

/**
 * Simple service container that replaces $this->wire('...') in the tests
 */
class FakeWire
{
    public static array $services = [];

    public static function reset(): void
    {
        self::$services = [
            'config' => new FakeConfig(),
            'modules' => new FakeModules(),
            'session' => new FakeSession(),
            'input' => new FakeInput(),
        ];
    }

    public static function get(string $name): mixed
    {
        return self::$services[$name] ?? null;
    }
}

class FakeConfig
{
    public bool $ajax = false;

    public function urls(mixed $module): string
    {
        return '/site/modules/SnowFallAnimation/';
    }
}

class FakeModules
{
    /** config data as stored in the database */
    public array $config = [];
    /** every call of saveConfig() */
    public array $saved = [];

    public function getConfig(mixed $module): array
    {
        return $this->config;
    }

    public function saveConfig(mixed $module, mixed $data, mixed $value = null): bool
    {
        $this->saved[] = $data;
        $this->config = $data;
        return true;
    }

    public function get(string $name): Inputfield
    {
        $class = __NAMESPACE__ . '\\' . $name;
        return class_exists($class) ? new $class() : new Inputfield();
    }
}

class FakeSession
{
    public array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }
}

class FakeInput
{
    public array $get = [];

    public function get(string $key): mixed
    {
        return $this->get[$key] ?? null;
    }
}

class WireData
{
    protected array $data = [];
    /** errors reported via $this->error() */
    public array $errors = [];
    /** hooks added via addHookAfter()/addHookBefore() */
    public array $hooks = [];

    public function __construct()
    {
    }

    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function _(string $text): string
    {
        return $text;
    }

    public function wire(string $name): mixed
    {
        return FakeWire::get($name);
    }

    public function error(string $text): static
    {
        $this->errors[] = $text;
        return $this;
    }

    public function addHookAfter(string $method, mixed $object, string $callback, array $options = []): void
    {
        $this->hooks[] = ['after', $method, $callback];
    }

    public function addHookBefore(string $method, mixed $object, string $callback, array $options = []): void
    {
        $this->hooks[] = ['before', $method, $callback];
    }

    public function className(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    public function __toString(): string
    {
        return $this->className();
    }
}

class HookEvent
{
    public mixed $object = null;
    public mixed $return = null;
    public array $args = [];

    public function __construct(array $args = [], mixed $object = null, mixed $return = null)
    {
        $this->args = $args;
        $this->object = $object;
        $this->return = $return;
    }

    public function arguments(int $index, mixed $value = null): mixed
    {
        if (func_num_args() > 1) $this->args[$index] = $value;
        return $this->args[$index] ?? null;
    }
}

/**
 * Generic inputfield: stores attributes, properties and children
 */
class Inputfield
{
    const collapsedNo = 0;
    const collapsedYes = 1;

    public array $attributes = [];
    public array $properties = [];
    public array $children = [];
    public array $classes = [];

    public function attr(string $key, mixed $value = null): mixed
    {
        if (func_num_args() > 1) {
            $this->attributes[$key] = $value;
            return $this;
        }
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->properties[$key] = $value;
    }

    public function __get(string $key): mixed
    {
        return $this->properties[$key] ?? null;
    }

    public function __call(string $method, array $args): mixed
    {
        // e.g. $markup->markupText('...')
        $this->properties[$method] = $args[0] ?? null;
        return $this;
    }

    public function addClass(string $class): static
    {
        $this->classes[] = $class;
        return $this;
    }

    public function add(Inputfield $field): static
    {
        $this->children[] = $field;
        return $this;
    }

    public function addOptions(array $options): static
    {
        $this->properties['options'] = $options;
        return $this;
    }

    public function className(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Find a field by name (recursive)
     */
    public function getByName(string $name): ?Inputfield
    {
        foreach ($this->children as $child) {
            if ($child->attr('name') === $name) return $child;
            $found = $child->getByName($name);
            if ($found) return $found;
        }
        return null;
    }

    /**
     * Count all fields with a given name (recursive)
     */
    public function countByName(string $name): int
    {
        $count = 0;
        foreach ($this->children as $child) {
            if ($child->attr('name') === $name) $count++;
            $count += $child->countByName($name);
        }
        return $count;
    }
}

class InputfieldWrapper extends Inputfield {}
class InputfieldFieldset extends InputfieldWrapper {}
class InputfieldMarkup extends Inputfield {}
class InputfieldSelect extends Inputfield {}
class InputfieldText extends Inputfield {}
class InputfieldInteger extends Inputfield {}
class InputfieldFloat extends Inputfield {}
class InputfieldCheckbox extends Inputfield {}

class InputfieldDatetime extends Inputfield
{
    const datepickerFocus = 3;
}

class WireException extends \Exception {}
class WirePermissionException extends \Exception {}
