import process from 'node:process'
import { defineConfig, devices } from '@playwright/test'

const gridUiUrl = process.env.GRID_E2E_UI_URL ?? 'http://localhost:5173'
const switchUiUrl = process.env.SWITCH_E2E_UI_URL ?? 'http://localhost:3001'

export default defineConfig({
  testDir: './e2e-live',
  globalSetup: './e2e-live/global.setup.ts',
  timeout: 45_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { outputFolder: 'playwright-report-live', open: 'never' }]],
  outputDir: 'test-results/live',
  use: {
    ...devices['Desktop Chrome'],
    headless: true,
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'gridpbx-live',
      testMatch:
        /gridpbx-(account-projection|blacklist-form|call-activity|caller-id-list|callflow-dnd|callflow-form|callflow-ring-group|conference-form|device-panel-context|device-parity|device-fields|directory-form|extension-hotdesk|fax-box-form|group-form|layout-alignment|media-form|menu-form|phone-number-detail|provisioning-walkthrough|queue-form|reseller-administration|system-status|temporal-routing)\.spec\.ts/,
      use: {
        baseURL: gridUiUrl,
        storageState: '.playwright/.auth/gridpbx.json',
      },
    },
    {
      name: 'switch-ui-reference',
      testMatch: /switch-ui-device-parity\.spec\.ts/,
      use: {
        baseURL: switchUiUrl,
        storageState: '.playwright/.auth/switch-ui.json',
      },
    },
  ],
})
