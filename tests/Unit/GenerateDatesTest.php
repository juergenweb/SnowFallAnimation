<?php
declare(strict_types=1);

namespace SnowFallAnimation\Tests;

use ProcessWire\HookEvent;

/**
 * Tests for the yearly recurrence (LazyCron::everyDay hook)
 */
class GenerateDatesTest extends ModuleTestCase
{
    private function runCron(array $config, string $today = self::TODAY): \ProcessWire\SnowFallAnimation
    {
        $m = $this->makeModule($config, $today);
        $this->call($m, 'generateDates', new HookEvent());
        return $m;
    }

    private function savedDates(): array
    {
        $saved = $this->modules()->saved;
        $this->assertCount(1, $saved, 'config must be saved exactly once');
        return [date('Y-m-d', $saved[0]['input_start']), date('Y-m-d', $saved[0]['input_end'])];
    }

    public function testNothingHappensWithoutRecurrence(): void
    {
        $this->runCron(['input_recurrence' => 0, 'input_start' => $this->ts('2025-12-01'), 'input_end' => $this->ts('2026-01-06')]);
        $this->assertSame([], $this->modules()->saved);
    }

    public function testNothingHappensIfEndDateIsInTheFuture(): void
    {
        $this->runCron(['input_recurrence' => 1, 'input_start' => $this->ts('2026-06-01'), 'input_end' => $this->ts('2026-06-16')]);
        $this->assertSame([], $this->modules()->saved);
    }

    public function testNothingHappensIfADateIsMissing(): void
    {
        $this->runCron(['input_recurrence' => 1, 'input_start' => $this->ts('2025-12-01'), 'input_end' => '']);
        $this->runCron(['input_recurrence' => 1, 'input_start' => '', 'input_end' => $this->ts('2026-01-06')]);
        $this->assertSame([], $this->modules()->saved);
    }

    public function testDatesAreMovedByOneYear(): void
    {
        $this->runCron(['input_recurrence' => 1, 'input_start' => $this->ts('2025-12-01'), 'input_end' => $this->ts('2026-01-06')]);
        $this->assertSame(['2026-12-01', '2027-01-06'], $this->savedDates());
    }

    public function testDatesAreMovedOnTheEndDateItself(): void
    {
        $this->runCron(['input_recurrence' => 1, 'input_start' => $this->ts('2026-06-01'), 'input_end' => $this->ts(self::TODAY)]);
        $this->assertSame(['2027-06-01', '2027-06-15'], $this->savedDates());
    }

    public function testSeveralYearsAreSkippedAfterALongDowntime(): void
    {
        $this->runCron(['input_recurrence' => 1, 'input_start' => $this->ts('2021-12-01'), 'input_end' => $this->ts('2022-01-06')]);
        $this->assertSame(['2026-12-01', '2027-01-06'], $this->savedDates());
    }

    public function testLeapDayBecomes28thOfFebruary(): void
    {
        $this->runCron(['input_recurrence' => 1, 'input_start' => $this->ts('2024-02-29'), 'input_end' => $this->ts('2024-03-10')], '2024-03-10');
        $this->assertSame(['2025-02-28', '2025-03-10'], $this->savedDates());
    }

    public function testOtherConfigValuesAreKept(): void
    {
        $this->runCron([
            'input_recurrence' => 1,
            'input_start' => $this->ts('2025-12-01'),
            'input_end' => $this->ts('2026-01-06'),
            'input_count' => 123,
            'input_color' => '#abcdef',
        ]);
        $saved = $this->modules()->saved[0];
        $this->assertSame(123, $saved['input_count']);
        $this->assertSame('#abcdef', $saved['input_color']);
        $this->assertSame(1, $saved['input_recurrence']);
    }

    public function testModuleStateIsUpdatedAfterSaving(): void
    {
        $m = $this->runCron(['input_recurrence' => 1, 'input_visibility' => '2', 'input_start' => $this->ts('2025-06-01'), 'input_end' => $this->ts('2026-06-10')]);
        $this->assertSame('2026-06-01', $this->getProperty($m, 'dateStart')->format('Y-m-d'));
        $this->assertSame('2027-06-10', $this->getProperty($m, 'dateEnd')->format('Y-m-d'));
        $this->assertTrue($this->call($m, 'isActive'), 'new range 2026-06-01 to 2027-06-10 contains today');
    }

    public function testRecurrenceAsStringOrBoolWorks(): void
    {
        $this->runCron(['input_recurrence' => '1', 'input_start' => $this->ts('2025-12-01'), 'input_end' => $this->ts('2026-01-06')]);
        $this->assertCount(1, $this->modules()->saved);
    }
}
