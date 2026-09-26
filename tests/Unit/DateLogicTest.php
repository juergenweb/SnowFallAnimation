<?php
declare(strict_types=1);

namespace SnowFallAnimation\Tests;

use DateTime;

/**
 * Tests for date conversion, date range and activation state
 */
class DateLogicTest extends ModuleTestCase
{
    // ---------- toDate() ----------

    public function testToDateReturnsNullForEmptyValues(): void
    {
        $m = $this->makeModule();
        foreach (['', null, false, 0, '0'] as $value) {
            $this->assertNull($this->call($m, 'toDate', $value), var_export($value, true));
        }
    }

    public function testToDateConvertsTimestampToMidnight(): void
    {
        $m = $this->makeModule();
        $date = $this->call($m, 'toDate', $this->ts('2026-12-24 15:30:00'));
        $this->assertSame('2026-12-24 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testToDateAcceptsTimestampAsString(): void
    {
        $m = $this->makeModule();
        $date = $this->call($m, 'toDate', (string)$this->ts('2026-12-24'));
        $this->assertSame('2026-12-24', $date->format('Y-m-d'));
    }

    public function testToDateAcceptsDateString(): void
    {
        $m = $this->makeModule();
        $this->assertSame('2026-12-24', $this->call($m, 'toDate', '2026-12-24')->format('Y-m-d'));
    }

    public function testToDateReturnsNullForInvalidString(): void
    {
        $m = $this->makeModule();
        $this->assertNull($this->call($m, 'toDate', 'no date'));
    }

    // ---------- addYears() ----------

    public function testAddYearsKeepsDayAndMonth(): void
    {
        $m = $this->makeModule();
        $this->assertSame('2027-12-01', $this->call($m, 'addYears', new DateTime('2026-12-01'), 1)->format('Y-m-d'));
        $this->assertSame('2030-01-06', $this->call($m, 'addYears', new DateTime('2026-01-06'), 4)->format('Y-m-d'));
    }

    public function testAddYearsUses28thOfFebruaryInNonLeapYears(): void
    {
        $m = $this->makeModule();
        $this->assertSame('2025-02-28', $this->call($m, 'addYears', new DateTime('2024-02-29'), 1)->format('Y-m-d'));
    }

    public function testAddYearsKeeps29thOfFebruaryInLeapYears(): void
    {
        $m = $this->makeModule();
        $this->assertSame('2028-02-29', $this->call($m, 'addYears', new DateTime('2024-02-29'), 4)->format('Y-m-d'));
    }

    // ---------- isActive() / checkInRange() ----------

    public function testVisibilityOffIsInactive(): void
    {
        $m = $this->makeModule(['input_visibility' => '0']);
        $this->assertFalse($this->call($m, 'isActive'));
    }

    public function testVisibilityOnIsActiveEvenOutsideTheDates(): void
    {
        $m = $this->makeModule([
            'input_visibility' => '1',
            'input_start' => $this->ts('2027-01-01'),
            'input_end' => $this->ts('2027-02-01'),
        ]);
        $this->assertTrue($this->call($m, 'isActive'));
    }

    public function testVisibilityAsIntegerWorksToo(): void
    {
        $m = $this->makeModule(['input_visibility' => 1]);
        $this->assertTrue($this->call($m, 'isActive'));
    }

    public function testDateRangeActiveInside(): void
    {
        $m = $this->makeModule([
            'input_visibility' => '2',
            'input_start' => $this->ts('2026-06-01'),
            'input_end' => $this->ts('2026-07-01'),
        ]);
        $this->assertTrue($this->call($m, 'isActive'));
    }

    public function testDateRangeInactiveBeforeStart(): void
    {
        $m = $this->makeModule([
            'input_visibility' => '2',
            'input_start' => $this->ts('2026-06-16'),
            'input_end' => $this->ts('2026-07-01'),
        ]);
        $this->assertFalse($this->call($m, 'isActive'));
    }

    public function testStartDateIsIncluded(): void
    {
        $m = $this->makeModule([
            'input_visibility' => '2',
            'input_start' => $this->ts(self::TODAY),
            'input_end' => $this->ts('2026-07-01'),
        ]);
        $this->assertTrue($this->call($m, 'isActive'));
    }

    public function testEndDateIsExcluded(): void
    {
        $m = $this->makeModule([
            'input_visibility' => '2',
            'input_start' => $this->ts('2026-06-01'),
            'input_end' => $this->ts(self::TODAY),
        ]);
        $this->assertFalse($this->call($m, 'isActive'));
    }

    public function testOnlyFutureStartDateIsInactive(): void
    {
        $m = $this->makeModule(['input_visibility' => '2', 'input_start' => $this->ts('2026-12-01')]);
        $this->assertFalse($this->call($m, 'isActive'));
    }

    public function testOnlyPastStartDateIsActive(): void
    {
        $m = $this->makeModule(['input_visibility' => '2', 'input_start' => $this->ts('2026-01-01')]);
        $this->assertTrue($this->call($m, 'isActive'));
    }

    public function testOnlyPastEndDateIsInactive(): void
    {
        $m = $this->makeModule(['input_visibility' => '2', 'input_end' => $this->ts('2026-06-01')]);
        $this->assertFalse($this->call($m, 'isActive'));
    }

    public function testOnlyFutureEndDateIsActive(): void
    {
        $m = $this->makeModule(['input_visibility' => '2', 'input_end' => $this->ts('2026-12-01')]);
        $this->assertTrue($this->call($m, 'isActive'));
    }

    public function testInitRegistersAllHooks(): void
    {
        $m = $this->makeModule();
        $methods = array_map(fn($hook) => $hook[1], $m->hooks);
        $this->assertEqualsCanonicalizing(
            ['Page::render', 'Modules::saveConfig', 'Inputfield::render', 'LazyCron::everyDay'],
            $methods
        );
    }
}
