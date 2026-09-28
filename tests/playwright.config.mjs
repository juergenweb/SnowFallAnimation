import { defineConfig, devices } from '@playwright/test';

/**
 * Browser tests (end-to-end) for SnowFallAnimation
 *
 * The URL of the page to test can be set with the environment variable SNOWFALL_URL:
 *   Windows (cmd):   set SNOWFALL_URL=http://webseite2.test/ && npm run test:e2e
 *   PowerShell:      $env:SNOWFALL_URL="http://webseite2.test/"; npm run test:e2e
 *   Linux/macOS:     SNOWFALL_URL=http://webseite2.test/ npm run test:e2e
 */
export default defineConfig({
    testDir: './e2e',
    timeout: 30000,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL: process.env.SNOWFALL_URL || 'http://webseite2.test/',
        ignoreHTTPSErrors: true,
        launchOptions: process.env.PW_CHROMIUM_PATH ? { executablePath: process.env.PW_CHROMIUM_PATH } : {},
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile', use: { ...devices['Pixel 7'] } },
    ],
});
