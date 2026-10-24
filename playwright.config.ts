import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 120_000,
    expect: { timeout: 15_000 },
    workers: 1,
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: 'http://localhost:5600',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        locale: 'es-VE',
    },
    outputDir: '/tmp/e2e-fase4/artifacts',
});
