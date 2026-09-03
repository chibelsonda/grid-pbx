import { existsSync, readFileSync } from 'node:fs'
import process from 'node:process'

import { expect, test, type Locator, type Page } from '@playwright/test'

type PublicCallflowNode = {
  module?: string
  reference_status?: string
  settings?: Record<string, unknown>
  target?: unknown
  children?: Record<string, PublicCallflowNode>
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

async function replaceInputValue(input: Locator, value: string): Promise<void> {
  await input.evaluate((element, nextValue) => {
    const field = element as HTMLInputElement
    field.value = nextValue
    field.dispatchEvent(new Event('input', { bubbles: true }))
  }, value)
  await expect(input).toHaveValue(value)
}

function nodeAtDefaultDepth(
  flow: PublicCallflowNode | null | undefined,
  depth: number,
): PublicCallflowNode | null {
  let node = flow

  for (let index = 0; index < depth; index += 1) {
    node = node?.children?._
    if (!node) return null
  }

  return node ?? null
}

async function deleteDisposableRoute(page: Page, routeName: string): Promise<void> {
  await page.goto('/call-routing')
  const routeSearch = page.getByRole('searchbox', { name: 'Search callflows' })
  await routeSearch.fill(routeName)
  await page.getByRole('button', { name: 'Apply filters' }).click()
  const viewRoute = page.getByRole('button', { name: routeName, exact: true })
  await expect(viewRoute).toHaveCount(1)
  await viewRoute.click()

  const workspace = page.getByRole('region', { name: 'Callflow workspace' })
  const deleteRoute = workspace.getByRole('button', { name: 'Delete callflow' })

  if (await deleteRoute.isDisabled()) {
    await workspace.getByRole('button', { name: 'Edit callflow' }).click()
    const editRoute = page.getByRole('dialog', { name: 'Edit callflow' })
    const selectedNumbers = editRoute.getByRole('checkbox', { checked: true })

    for (let index = (await selectedNumbers.count()) - 1; index >= 0; index -= 1) {
      await selectedNumbers.nth(index).uncheck()
    }

    const clearResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+$/.test(new URL(response.url()).pathname),
    )
    await editRoute.getByRole('button', { name: 'Save route' }).click()
    expect((await clearResponse).status()).toBe(200)
  }

  await deleteRoute.click()
  const confirmation = page.getByRole('dialog', { name: 'Delete this callflow?' })
  const deleteResponse = page.waitForResponse(
    (response) =>
      response.request().method() === 'DELETE' &&
      /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+$/.test(new URL(response.url()).pathname),
  )
  await confirmation.getByRole('button', { name: 'Delete callflow' }).click()
  expect((await deleteResponse).status()).toBe(204)
}

test('configures Do Not Disturb guided inline actions', async ({ page }) => {
  test.setTimeout(60_000)
  const routeName = process.env.GRID_E2E_CALL_PRIORITY_ROUTE_NAME?.trim()
  const verificationFile = process.env.GRID_E2E_DND_VERIFICATION_FILE?.trim()
  test.skip(!routeName, 'GRID_E2E_CALL_PRIORITY_ROUTE_NAME must identify a disposable route.')

  const issues = collectPageIssues(page)
  const actions = [
    { label: 'Activate Do Not Disturb', action: 'activate' },
    { label: 'Deactivate Do Not Disturb', action: 'deactivate' },
    { label: 'Toggle Do Not Disturb', action: 'toggle' },
  ] as const

  try {
    await page.goto('/call-routing')
    await page.getByRole('button', { name: routeName, exact: true }).click()

    const workspace = page.getByRole('region', { name: 'Callflow workspace' })
    await expect(workspace.getByRole('heading', { name: routeName })).toBeVisible()
    const diagram = workspace.getByRole('tree', { name: 'Callflow diagram' })
    let parentNode = diagram.getByRole('treeitem').nth(1)
    const nodeInformation = page.getByRole('dialog')
    const palette = workspace.getByRole('region', { name: 'Callflow action catalog' })
    const paletteSearch = palette.getByRole('searchbox', { name: 'Search callflow actions' })

    for (const [index, item] of actions.entries()) {
      await parentNode.click()
      await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
      await replaceInputValue(paletteSearch, item.label)
      await palette.getByRole('button', { name: `Add ${item.label}`, exact: true }).click()

      const addPanel = page.getByRole('dialog', { name: `Add ${item.label}` })
      await expect(addPanel.getByTestId('slide-over-panel')).toBeVisible()
      await expect(addPanel).toContainText('authenticated caller’s owner')
      await expect(addPanel).toContainText('does not prompt for a PIN')

      const createResponse = page.waitForResponse(
        (response) =>
          response.request().method() === 'POST' &&
          /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
            new URL(response.url()).pathname,
          ),
      )
      await addPanel.getByRole('button', { name: 'Add action' }).click()
      const created = await createResponse
      expect(created.status()).toBe(200)
      expect(created.request().postDataJSON()).toEqual({
        parent_path: Array<string>(index).fill('_'),
        branch: '_',
        module: 'do_not_disturb',
        data: { action: item.action, skip_module: false },
      })

      const createdBody = (await created.json()) as {
        data: { flow?: PublicCallflowNode | null }
      }
      const publicNode = nodeAtDefaultDepth(createdBody.data.flow, index + 1)
      expect(publicNode).toMatchObject({
        module: 'do_not_disturb',
        reference_status: 'not_applicable',
        target: null,
      })
      expect(publicNode?.settings).toEqual({ action: item.action, skip_module: false })
      expect(JSON.stringify(publicNode)).not.toContain('owner_id')
      expect(JSON.stringify(publicNode)).not.toContain('switch_resource_id')

      const dndNode = diagram.getByRole('treeitem', { name: item.label })
      parentNode = dndNode
      await dndNode.click()
      await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
      const editPanel = page.getByRole('dialog', { name: `Edit ${item.label}` })
      await editPanel.getByRole('switch', { name: 'Skip this action' }).click()
      const updateResponse = page.waitForResponse(
        (response) =>
          response.request().method() === 'PATCH' &&
          /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
            new URL(response.url()).pathname,
          ),
      )
      await editPanel.getByRole('button', { name: 'Save action' }).click()
      const updated = await updateResponse
      expect(updated.status()).toBe(200)
      expect(updated.request().postDataJSON()).toEqual({
        node_path: Array<string>(index + 1).fill('_'),
        module: 'do_not_disturb',
        data: { action: item.action, skip_module: true },
      })

      const updatedBody = (await updated.json()) as {
        data: { flow?: PublicCallflowNode | null }
      }
      expect(nodeAtDefaultDepth(updatedBody.data.flow, index + 1)?.settings).toEqual({
        action: item.action,
        skip_module: true,
      })

      await dndNode.click()
      await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
      const reopenedPanel = page.getByRole('dialog', { name: `Edit ${item.label}` })
      await expect(reopenedPanel.getByRole('switch', { name: 'Skip this action' })).toHaveAttribute(
        'aria-checked',
        'true',
      )
      await reopenedPanel.getByRole('button', { name: 'Close' }).click()
    }

    if (verificationFile) {
      await expect.poll(() => existsSync(verificationFile), { timeout: 20_000 }).toBe(true)
      const evidence = JSON.parse(readFileSync(verificationFile, 'utf8')) as {
        actions?: Array<{ action?: string; skip_module?: boolean; has_id?: boolean }>
      }
      expect(evidence.actions).toEqual([
        { action: 'activate', skip_module: true, has_id: false },
        { action: 'deactivate', skip_module: true, has_id: false },
        { action: 'toggle', skip_module: true, has_id: false },
      ])
    }

    expect(issues).toEqual([])
  } finally {
    await deleteDisposableRoute(page, routeName!)
  }
})
