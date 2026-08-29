import { expect, test, type Page } from '@playwright/test'

function collectPageIssues(page: Page): string[] {
  const issues: string[] = []
  page.on('console', (message) => {
    if (message.type() === 'error') issues.push(`console: ${message.text()}`)
  })
  page.on('pageerror', (error) => issues.push(`page: ${error.message}`))
  page.on('response', (response) => {
    if (response.status() >= 500) issues.push(`response: ${response.status()} ${response.url()}`)
  })

  return issues
}

const mediaRecord = {
  id: '9a3d5827-f11e-41bb-b5f1-0d1296da3aae',
  name: 'Main hold music',
  description: 'Lobby loop',
  language: 'en-us',
  media_source: 'upload',
  content_type: 'audio/mpeg',
  content_length: 4096,
  prompt_id: null,
  streamable: true,
  is_music_on_hold: true,
  last_synced_at: '2026-08-28T09:00:00+08:00',
  sync_status: 'healthy',
  created_at: '2026-08-28T09:00:00+08:00',
  updated_at: '2026-08-28T09:00:00+08:00',
}

test('keeps Media validation inline and its music-on-hold choice inside the viewport', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.route('**/api/v1/accounts/*/media?*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [mediaRecord],
        links: { first: null, last: null, prev: null, next: null },
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
          sync: { status: 'healthy', last_successful_at: null, error_message: null },
        },
      }),
    }),
  )

  await page.goto('/media')
  await expect(page.getByRole('heading', { name: 'Media & Music on Hold' })).toBeVisible()
  await page.getByRole('button', { name: 'Upload media' }).click()

  const upload = page.getByRole('dialog', { name: 'Upload media' })
  await upload.getByRole('button', { name: 'Upload media' }).click()
  for (const control of [upload.getByLabel('Media name'), upload.getByLabel('Media audio file')]) {
    await expect(control).toHaveAttribute('aria-invalid', 'true')
    await expect(control).toHaveClass(/border-red-400/)
  }
  await expect(upload.getByText('Enter a media name.')).toBeVisible()
  await expect(upload.getByText('Select an MP3, WAV, or OGG audio file.')).toBeVisible()
  await upload.getByRole('button', { name: 'Cancel' }).click()

  await page.getByRole('button', { name: 'Music on hold' }).click()
  const musicOnHold = page.getByRole('dialog', { name: 'Music on hold' })
  await musicOnHold.getByRole('button', { name: 'Hold media' }).click()
  const listbox = page.getByRole('listbox')
  const box = await listbox.boundingBox()
  const viewport = page.viewportSize()
  expect(box).not.toBeNull()
  expect(viewport).not.toBeNull()
  expect(box!.x).toBeGreaterThanOrEqual(0)
  expect(box!.y).toBeGreaterThanOrEqual(0)
  expect(box!.x + box!.width).toBeLessThanOrEqual(viewport!.width)
  expect(box!.y + box!.height).toBeLessThanOrEqual(viewport!.height)
  await expect(page.getByRole('option', { name: 'Main hold music' })).toBeVisible()
  expect(issues).toEqual([])
})
