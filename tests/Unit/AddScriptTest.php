<?php
declare(strict_types=1);

namespace SnowFallAnimation\Tests;

use ProcessWire\FakeWire;

/**
 * Tests for the output of the script and stylesheet in the frontend (Page::render hook)
 */
class AddScriptTest extends ModuleTestCase
{
    private const HTML = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Content</p></body></html>';

    public function testNothingIsAddedIfSnowfallIsOff(): void
    {
        $m = $this->makeModule(['input_visibility' => '0']);
        $this->assertSame(self::HTML, $this->render($m, self::HTML));
    }

    public function testNothingIsAddedOutsideTheDateRange(): void
    {
        $m = $this->makeModule([
            'input_visibility' => '2',
            'input_start' => $this->ts('2026-12-01'),
            'input_end' => $this->ts('2027-01-06'),
        ]);
        $this->assertSame(self::HTML, $this->render($m, self::HTML));
    }

    public function testNothingIsAddedOnAdminPages(): void
    {
        $m = $this->makeModule(['input_visibility' => '1']);
        $this->assertSame(self::HTML, $this->render($m, self::HTML, 'admin'));
    }

    public function testNothingIsAddedOnAjaxRequests(): void
    {
        $m = $this->makeModule(['input_visibility' => '1']);
        FakeWire::$services['config']->ajax = true;
        $this->assertSame(self::HTML, $this->render($m, self::HTML));
    }

    public function testNothingIsAddedWithoutBodyTag(): void
    {
        $m = $this->makeModule(['input_visibility' => '1']);
        $partial = '<div>partial render</div>';
        $json = '{"a":1}';
        $this->assertSame($partial, $this->render($m, $partial));
        $this->assertSame($json, $this->render($m, $json));
    }

    public function testScriptIsAddedBeforeTheClosingBodyTag(): void
    {
        $m = $this->makeModule(['input_visibility' => '1']);
        $html = $this->render($m, self::HTML);

        $this->assertMatchesRegularExpression('#<script src="/site/modules/SnowFallAnimation/snow\.min\.js\?v=1\.0\.1" data-config="[^"]+"></script>\s*</body>#', $html);
    }

    public function testScriptIsAddedBeforeTheLastBodyTagOnly(): void
    {
        $m = $this->makeModule(['input_visibility' => '1']);
        $input = '<html><head></head><body><template></body></template><p>x</p></body></html>';
        $html = $this->render($m, $input);

        $this->assertSame(1, substr_count($html, 'snow.min.js'));
        $this->assertStringContainsString('<p>x</p><script src=', $html);
    }

    public function testStylesheetIsAddedToTheHead(): void
    {
        $m = $this->makeModule(['input_visibility' => '1']);
        $html = $this->render($m, self::HTML);

        $this->assertMatchesRegularExpression('#<link rel="stylesheet" type="text/css" href="/site/modules/SnowFallAnimation/snowfall\.min\.css\?v=1\.0\.1">\s*</head>#', $html);
    }

    public function testVersionParameterContainsNoTimestamp(): void
    {
        $m = $this->makeModule(['input_visibility' => '1']);
        $html = $this->render($m, self::HTML);

        $this->assertStringContainsString('snow.min.js?v=1.0.1"', $html);
    }

    public function testNoInlineScriptIsUsed(): void
    {
        $m = $this->makeModule(['input_visibility' => '1']);
        $html = $this->render($m, self::HTML);

        // every script tag must have a src attribute (CSP-friendly)
        preg_match_all('#<script\b[^>]*>#i', $html, $matches);
        $this->assertNotEmpty($matches[0]);
        foreach ($matches[0] as $tag) {
            $this->assertStringContainsString(' src="', $tag);
        }
    }

    public function testDataConfigContainsTheJsConfig(): void
    {
        $m = $this->makeModule(['input_visibility' => '1', 'input_count' => 77, 'input_color' => '#123456']);
        $html = $this->render($m, self::HTML);

        $this->assertSame($this->call($m, 'getJsConfig'), $this->extractConfig($html));
    }

    public function testDangerousSymbolsCannotBreakOutOfTheAttributeOrScript(): void
    {
        $payload = '</script><script>alert(1)</script>,"><img src=x onerror=alert(1)>,\'';
        $m = $this->makeModule(['input_visibility' => '1', 'input_text' => $payload]);
        $html = $this->render($m, self::HTML);

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('"><img', $html);

        // but the symbols arrive unchanged in the JavaScript
        $config = $this->extractConfig($html);
        $this->assertSame(['</script><script>alert(1)</script>', '"><img src=x onerror=alert(1)>', "'"], $config['snowflakes']);
    }

    private function extractConfig(string $html): array
    {
        $this->assertSame(1, preg_match('#data-config="([^"]*)"#', $html, $match));
        $json = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
