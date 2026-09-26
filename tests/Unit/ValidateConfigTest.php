<?php
declare(strict_types=1);

namespace SnowFallAnimation\Tests;

use ProcessWire\SnowFallAnimation;

/**
 * Tests for the validation before the module config is saved (Modules::saveConfig hook)
 */
class ValidateConfigTest extends ModuleTestCase
{
    private const OLD_CONFIG = [
        'input_start' => 1000,
        'input_end' => 2000,
        'input_recurrence' => 1,
    ];

    public function testOtherModulesAreIgnored(): void
    {
        $m = $this->makeModule();
        $data = ['input_start' => $this->ts('2026-07-01'), 'input_end' => $this->ts('2026-06-20'), 'input_count' => 99999];
        $this->assertSame($data, $this->validate($m, $data, 'OtherModule'));
        $this->assertSame([], $m->errors);
    }

    public function testSingleValueSaveIsIgnored(): void
    {
        // saveConfig($module, 'key', 'value') passes a string instead of an array -> must not crash or change anything
        $m = $this->makeModule();
        $event = new \ProcessWire\HookEvent(['SnowFallAnimation', 'input_count', 5]);
        $this->call($m, 'validateDates', $event);
        $this->assertSame('input_count', $event->arguments(1));
        $this->assertSame([], $m->errors);
    }

    public function testModuleObjectAsClassArgumentWorks(): void
    {
        // generateDates() calls saveConfig($this, ...) with the module object
        $m = $this->makeModule(self::OLD_CONFIG);
        $data = $this->validate($m, ['input_start' => $this->ts('2026-07-01'), 'input_end' => $this->ts('2026-06-20')], $m);
        $this->assertCount(1, $m->errors);
        $this->assertSame(1000, $data['input_start']);
    }

    public function testValidDatesAreSaved(): void
    {
        $m = $this->makeModule();
        $input = ['input_start' => $this->ts('2026-06-20'), 'input_end' => $this->ts('2026-07-20'), 'input_recurrence' => 1];
        $this->assertSame($input, $this->validate($m, $input));
        $this->assertSame([], $m->errors);
        $this->assertNull($this->session()->get('snowfalldateserror'));
    }

    public function testStartAfterEndIsRejectedAndOldDatesAreKept(): void
    {
        $m = $this->makeModule(self::OLD_CONFIG);
        $data = $this->validate($m, [
            'input_start' => $this->ts('2026-07-01'),
            'input_end' => $this->ts('2026-06-20'),
            'input_recurrence' => 0,
            'input_count' => 80,
        ]);

        $this->assertSame(1000, $data['input_start']);
        $this->assertSame(2000, $data['input_end']);
        $this->assertSame(1, $data['input_recurrence']);
        $this->assertSame(80, $data['input_count'], 'other values must still be saved');
        $this->assertStringContainsString('The end date must be after the start date.', $m->errors[0]);
        $this->assertSame(1, $this->session()->get('snowfalldateserror'));
    }

    public function testEndDateInThePastIsRejected(): void
    {
        $m = $this->makeModule(self::OLD_CONFIG);
        $data = $this->validate($m, ['input_start' => $this->ts('2026-01-01'), 'input_end' => $this->ts('2026-06-14')]);

        $this->assertSame(2000, $data['input_end']);
        $this->assertStringContainsString('The end date must be in the future', $m->errors[0]);
    }

    public function testEndDateTodayIsAllowed(): void
    {
        $m = $this->makeModule();
        $this->validate($m, ['input_start' => $this->ts('2026-06-01'), 'input_end' => $this->ts(self::TODAY)]);
        $this->assertSame([], $m->errors);
    }

    public function testRangeOfOneYearOrMoreIsRejectedWithRecurrence(): void
    {
        $m = $this->makeModule(self::OLD_CONFIG);
        $data = $this->validate($m, [
            'input_start' => $this->ts('2026-06-20'),
            'input_end' => $this->ts('2027-06-20'),
            'input_recurrence' => 1,
        ]);

        $this->assertSame(1000, $data['input_start']);
        $this->assertStringContainsString('less than 1 year', $m->errors[0]);
    }

    public function testSubmittedRecurrenceValueIsUsedNotTheSavedOne(): void
    {
        // saved: recurrence on / submitted: recurrence off -> long range is allowed
        $m = $this->makeModule(['input_recurrence' => 1]);
        $this->validate($m, [
            'input_start' => $this->ts('2026-06-20'),
            'input_end' => $this->ts('2027-08-20'),
            'input_recurrence' => 0,
        ]);
        $this->assertSame([], $m->errors);
    }

    public function testRecurrenceIsRemovedIfADateIsMissing(): void
    {
        $m = $this->makeModule();
        $data = $this->validate($m, ['input_start' => $this->ts('2026-06-20'), 'input_end' => '', 'input_recurrence' => 1]);
        $this->assertSame(0, $data['input_recurrence']);
        $this->assertSame([], $m->errors);
    }

    public function testDefaultsAreUsedIfNoOldDatesExist(): void
    {
        $m = $this->makeModule([]);
        $data = $this->validate($m, ['input_start' => $this->ts('2026-07-01'), 'input_end' => $this->ts('2026-06-20'), 'input_recurrence' => 1]);
        $this->assertSame('', $data['input_start']);
        $this->assertSame('', $data['input_end']);
        $this->assertSame(0, $data['input_recurrence']);
    }

    public function testMinAndMaxAreSwappedOnSave(): void
    {
        $m = $this->makeModule();
        $data = $this->validate($m, [
            'input_minRadius' => 2.5, 'input_maxRadius' => 1.0,
            'input_minSpeed' => 12, 'input_maxSpeed' => 3,
        ]);
        $this->assertEquals([1.0, 2.5, 3, 12], [$data['input_minRadius'], $data['input_maxRadius'], $data['input_minSpeed'], $data['input_maxSpeed']]);
    }

    public function testLimitsAreAppliedOnSave(): void
    {
        $m = $this->makeModule();
        $data = $this->validate($m, [
            'input_count' => '5000',
            'input_minRadius' => '0.01',
            'input_maxRadius' => '99',
            'input_minSpeed' => '0',
            'input_maxSpeed' => '500',
        ]);
        $this->assertSame(SnowFallAnimation::MAX_DENSITY, $data['input_count']);
        $this->assertSame(SnowFallAnimation::MIN_SIZE, $data['input_minRadius']);
        $this->assertSame((float)SnowFallAnimation::MAX_SIZE, $data['input_maxRadius']);
        $this->assertSame(SnowFallAnimation::MIN_DURATION, $data['input_minSpeed']);
        $this->assertSame(SnowFallAnimation::MAX_DURATION, $data['input_maxSpeed']);
    }

    public function testEmptyNumericValuesAreLeftUntouched(): void
    {
        $m = $this->makeModule();
        $data = $this->validate($m, ['input_count' => '']);
        $this->assertSame('', $data['input_count']);
    }
}
