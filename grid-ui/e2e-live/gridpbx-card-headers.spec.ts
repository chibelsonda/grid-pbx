import { expect, test, type Locator, type Page } from '@playwright/test'

type HeaderMetrics = {
  height: number
  minHeight: string
  paddingBottom: string
  paddingTop: string
}

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

async function headerMetrics(header: Locator): Promise<HeaderMetrics> {
  return header.evaluate((element) => {
    const style = window.getComputedStyle(element)

    return {
      height: element.getBoundingClientRect().height,
      minHeight: style.minHeight,
      paddingBottom: style.paddingBottom,
      paddingTop: style.paddingTop,
    }
  })
}

test('keeps form card headers compact and aligned across pages', async ({ page }) => {
  const issues = collectPageIssues(page)

  await page.goto('/voicemail/new')

  const voicemailDialog = page.getByRole('dialog', { name: 'Create voicemail box' })
  const mailboxHeader = voicemailDialog
    .locator('.card-surface.overflow-hidden > header')
    .filter({ hasText: 'Mailbox identity' })
  const assignmentHeader = voicemailDialog
    .locator('.card-surface.overflow-hidden > header')
    .filter({ hasText: 'Assignment' })

  await expect(mailboxHeader).toBeVisible()
  await expect(assignmentHeader).toBeVisible()

  const mailboxMetrics = await headerMetrics(mailboxHeader)
  const assignmentMetrics = await headerMetrics(assignmentHeader)

  expect(mailboxMetrics).toMatchObject({
    minHeight: '56px',
    paddingBottom: '8px',
    paddingTop: '8px',
  })
  expect(assignmentMetrics).toMatchObject({
    minHeight: '56px',
    paddingBottom: '8px',
    paddingTop: '8px',
  })
  expect(Math.abs(mailboxMetrics.height - assignmentMetrics.height)).toBeLessThanOrEqual(1)

  await page.goto('/devices/new')

  const deviceDialog = page.getByRole('dialog', { name: 'Create device' })
  const deviceHeader = deviceDialog
    .locator('.card-surface.overflow-hidden > header')
    .filter({ hasText: 'Device identity' })

  await expect(deviceHeader).toBeVisible()
  await expect(headerMetrics(deviceHeader)).resolves.toMatchObject({
    minHeight: '56px',
    paddingBottom: '8px',
    paddingTop: '8px',
  })

  expect(issues).toEqual([])
})
