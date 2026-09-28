/**
 * Browser tests (end-to-end) for SnowFallAnimation
 *
 * Opens a real page of the website in Chromium and checks that the snowfall works as intended.
 * Requirement: the snowfall must be active on the tested page (module config: "On" or inside the date range).
 * If it is not active, all tests are skipped with a note.
 *
 * Run inside the tests folder:  npm run test:e2e
 */
import { test, expect } from '@playwright/test';

/**
 * Open the page, collect errors and read the snowfall config from the data-config attribute
 */
async function openPage(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        // failed requests of other files of the website (e.g. a missing favicon) are not our business -
        // the status of the snowfall files is checked separately
        if (message.type() === 'error' && !message.text().startsWith('Failed to load resource')) errors.push(message.text());
    });

    const assets = {};
    page.on('response', (response) => {
        const url = response.url();
        if (url.includes('/SnowFallAnimation/')) assets[url.split('?')[0].split('/').pop()] = response.status();
    });

    await page.goto('/', { waitUntil: 'load' });

    const script = page.locator('script[src*="SnowFallAnimation/snow.min.js"]');
    const active = (await script.count()) > 0;
    test.skip(!active, 'The snowfall is not active on this page - enable it in the module config ("On") and run the test again.');

    const config = JSON.parse(await script.getAttribute('data-config'));
    return { errors, assets, config };
}

test.describe('SnowFallAnimation', () => {

    test('loads script and stylesheet without errors', async ({ page }) => {
        const { errors, assets } = await openPage(page);
        await page.waitForTimeout(1000);

        expect(assets['snow.min.js'], 'snow.min.js HTTP status').toBe(200);
        expect(assets['snowfall.min.css'], 'snowfall.min.css HTTP status').toBe(200);
        expect(errors, 'JavaScript errors in the console').toEqual([]);
    });

    test('snowflakes appear with the configured symbols and color', async ({ page }) => {
        const { config } = await openPage(page);
        test.skip(config.density === 0, 'Density is 0 - no snowflakes expected');

        const flakes = page.locator('#snow-container .snowflake');
        await expect(flakes.first()).toBeAttached({ timeout: 3000 });

        const data = await flakes.evaluateAll((elements) => elements.map((el) => ({
            text: el.textContent,
            color: getComputedStyle(el).color,
            opacity: parseFloat(getComputedStyle(el).opacity),
        })));

        const expectedColor = await page.evaluate((color) => {
            const el = document.createElement('span');
            el.style.color = color;
            document.body.appendChild(el);
            const value = getComputedStyle(el).color;
            el.remove();
            return value;
        }, config.color);

        for (const flake of data) {
            expect(config.snowflakes, 'snowflake symbol').toContain(flake.text);
            expect(flake.color, 'snowflake color').toBe(expectedColor);
            expect(flake.opacity, 'snowflake is visible').toBeGreaterThan(0);
        }
    });

    test('snowflakes fall down (animation is running)', async ({ page }) => {
        const { config } = await openPage(page);
        test.skip(config.density === 0, 'Density is 0 - no snowflakes expected');

        const flake = page.locator('#snow-container .snowflake').first();
        await expect(flake).toBeAttached({ timeout: 3000 });
        const y1 = await flake.evaluate((el) => el.getBoundingClientRect().top);
        await page.waitForTimeout(700);
        const y2 = await flake.evaluate((el) => el.isConnected ? el.getBoundingClientRect().top : null);
        test.skip(y2 === null, 'Snowflake was removed in the meantime');
        expect(y2, 'snowflake moved down').toBeGreaterThan(y1);
    });

    test('number of snowflakes never exceeds the density', async ({ page }) => {
        const { config } = await openPage(page);
        let max = 0;
        for (let i = 0; i < 8; i++) {
            max = Math.max(max, await page.locator('#snow-container .snowflake').count());
            await page.waitForTimeout(500);
        }
        expect(max, 'maximum number of snowflakes').toBeLessThanOrEqual(config.density);
        if (config.density > 0) expect(max, 'snowflakes are created').toBeGreaterThan(0);
    });

    test('container covers the viewport with the configured z-index', async ({ page }) => {
        const { config } = await openPage(page);
        const container = page.locator('#snow-container');
        await expect(container).toBeAttached();

        const style = await container.evaluate((el) => {
            const cs = getComputedStyle(el);
            return { position: cs.position, zIndex: cs.zIndex, pointerEvents: cs.pointerEvents };
        });
        expect(style.position).toBe('fixed');
        expect(style.zIndex).toBe(String(config.zIndex));
        expect(style.pointerEvents).toBe('none');
    });

    test('snowflakes do not block clicks on the page', async ({ page }) => {
        const { config } = await openPage(page);
        test.skip(config.density === 0, 'Density is 0 - no snowflakes expected');
        await expect(page.locator('#snow-container .snowflake').first()).toBeAttached({ timeout: 3000 });

        // the element under the center of each visible snowflake must be a page element, not the snowflake
        const blocking = await page.evaluate(() => {
            const result = [];
            for (const flake of document.querySelectorAll('#snow-container .snowflake')) {
                const r = flake.getBoundingClientRect();
                const x = r.left + r.width / 2;
                const y = r.top + r.height / 2;
                if (x < 0 || y < 0 || x > innerWidth || y > innerHeight) continue;
                const hit = document.elementFromPoint(x, y);
                if (hit && hit.closest('#snow-container')) result.push(flake.textContent);
            }
            return result;
        });
        expect(blocking, 'snowflakes that catch clicks').toEqual([]);
    });

    test('no horizontal scrollbar is caused by the snowflakes', async ({ page }) => {
        await openPage(page);
        await page.waitForTimeout(1500);
        // compare the page width with and without the snowflakes (the page itself may already be wider)
        const overflow = await page.evaluate(() => {
            const root = document.documentElement;
            const container = document.getElementById('snow-container');
            const withSnow = root.scrollWidth - root.clientWidth;
            container.style.display = 'none';
            const withoutSnow = root.scrollWidth - root.clientWidth;
            container.style.display = '';
            return withSnow - withoutSnow;
        });
        expect(overflow, 'additional horizontal overflow caused by the snowflakes (px)').toBeLessThanOrEqual(0);
    });
});
