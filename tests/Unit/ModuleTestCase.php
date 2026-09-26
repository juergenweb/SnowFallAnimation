<?php
declare(strict_types=1);

namespace SnowFallAnimation\Tests;

use DateTime;
use PHPUnit\Framework\TestCase;
use ProcessWire\FakeWire;
use ProcessWire\HookEvent;
use ProcessWire\SnowFallAnimation;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Base class with helpers for all SnowFallAnimation tests
 */
abstract class ModuleTestCase extends TestCase
{
    /** fixed "today" for all tests, so the results do not depend on the real date */
    protected const TODAY = '2026-06-15';

    protected function setUp(): void
    {
        FakeWire::reset();
    }

    /**
     * Create the module like ProcessWire does: construct -> set config data -> init()
     * @param array $config config data (as stored in the database)
     * @param string $today the date that should be used as "today"
     */
    protected function makeModule(array $config = [], string $today = self::TODAY): SnowFallAnimation
    {
        FakeWire::$services['modules']->config = $config;

        $module = new SnowFallAnimation();
        $this->setToday($module, $today);
        foreach ($config as $key => $value) {
            $module->set($key, $value);
        }
        $module->init();
        return $module;
    }

    protected function setToday(SnowFallAnimation $module, string $date): void
    {
        $property = new ReflectionProperty($module, 'today');
        $property->setValue($module, new DateTime($date));
    }

    protected function getProperty(SnowFallAnimation $module, string $name): mixed
    {
        return (new ReflectionProperty($module, $name))->getValue($module);
    }

    /**
     * Call a protected method of the module
     */
    protected function call(SnowFallAnimation $module, string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod($module, $method))->invoke($module, ...$args);
    }

    /**
     * Timestamp of a date (midnight)
     */
    protected function ts(string $date): int
    {
        return (new DateTime($date))->getTimestamp();
    }

    /**
     * Run the Page::render hook and return the resulting HTML
     */
    protected function render(SnowFallAnimation $module, string $html, string $template = 'home'): string
    {
        $page = (object)['template' => (object)['name' => $template]];
        $event = new HookEvent([], $page, $html);
        $this->call($module, 'addScript', $event);
        return $event->return;
    }

    /**
     * Run the Modules::saveConfig hook and return the (modified) data
     */
    protected function validate(SnowFallAnimation $module, array $data, mixed $class = 'SnowFallAnimation'): mixed
    {
        $event = new HookEvent([$class, $data]);
        $this->call($module, 'validateDates', $event);
        return $event->arguments(1);
    }

    protected function modules(): \ProcessWire\FakeModules
    {
        return FakeWire::$services['modules'];
    }

    protected function session(): \ProcessWire\FakeSession
    {
        return FakeWire::$services['session'];
    }
}
