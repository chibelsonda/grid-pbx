import { expect, test, type Locator } from '@playwright/test'

interface ContentEdges {
  left: number
  right: number
}

async function contentEdges(container: Locator): Promise<ContentEdges> {
  return container.evaluate((element) => {
    const bounds = element.getBoundingClientRect()
    const styles = window.getComputedStyle(element)

    return {
      left: bounds.left + Number.parseFloat(styles.paddingLeft),
      right: bounds.right - Number.parseFloat(styles.paddingRight),
    }
  })
}

test.use({ viewport: { width: 2048, height: 900 } })

for (const route of ['/extensions', '/devices', '/accounts']) {
  test(`aligns the ${route} header and main content gutters`, async ({ page }) => {
    await page.goto(route)

    const headerContainer = page.locator('main > section .page-container').first()
    const bodyContainer = page.locator('main > div.page-container').first()

    await expect(headerContainer).toBeVisible()
    await expect(bodyContainer).toBeVisible()

    const header = await contentEdges(headerContainer)
    const body = await contentEdges(bodyContainer)

    expect(Math.abs(header.left - body.left)).toBeLessThan(1)
    expect(Math.abs(header.right - body.right)).toBeLessThan(1)

    if (route === '/extensions') {
      await page.screenshot({ path: 'test-results/live/layout-extensions.png', fullPage: true })
    }
  })
}
