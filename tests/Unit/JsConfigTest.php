<?php
declare(strict_types=1);

namespace SnowFallAnimation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use ProcessWire\SnowFallAnimation;

/**
 * Tests for the configuration that is passed to the JavaScript
 */
class JsConfigTest extends ModuleTestCase
{
    private function jsConfig(array $config): array
    {
        return $this->call($this->makeModule($config), 'getJsConfig');
    }

    public function testDefaultsMatchTheDocumentedValues(): void
    {
        $this->assertSame([
            'density' => 50,
            'minSize' => 0.8,
            'maxSize' => 1.5,
            'minDuration' => 5,
            'maxDuration' => 15,
            'snowflakes' => ['❄', '❅', '❆'],
            'color' => '#99ccff',
            'zIndex' => 1000,
        ], $this->jsConfig([]));
    }

    public function testMinAndMaxAreSwappedIfInWrongOrder(): void
    {
        $config = $this->jsConfig([
            'input_minRadius' => 3.0,
            'input_maxRadius' => 1.0,
            'input_minSpeed' => 20,
            'input_maxSpeed' => 4,
        ]);
        $this->assertSame([1.0, 3.0], [$config['minSize'], $config['maxSize']]);
        $this->assertSame([4, 20], [$config['minDuration'], $config['maxDuration']]);
    }

    public function testUpperLimitsAreApplied(): void
    {
        $config = $this->jsConfig([
            'input_count' => 9999999,
            'input_minRadius' => 500.0,
            'input_maxRadius' => 500.0,
            'input_minSpeed' => 99999,
            'input_maxSpeed' => 99999,
        ]);
        $this->assertSame(SnowFallAnimation::MAX_DENSITY, $config['density']);
        $this->assertEquals(SnowFallAnimation::MAX_SIZE, $config['maxSize']);
        $this->assertEquals(SnowFallAnimation::MAX_SIZE, $config['minSize']);
        $this->assertSame(SnowFallAnimation::MAX_DURATION, $config['maxDuration']);
    }

    public function testLowerLimitsAreApplied(): void
    {
        $config = $this->jsConfig([
            'input_count' => -10,
            'input_minRadius' => 0.0,
            'input_maxRadius' => -1.0,
            'input_minSpeed' => 0,
            'input_maxSpeed' => -5,
        ]);
        $this->assertSame(0, $config['density']);
        $this->assertEquals(SnowFallAnimation::MIN_SIZE, $config['minSize']);
        $this->assertEquals(SnowFallAnimation::MIN_SIZE, $config['maxSize']);
        $this->assertSame(SnowFallAnimation::MIN_DURATION, $config['minDuration']);
    }

    public function testNumericStringsAreConverted(): void
    {
        $config = $this->jsConfig(['input_count' => '120', 'input_minRadius' => '1.2']);
        $this->assertSame(120, $config['density']);
        $this->assertSame(1.2, $config['minSize']);
    }

    public function testSnowflakesAreTrimmedAndEmptyEntriesRemoved(): void
    {
        $config = $this->jsConfig(['input_text' => ' ❄ ,, ★ , ']);
        $this->assertSame(['❄', '★'], $config['snowflakes']);
    }

    public function testEmptySnowflakesFallBackToDefaults(): void
    {
        $config = $this->jsConfig(['input_text' => ' , , ']);
        $this->assertSame(['❄', '❅', '❆'], $config['snowflakes']);
    }

    #[DataProvider('validColors')]
    public function testValidColorsAreAccepted(string $color): void
    {
        $this->assertSame($color, $this->jsConfig(['input_color' => $color])['color']);
    }

    public static function validColors(): array
    {
        return [['#fff'], ['#FFFF'], ['#a1b2c3'], ['#A1B2C3D4']];
    }

    #[DataProvider('invalidColors')]
    public function testInvalidColorsFallBackToDefault(string $color): void
    {
        $this->assertSame('#99ccff', $this->jsConfig(['input_color' => $color])['color']);
    }

    public static function invalidColors(): array
    {
        return [['red'], ['#12'], ['#gggggg'], ['"red'], ['#fff;background:url(x)'], ['']];
    }

    public function testColorIsTrimmed(): void
    {
        $this->assertSame('#abc', $this->jsConfig(['input_color' => ' #abc '])['color']);
    }

    public function testValidZIndex(): void
    {
        $this->assertSame(5000, $this->jsConfig(['input_zIndex' => '5000'])['zIndex']);
        $this->assertSame(-1, $this->jsConfig(['input_zIndex' => '-1'])['zIndex']);
    }

    public function testInvalidZIndexFallsBackToDefault(): void
    {
        foreach (['auto', '10px', '1;alert(1)', ''] as $value) {
            $this->assertSame(1000, $this->jsConfig(['input_zIndex' => $value])['zIndex'], $value);
        }
    }
}
