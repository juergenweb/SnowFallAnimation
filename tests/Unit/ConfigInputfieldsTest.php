<?php
declare(strict_types=1);

namespace SnowFallAnimation\Tests;

use ProcessWire\HookEvent;
use ProcessWire\Inputfield;
use ProcessWire\InputfieldFieldset;
use ProcessWire\InputfieldWrapper;
use ProcessWire\SnowFallAnimation;

/**
 * Tests for the module configuration form and the fieldset hook
 */
class ConfigInputfieldsTest extends ModuleTestCase
{
    private function form(array $config = [], string $today = self::TODAY): InputfieldWrapper
    {
        $m = $this->makeModule($config, $today);
        $wrapper = new InputfieldWrapper();
        $m->getModuleConfigInputfields($wrapper);
        return $wrapper;
    }

    private function statusText(InputfieldWrapper $form): string
    {
        return (string)$form->children[0]->properties['markupText'];
    }

    // ---------- form fields ----------

    public function testAllFieldsArePresentExactlyOnce(): void
    {
        $form = $this->form();
        $names = ['input_visibility', 'input_start', 'input_end', 'input_recurrence', 'input_count',
            'input_minRadius', 'input_maxRadius', 'input_minSpeed', 'input_maxSpeed', 'input_text', 'input_color', 'input_zIndex'];
        foreach ($names as $name) {
            $this->assertSame(1, $form->countByName($name), $name);
        }
    }

    public function testFieldsetsHaveNames(): void
    {
        $form = $this->form();
        $this->assertInstanceOf(InputfieldFieldset::class, $form->getByName('fieldset1'));
        $this->assertInstanceOf(InputfieldFieldset::class, $form->getByName('fieldset2'));
    }

    public function testZIndexFieldIsInTheStylingFieldset(): void
    {
        $fieldset2 = $this->form(['input_zIndex' => '4242'])->getByName('fieldset2');
        $zIndex = $fieldset2->getByName('input_zIndex');
        $this->assertNotNull($zIndex);
        $this->assertSame('4242', $zIndex->attr('value'));
    }

    public function testLimitsAreSetAsMaxAttributes(): void
    {
        $form = $this->form();
        $this->assertSame(SnowFallAnimation::MAX_DENSITY, $form->getByName('input_count')->attr('max'));
        $this->assertSame(SnowFallAnimation::MAX_SIZE, $form->getByName('input_minRadius')->attr('max'));
        $this->assertSame(SnowFallAnimation::MAX_SIZE, $form->getByName('input_maxRadius')->attr('max'));
        $this->assertSame(SnowFallAnimation::MAX_DURATION, $form->getByName('input_minSpeed')->attr('max'));
        $this->assertSame(SnowFallAnimation::MAX_DURATION, $form->getByName('input_maxSpeed')->attr('max'));
    }

    public function testRecurrenceCheckboxIsChecked(): void
    {
        $checkbox = $this->form(['input_recurrence' => 1])->getByName('input_recurrence');
        $this->assertSame('checked', $checkbox->attr('checked'));
    }

    public function testRecurrenceCheckboxIsNotChecked(): void
    {
        $checkbox = $this->form(['input_recurrence' => 0])->getByName('input_recurrence');
        $this->assertNull($checkbox->attr('checked'));
        $this->assertNull($checkbox->attr('value'), 'the value attribute must not be overwritten');
    }

    // ---------- status message ----------

    public function testStatusOff(): void
    {
        $form = $this->form(['input_visibility' => '0']);
        $this->assertStringContainsString('At the moment the snowfall is disabled.', $this->statusText($form));
        $this->assertStringContainsString('inactive', $this->statusText($form));
    }

    public function testStatusOn(): void
    {
        $form = $this->form(['input_visibility' => '1']);
        $this->assertStringContainsString('At the moment the snowfall is enabled.', $this->statusText($form));
        $this->assertStringContainsString('snowfall-state active', $this->statusText($form));
    }

    public function testStatusActiveWithEndDate(): void
    {
        $form = $this->form(['input_visibility' => '2', 'input_start' => $this->ts('2026-06-01'), 'input_end' => $this->ts('2026-07-01')]);
        $this->assertStringContainsString('It will be disabled on 2026-07-01.', $this->statusText($form));
    }

    public function testStatusWaitingForStartDate(): void
    {
        $form = $this->form(['input_visibility' => '2', 'input_start' => $this->ts('2026-12-01'), 'input_end' => $this->ts('2027-01-06')]);
        $this->assertStringContainsString('It will be enabled on 2026-12-01 automatically.', $this->statusText($form));
    }

    public function testStatusOnlyFutureStartDate(): void
    {
        $form = $this->form(['input_visibility' => '2', 'input_start' => $this->ts('2026-12-01')]);
        $this->assertStringContainsString('It will be enabled on 2026-12-01 automatically.', $this->statusText($form));
    }

    public function testStatusAfterEndDate(): void
    {
        $form = $this->form(['input_visibility' => '2', 'input_start' => $this->ts('2026-01-01'), 'input_end' => $this->ts('2026-02-01')]);
        $this->assertStringContainsString('At the moment the snowfall is disabled.', $this->statusText($form));
        $this->assertStringContainsString('inactive', $this->statusText($form));
    }

    // ---------- alert box ----------

    public function testActiveStateIsAGreenAlert(): void
    {
        $html = $this->statusText($this->form(['input_visibility' => '1']));
        $this->assertStringContainsString('class="uk-alert uk-alert-success snowfall-state active"', $html);
        $this->assertStringContainsString('fa-check-circle', $html);
    }

    public function testScheduledStateIsABlueAlert(): void
    {
        $html = $this->statusText($this->form(['input_visibility' => '2', 'input_start' => $this->ts('2026-12-01')]));
        $this->assertStringContainsString('class="uk-alert uk-alert-primary snowfall-state inactive"', $html);
        $this->assertStringContainsString('fa-clock-o', $html);
    }

    public function testOffStateIsANeutralAlert(): void
    {
        foreach ([['input_visibility' => '0'], ['input_visibility' => '2', 'input_end' => $this->ts('2026-06-01')]] as $config) {
            $html = $this->statusText($this->form($config));
            $this->assertStringContainsString('class="uk-alert snowfall-state inactive"', $html);
            $this->assertStringContainsString('fa-times-circle', $html);
        }
    }

    public function testStatusTextIsEscaped(): void
    {
        // the text is translatable - HTML in a translation must not be rendered
        $html = $this->statusText($this->form(['input_visibility' => '1']));
        $this->assertStringNotContainsString('<p', $html);
        $this->assertStringContainsString('role="status"', $html);
    }

    // ---------- openFieldset() ----------

    private function renderFieldset(Inputfield $fieldset): void
    {
        $m = $this->makeModule();
        $this->call($m, 'openFieldset', new HookEvent([], $fieldset));
    }

    private function fieldset(string $name): InputfieldFieldset
    {
        $fieldset = new InputfieldFieldset();
        $fieldset->attr('name', $name);
        $fieldset->collapsed = Inputfield::collapsedYes;
        return $fieldset;
    }

    public function testFieldsetIsOpenedAfterAnError(): void
    {
        \ProcessWire\FakeWire::$services['input']->get = ['name' => 'SnowFallAnimation'];
        $this->session()->set('snowfalldateserror', 1);

        $fieldset = $this->fieldset('fieldset1');
        $this->renderFieldset($fieldset);

        $this->assertSame(Inputfield::collapsedNo, $fieldset->collapsed);
        $this->assertNull($this->session()->get('snowfalldateserror'), 'the error flag must be removed');
    }

    public function testOtherFieldsetsStayClosed(): void
    {
        \ProcessWire\FakeWire::$services['input']->get = ['name' => 'SnowFallAnimation'];
        $this->session()->set('snowfalldateserror', 1);

        $fieldset = $this->fieldset('fieldset2');
        $this->renderFieldset($fieldset);

        $this->assertSame(Inputfield::collapsedYes, $fieldset->collapsed);
        $this->assertSame(1, $this->session()->get('snowfalldateserror'), 'the flag must stay for fieldset1');
    }

    public function testFieldsetOnOtherModulePagesIsIgnored(): void
    {
        \ProcessWire\FakeWire::$services['input']->get = ['name' => 'OtherModule'];
        $this->session()->set('snowfalldateserror', 1);

        $fieldset = $this->fieldset('fieldset1');
        $this->renderFieldset($fieldset);

        $this->assertSame(Inputfield::collapsedYes, $fieldset->collapsed);
    }

    public function testFieldsetStaysClosedWithoutError(): void
    {
        \ProcessWire\FakeWire::$services['input']->get = ['name' => 'SnowFallAnimation'];

        $fieldset = $this->fieldset('fieldset1');
        $this->renderFieldset($fieldset);

        $this->assertSame(Inputfield::collapsedYes, $fieldset->collapsed);
    }
}
