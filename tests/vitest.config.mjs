import { defineConfig } from 'vitest/config';

// Vitest runs only the unit tests in js/ (the browser tests in e2e/ are run by Playwright)
export default defineConfig({
    test: {
        include: ['js/**/*.test.js'],
    },
});
