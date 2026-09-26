// @vitest-environment jsdom
/**
 * Tests for snow.js and snow.min.js (both files are tested, so the minified file must be rebuilt after changes)
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const moduleDir = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const files = ['snow.js', 'snow.min.js'];

/**
 * Run the script like the browser does: as <script src="..." data-config="...">
 * @param {string} file
 * @param {object|string|null} config object -> JSON, string -> used as is, null -> no data-config attribute
 */
function runScript(file, config = null) {
    const code = readFileSync(resolve(moduleDir, file), 'utf8');
    const script = document.createElement('script');
    if (config !== null) {
        script.setAttribute('data-config', typeof config === 'string' ? config : JSON.stringify(config));
    }
    document.body.appendChild(script);
    Object.defineProperty(document, 'currentScript', { value: script, configurable: true });
    try {
        new Function(code)();
    } finally {
        Object.defineProperty(document, 'currentScript', { value: null, configurable: true });
    }
    return document.getElementById('snow-container');
}

const flakes = (container) => [...container.querySelectorAll('.snowflake')];

describe.each(files)('%s', (file) => {

    beforeEach(() => {
        vi.useFakeTimers();
        document.body.innerHTML = '';
        delete window.SnowTheme;
        delete window.SnowThemeConfig;
    });

    afterEach(() => {
        vi.clearAllTimers();
        vi.useRealTimers();
    });

    it('creates the container and the first 10 snowflakes', () => {
        const container = runScript(file);
        expect(container).not.toBeNull();
        expect(flakes(container)).toHaveLength(10);
    });

    it('uses the defaults without config', () => {
        const container = runScript(file);
        expect(container.style.zIndex).toBe('999999');
        expect(window.SnowTheme.config.density).toBe(50);
        expect(window.SnowTheme.config.interval).toBe(200);
        for (const flake of flakes(container)) {
            expect(['❄', '❅', '❆']).toContain(flake.textContent);
        }
    });

    it('applies the config from the data-config attribute to the first snowflakes', () => {
        const container = runScript(file, { color: '#ff0000', zIndex: 1234, snowflakes: ['★'] });
        expect(container.style.zIndex).toBe('1234');
        for (const flake of flakes(container)) {
            expect(flake.style.color).toBe('rgb(255, 0, 0)');
            expect(flake.textContent).toBe('★');
        }
    });

    it('still supports window.SnowThemeConfig as fallback', () => {
        window.SnowThemeConfig = { snowflakes: ['☃'], zIndex: 5 };
        const container = runScript(file);
        expect(container.style.zIndex).toBe('5');
        expect(flakes(container)[0].textContent).toBe('☃');
    });

    it('data-config has priority over window.SnowThemeConfig', () => {
        window.SnowThemeConfig = { snowflakes: ['☃'] };
        const container = runScript(file, { snowflakes: ['★'] });
        expect(flakes(container)[0].textContent).toBe('★');
    });

    it('uses the defaults if data-config contains invalid JSON', () => {
        const container = runScript(file, '{invalid json');
        expect(container.style.zIndex).toBe('999999');
        expect(flakes(container)).toHaveLength(10);
    });

    it('falls back to the default symbols if the list is empty', () => {
        const container = runScript(file, { snowflakes: [] });
        expect(['❄', '❅', '❆']).toContain(flakes(container)[0].textContent);
    });

    it('inserts the symbols as text and never as HTML (XSS)', () => {
        const payload = '<img src=x onerror="window.__xss = true">';
        const container = runScript(file, { snowflakes: [payload] });

        expect(container.querySelector('img')).toBeNull();
        expect(flakes(container)[0].textContent).toBe(payload);
        expect(window.__xss).toBeUndefined();
    });

    it('derives the interval from density and average duration', () => {
        const setIntervalSpy = vi.spyOn(globalThis, 'setInterval');
        runScript(file, { density: 100, minDuration: 5, maxDuration: 15 });
        // average duration 10 s / 100 flakes = 100 ms
        expect(setIntervalSpy).toHaveBeenCalledWith(expect.any(Function), 100);
    });

    it('uses a minimum interval of 16 ms', () => {
        runScript(file, { density: 1000, minDuration: 1, maxDuration: 1 });
        expect(window.SnowTheme.config.interval).toBe(16);
    });

    it('keeps an explicitly given interval', () => {
        runScript(file, { interval: 500 });
        expect(window.SnowTheme.config.interval).toBe(500);
    });

    it('never creates more snowflakes than the density', () => {
        const container = runScript(file, { density: 15, minDuration: 60, maxDuration: 60 });
        vi.advanceTimersByTime(30000);
        expect(flakes(container).length).toBe(15);
    });

    it('creates no snowflakes with density 0', () => {
        const container = runScript(file, { density: 0 });
        vi.advanceTimersByTime(5000);
        expect(flakes(container)).toHaveLength(0);
    });

    it('removes snowflakes after their fall duration', () => {
        const container = runScript(file, { density: 10, minDuration: 2, maxDuration: 2, interval: 100000 });
        expect(flakes(container)).toHaveLength(10);
        vi.advanceTimersByTime(2001);
        expect(flakes(container)).toHaveLength(0);
    });

    it('keeps the size and duration inside the configured range', () => {
        const container = runScript(file, { density: 50, minSize: 1, maxSize: 2, minDuration: 3, maxDuration: 4 });
        vi.advanceTimersByTime(2000);
        for (const flake of flakes(container)) {
            const scale = Number(flake.style.scale);
            const duration = Number(flake.style.animation.match(/snowfall ([\d.]+)s/)[1]);
            expect(scale).toBeGreaterThanOrEqual(1);
            expect(scale).toBeLessThanOrEqual(2);
            expect(duration).toBeGreaterThanOrEqual(3);
            expect(duration).toBeLessThanOrEqual(4);
        }
    });
});
