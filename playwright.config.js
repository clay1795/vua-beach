import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 90_000,
    reporter: [['list'], ['html', { outputFolder: 'output/playwright/report', open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8010',
        locale: 'vi-VN',
        timezoneId: 'Asia/Ho_Chi_Minh',
        reducedMotion: 'reduce',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: './scripts/e2e-server.sh',
        url: 'http://127.0.0.1:8010/up',
        reuseExistingServer: false,
        timeout: 120_000,
        stdout: 'ignore',
        stderr: 'ignore',
    },
    projects: [
        { name: 'Chrome', use: { ...devices['Desktop Chrome'], channel: 'chrome' } },
        { name: 'Safari-WebKit', use: { ...devices['Desktop Safari'] } },
        { name: 'iPhone', use: { ...devices['iPhone 15'] } },
        { name: 'Android', use: { ...devices['Pixel 7'] } },
    ],
});
