import { expect, test } from '@playwright/test'

const entityCreateActions = [
  { path: '/extensions', label: 'Create extension' },
  { path: '/devices', label: 'Create device' },
  { path: '/voicemail', label: 'Create voicemail box' },
  { path: '/call-routing', label: 'Create callflow' },
  { path: '/directories', label: 'Create directory' },
  { path: '/groups', label: 'Create group' },
  { path: '/queues', label: 'Create queue' },
  { path: '/menus', label: 'Create menu' },
  { path: '/business-hours', label: 'Create rule' },
  { path: '/blacklists', label: 'Create blacklist' },
  { path: '/caller-id-lists', label: 'Create Caller-ID list' },
  { path: '/conferences', label: 'Create conference' },
  { path: '/faxes', label: 'Create fax box' },
] as const

test('uses Create consistently for standalone entity actions', async ({ page }) => {
  for (const action of entityCreateActions) {
    await page.goto(action.path)

    const control = page
      .getByRole('button', { name: action.label, exact: true })
      .or(page.getByRole('link', { name: action.label, exact: true }))

    await expect(control).toBeVisible()
  }
})
