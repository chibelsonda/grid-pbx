import { existsSync, readFileSync } from 'node:fs'
import process from 'node:process'

import { expect, test, type Locator, type Page } from '@playwright/test'

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

async function replaceInputValue(inputLocator: Locator, value: string): Promise<void> {
  await inputLocator.evaluate((element, nextValue) => {
    const input = element as HTMLInputElement
    input.value = nextValue
    input.dispatchEvent(new Event('input', { bubbles: true }))
  }, value)
  await expect(inputLocator).toHaveValue(value)
}

type PublicCallflowNode = {
  module?: string
  settings?: Record<string, unknown>
  children?: Record<string, PublicCallflowNode>
}

function findCallflowNodeByModule(
  node: PublicCallflowNode | null | undefined,
  module: string,
): PublicCallflowNode | null {
  if (!node) return null
  if (node.module === module) return node

  for (const child of Object.values(node.children ?? {})) {
    const match = findCallflowNodeByModule(child, module)
    if (match) return match
  }

  return null
}

function callflowNodeAtPath(
  node: PublicCallflowNode | null | undefined,
  path: string[],
): PublicCallflowNode | null {
  let current = node

  for (const branch of path) {
    current = current?.children?.[branch]
    if (!current) return null
  }

  return current ?? null
}

function defaultBranchPath(depth: number): string[] {
  return Array<string>(depth).fill('_')
}

async function deleteCallflowRoute(page: Page, routeName: string): Promise<void> {
  await page.goto('/call-routing')
  const routeSearch = page.getByRole('searchbox', { name: 'Search call routes' })
  await routeSearch.fill(routeName)
  await expect(routeSearch).toHaveValue(routeName)
  await page.getByRole('button', { name: 'Apply filters' }).click()
  const viewRoute = page.getByRole('button', { name: `View ${routeName}` })
  await expect(viewRoute).toHaveCount(1)
  await viewRoute.click()

  const workspace = page.getByRole('region', { name: 'Callflow workspace' })
  const deleteRoute = workspace.getByRole('button', { name: 'Delete route' })

  if (await deleteRoute.isDisabled()) {
    await workspace.getByRole('button', { name: 'Edit guided route' }).click()
    const editRoute = page.getByRole('dialog', { name: 'Edit guided route' })
    const selectedNumbers = editRoute.getByRole('checkbox', { checked: true })

    for (let index = (await selectedNumbers.count()) - 1; index >= 0; index--) {
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
  const confirmation = page.getByRole('dialog', { name: 'Delete this route?' })
  const deleteResponse = page.waitForResponse(
    (response) =>
      response.request().method() === 'DELETE' &&
      /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+$/.test(new URL(response.url()).pathname),
  )
  await confirmation.getByRole('button', { name: 'Delete route' }).click()
  expect((await deleteResponse).status()).toBe(204)
  await expect(page.getByRole('heading', { name: 'Call Routing', exact: true })).toBeVisible()
  await expect(page.getByText(routeName, { exact: true })).toHaveCount(0)
}

test('opens a deep UI-only callflow demo without mutating Switch', async ({ page }) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      request.url().includes('/callflows') &&
      !['GET', 'HEAD', 'OPTIONS'].includes(request.method())
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })

  await page.goto('/call-routing')
  await page.getByRole('button', { name: 'Open complex demo' }).click()
  const workspace = page.getByRole('region', { name: 'Callflow workspace' })

  await expect(
    workspace.getByRole('heading', { name: 'Complex callflow demo · UI only' }),
  ).toBeVisible()
  await expect(page.getByText('UI-only demonstration.')).toBeVisible()
  await expect(workspace.getByText('20', { exact: true }).first()).toBeVisible()
  await expect(workspace.getByText('6', { exact: true }).first()).toBeVisible()
  await expect(workspace.getByText('14', { exact: true }).first()).toBeVisible()
  await expect(
    workspace.getByRole('treeitem', { name: 'Time of Day: Office hours and holidays' }),
  ).toBeVisible()
  await expect(workspace.getByRole('treeitem', { name: 'Menu: Main IVR' })).toBeVisible()
  await expect(
    workspace.getByRole('treeitem', { name: 'Voicemail: Support overflow mailbox' }),
  ).toBeVisible()
  await expect(workspace.getByRole('treeitem', { name: 'Response' })).toHaveCount(3)

  await workspace.getByRole('treeitem', { name: 'Menu: Main IVR' }).click()
  const nodeInfo = page.getByRole('dialog', { name: 'Menu' })
  await expect(nodeInfo).toContainText('Main IVR')
  await expect(nodeInfo).toContainText('Business hours match')
  await expect(nodeInfo).toContainText('Child paths')
  await expect(nodeInfo.getByRole('button', { name: 'Edit action target' })).toHaveCount(0)
  await nodeInfo.getByRole('button', { name: 'Close node information' }).click()

  const palette = workspace.getByRole('region', { name: 'Callflow action catalog' })
  await expect(palette.getByRole('button', { name: 'Move action palette' })).toBeVisible()
  const compactAction = palette.getByRole('button', { name: 'User unavailable in read-only mode' })
  await expect(compactAction).toBeDisabled()

  const panCanvas = workspace.locator('[data-callflow-pan-canvas]')
  const panBox = await panCanvas.boundingBox()
  const panDimensions = await panCanvas.evaluate((element) => ({
    clientHeight: element.clientHeight,
    scrollHeight: element.scrollHeight,
  }))
  expect(panBox).not.toBeNull()
  expect(panDimensions.scrollHeight).toBeGreaterThan(panDimensions.clientHeight)
  await expect(panCanvas).toHaveCSS('cursor', 'grab')
  await panCanvas.evaluate((element) => {
    element.scrollTop = 0
    element.scrollLeft = 0
  })
  await panCanvas.dispatchEvent('pointerdown', {
    button: 0,
    pointerId: 41,
    pointerType: 'mouse',
    clientX: 100,
    clientY: 180,
  })
  await expect(panCanvas).toHaveCSS('cursor', 'grabbing')
  await panCanvas.dispatchEvent('pointermove', {
    pointerId: 41,
    pointerType: 'mouse',
    clientX: 100,
    clientY: 60,
  })
  await panCanvas.dispatchEvent('pointerup', { pointerId: 41, pointerType: 'mouse' })
  expect(await panCanvas.evaluate((element) => element.scrollTop)).toBeGreaterThan(0)
  await expect(panCanvas).toHaveCSS('cursor', 'grab')
  await panCanvas.evaluate((element) => {
    element.scrollTop = 0
    element.scrollLeft = 0
  })

  const paletteBox = await palette.boundingBox()
  const actionBox = await compactAction.boundingBox()
  const compactActionAppearance = await compactAction.locator(':scope > div').evaluate((card) => ({
    background: getComputedStyle(card).backgroundColor,
    border: getComputedStyle(card).borderColor,
  }))
  const nodeBoxes = await workspace.locator('button[role="treeitem"]').evaluateAll((nodes) =>
    nodes.map((node) => {
      const box = node.getBoundingClientRect()
      return { width: box.width, height: box.height }
    }),
  )
  const nodeAppearance = await workspace
    .locator('button[role="treeitem"] > div')
    .evaluateAll((cards) =>
      cards.map((card) => {
        const icon = card.querySelector('div.absolute > svg')
        return {
          background: getComputedStyle(card).backgroundColor,
          border: getComputedStyle(card).borderColor,
          icon: icon ? getComputedStyle(icon).color : null,
        }
      }),
    )
  expect(paletteBox).not.toBeNull()
  expect(actionBox).not.toBeNull()
  expect(paletteBox!.width).toBeGreaterThanOrEqual(183)
  expect(paletteBox!.width).toBeLessThanOrEqual(185)
  expect(actionBox!.height).toBeGreaterThanOrEqual(55)
  expect(actionBox!.height).toBeLessThanOrEqual(57)
  expect(nodeBoxes.length).toBeGreaterThan(10)
  expect(Math.max(...nodeBoxes.map(({ width }) => width))).toBeLessThanOrEqual(145)
  expect(Math.max(...nodeBoxes.map(({ height }) => height))).toBeLessThanOrEqual(85)
  const nodeBackgrounds = new Set(nodeAppearance.map(({ background }) => background))
  expect(nodeBackgrounds.size).toBe(1)
  expect([...nodeBackgrounds][0]).not.toBe('rgba(0, 0, 0, 0)')
  expect(await palette.evaluate((element) => getComputedStyle(element).backgroundColor)).toBe(
    [...nodeBackgrounds][0],
  )
  expect(compactActionAppearance.background).toBe([...nodeBackgrounds][0])
  expect(compactActionAppearance.border).not.toBe([...nodeBackgrounds][0])
  expect(compactActionAppearance.border).not.toBe('rgba(0, 0, 0, 0)')
  expect(new Set(nodeAppearance.map(({ border }) => border)).size).toBeGreaterThanOrEqual(4)
  expect(new Set(nodeAppearance.map(({ icon }) => icon)).size).toBeGreaterThanOrEqual(4)
  const connectorAppearance = await workspace.locator('svg.h-10.w-5').evaluateAll((arrows) =>
    arrows.map((arrow) => ({
      height: arrow.getBoundingClientRect().height,
      color: getComputedStyle(arrow).color,
      shaftWidth: arrow.querySelector('line')?.getAttribute('stroke-width'),
    })),
  )
  expect(connectorAppearance.length).toBeGreaterThan(10)
  expect(Math.min(...connectorAppearance.map(({ height }) => height))).toBeGreaterThanOrEqual(39)
  const connectorColors = new Set(connectorAppearance.map(({ color }) => color))
  expect(connectorColors.size).toBe(1)
  expect([...connectorColors][0]).toBe([...nodeBackgrounds][0])
  expect(new Set(connectorAppearance.map(({ shaftWidth }) => shaftWidth))).toEqual(new Set(['8']))
  const branchBusAppearance = await workspace
    .locator('[data-callflow-branch-bus]')
    .evaluateAll((segments) =>
      segments.map((segment) => ({
        width: segment.getBoundingClientRect().width,
        height: segment.getBoundingClientRect().height,
        color: getComputedStyle(segment).color,
        tagName: segment.tagName.toLowerCase(),
        shaftWidth: segment.querySelector('line')?.getAttribute('stroke-width'),
      })),
    )
  expect(branchBusAppearance.length).toBeGreaterThanOrEqual(3)
  expect(Math.max(...branchBusAppearance.map(({ width }) => width))).toBeGreaterThan(70)
  expect(Math.min(...branchBusAppearance.map(({ height }) => height))).toBeGreaterThanOrEqual(7)
  expect(new Set(branchBusAppearance.map(({ tagName }) => tagName))).toEqual(new Set(['svg']))
  expect(new Set(branchBusAppearance.map(({ color }) => color))).toEqual(nodeBackgrounds)
  expect(new Set(branchBusAppearance.map(({ shaftWidth }) => shaftWidth))).toEqual(new Set(['8']))
  const categoryContainer = palette.locator('[data-callflow-palette-categories]')
  await expect(categoryContainer).toHaveCSS('overflow-y', 'visible')
  let categoryToggles = palette.locator('button[aria-expanded="true"]')
  await expect(categoryToggles).toHaveCount(1)
  await palette.locator('button[aria-expanded]').nth(1).click()
  categoryToggles = palette.locator('button[aria-expanded="true"]')
  await expect(categoryToggles).toHaveCount(1)
  const advancedTitles = await palette
    .locator('button[title*=" · "]')
    .evaluateAll((actions) =>
      actions.map((action) => action.getAttribute('title')?.split(' · ')[0] ?? ''),
    )
  expect(advancedTitles).toEqual([
    'Device',
    'Distinctive Ring',
    'Callflow',
    'Page Group',
    'Set CAV',
    'Missed Call Alert',
    'Manual Presence',
    'TTS',
    'Sleep',
    'Language',
    'Group Pickup',
    'Receive Fax',
    'Pivot',
    'Collect DTMF',
    'DISA',
    'Response',
    'Conference Service',
    'Check Voicemail',
    'Fax Boxes',
    'Global Carrier',
    'Account Carrier',
    'Directory',
    'Webhook',
  ])
  await expect(workspace.getByText('1 path', { exact: true })).toHaveCount(0)
  await expect(workspace.getByText('Default branch', { exact: true })).toHaveCount(0)
  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})

test('keeps Call Forwarding capability gated without mutating Switch', async ({ page }) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      request.url().includes('/callflows') &&
      !['GET', 'HEAD', 'OPTIONS'].includes(request.method())
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })

  await page.goto('/call-routing')
  await page.getByRole('button', { name: 'Open complex demo' }).click()
  const workspace = page.getByRole('region', { name: 'Callflow workspace' })
  const palette = workspace.getByRole('region', { name: 'Callflow action catalog' })
  await palette.getByRole('button', { name: /^Call Forwarding/ }).click()

  for (const label of [
    'Enable call forwarding',
    'Disable call forwarding',
    'Update call forwarding',
  ]) {
    const title = `${label} · call_forward · Capability required`
    const action = palette.getByTitle(title, { exact: true })
    await expect(action).toBeDisabled()
    await expect(action).toHaveAttribute('title', title)
  }

  await workspace.getByRole('treeitem', { name: 'Enable call forwarding' }).click()
  const nodeInformation = page.getByRole('dialog', { name: 'Call Forward' })
  await expect(nodeInformation).toContainText('Capability required')
  await expect(nodeInformation).toContainText('unauthenticated arbitrary destination')
  await expect(nodeInformation.getByRole('button', { name: 'Edit action target' })).toHaveCount(0)

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})

test('keeps ACDC Agent search-only and capability gated without mutating Switch', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      request.url().includes('/callflows') &&
      !['GET', 'HEAD', 'OPTIONS'].includes(request.method())
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })

  await page.goto('/call-routing')
  await page.getByRole('button', { name: 'Open complex demo' }).click()
  const workspace = page.getByRole('region', { name: 'Callflow workspace' })
  const palette = workspace.getByRole('region', { name: 'Callflow action catalog' })
  await palette.getByRole('searchbox', { name: 'Search callflow actions' }).fill('agent')

  for (const label of ['Agent login', 'Agent logout', 'Pause agent', 'Resume agent']) {
    const title = `${label} · acdc_agent · Capability required`
    const action = palette.getByTitle(title, { exact: true })
    await expect(action).toBeDisabled()
    await expect(action).toHaveAttribute('title', title)
  }

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})

test('keeps Eavesdrop search-only and capability gated without mutating Switch', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      request.url().includes('/callflows') &&
      !['GET', 'HEAD', 'OPTIONS'].includes(request.method())
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })

  await page.goto('/call-routing')
  await page.getByRole('button', { name: 'Open complex demo' }).click()
  const workspace = page.getByRole('region', { name: 'Callflow workspace' })
  const palette = workspace.getByRole('region', { name: 'Callflow action catalog' })
  await palette.getByRole('searchbox', { name: 'Search callflow actions' }).fill('eavesdrop')

  for (const [label, module] of [
    ['Eavesdrop configured target', 'eavesdrop'],
    ['Eavesdrop by extension', 'eavesdrop_feature'],
  ] as const) {
    const title = `${label} · ${module} · Capability required`
    const action = palette.getByTitle(title, { exact: true })
    await expect(action).toBeDisabled()
    await expect(action).toHaveAttribute('title', title)
  }

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})

test('creates and reopens live ACDC Queue feature actions', async ({ page }) => {
  test.setTimeout(60_000)
  const routeName = process.env.GRID_E2E_ACDC_QUEUE_ROUTE_NAME?.trim()
  test.skip(!routeName, 'Set GRID_E2E_ACDC_QUEUE_ROUTE_NAME to a disposable synchronized route.')
  const configuredQueueLabel = process.env.GRID_E2E_ACDC_QUEUE_LABEL?.trim()
  const rawQueueId = process.env.GRID_E2E_ACDC_QUEUE_RAW_ID?.trim()
  const verificationFile = process.env.GRID_E2E_ACDC_QUEUE_VERIFICATION_FILE?.trim()
  const issues = collectPageIssues(page)
  let opened = false

  try {
    await page.goto('/call-routing')
    const routeSearch = page.getByRole('searchbox', { name: 'Search call routes' })
    await routeSearch.fill(routeName!)
    await page.getByRole('button', { name: 'Apply filters' }).click()
    await page.getByRole('button', { name: `View ${routeName}` }).click()
    opened = true

    const workspace = page.getByRole('region', { name: 'Callflow workspace' })
    const diagram = workspace.getByRole('tree', { name: 'Callflow diagram' })
    const palette = workspace.getByRole('region', { name: 'Callflow action catalog' })
    const paletteSearch = palette.getByRole('searchbox', { name: 'Search callflow actions' })
    const nodeInformation = page.getByRole('dialog')

    await diagram.getByRole('treeitem').nth(1).click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Queue agent login')
    await palette.getByTitle('Queue agent login · acdc_queue · Guided now').click()
    const addLogin = page.getByRole('dialog', { name: 'Add Queue agent login' })
    await expect(addLogin).toContainText('does not prompt for a PIN')
    await expect(addLogin).toContainText('never stores an agent ID')
    await addLogin.getByRole('button', { name: 'Queue' }).click()
    const queueOption = configuredQueueLabel
      ? page.getByRole('option').filter({ hasText: configuredQueueLabel })
      : page.getByRole('option').first()
    const queueLabel = (await queueOption.locator('span').first().textContent())?.trim() ?? ''
    await queueOption.click()

    const createLoginResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addLogin.getByRole('button', { name: 'Add action' }).click()
    const createdLogin = await createLoginResponse
    expect(createdLogin.status()).toBe(200)
    const loginPayload = createdLogin.request().postDataJSON()
    expect(loginPayload).toMatchObject({
      parent_path: [],
      branch: '_',
      module: 'acdc_queue',
      data: {
        action: 'login',
        queue_id: expect.stringMatching(
          /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
        ),
        skip_module: false,
      },
    })
    const createdLoginBody = (await createdLogin.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    expect(callflowNodeAtPath(createdLoginBody.data.flow, ['_'])).toMatchObject({
      module: 'acdc_queue',
      reference_status: 'resolved',
      settings: {
        action: 'login',
        queue_id: loginPayload.data.queue_id,
        queue_label: queueLabel,
        supported_configuration: true,
        skip_module: false,
      },
    })
    if (rawQueueId) expect(JSON.stringify(createdLoginBody)).not.toContain(rawQueueId)

    const loginNode = diagram.getByRole('treeitem', { name: 'Queue agent login' })
    await expect(loginNode).toContainText(queueLabel)
    await loginNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editLogin = page.getByRole('dialog', { name: 'Edit Queue agent login' })
    await editLogin.getByRole('switch', { name: 'Skip this action' }).click()
    const updateLoginResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editLogin.getByRole('button', { name: 'Save action' }).click()
    const updatedLogin = await updateLoginResponse
    expect(updatedLogin.status()).toBe(200)
    expect(updatedLogin.request().postDataJSON()).toMatchObject({
      node_path: ['_'],
      module: 'acdc_queue',
      data: {
        action: 'login',
        queue_id: loginPayload.data.queue_id,
        skip_module: true,
      },
    })

    await loginNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedLogin = page.getByRole('dialog', { name: 'Edit Queue agent login' })
    await expect(reopenedLogin.getByRole('button', { name: 'Queue' })).toContainText(queueLabel)
    await expect(reopenedLogin.getByRole('switch', { name: 'Skip this action' })).toHaveAttribute(
      'aria-checked',
      'true',
    )
    await reopenedLogin.getByRole('button', { name: 'Close' }).click()

    await loginNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Queue agent logout')
    await palette.getByTitle('Queue agent logout · acdc_queue · Guided now').click()
    const addLogout = page.getByRole('dialog', { name: 'Add Queue agent logout' })
    await addLogout.getByRole('button', { name: 'Queue' }).click()
    await page.getByRole('option').filter({ hasText: queueLabel }).click()
    await addLogout.getByRole('switch', { name: 'Skip this action' }).click()

    const createLogoutResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addLogout.getByRole('button', { name: 'Add action' }).click()
    const createdLogout = await createLogoutResponse
    expect(createdLogout.status()).toBe(200)
    expect(createdLogout.request().postDataJSON()).toMatchObject({
      parent_path: ['_'],
      branch: '_',
      module: 'acdc_queue',
      data: {
        action: 'logout',
        queue_id: loginPayload.data.queue_id,
        skip_module: true,
      },
    })
    const createdLogoutBody = (await createdLogout.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    expect(callflowNodeAtPath(createdLogoutBody.data.flow, ['_', '_'])).toMatchObject({
      module: 'acdc_queue',
      reference_status: 'resolved',
      settings: {
        action: 'logout',
        queue_id: loginPayload.data.queue_id,
        queue_label: queueLabel,
        supported_configuration: true,
        skip_module: true,
      },
    })
    if (rawQueueId) expect(JSON.stringify(createdLogoutBody)).not.toContain(rawQueueId)

    const logoutNode = diagram.getByRole('treeitem', { name: 'Queue agent logout' })
    await logoutNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedLogout = page.getByRole('dialog', { name: 'Edit Queue agent logout' })
    await expect(reopenedLogout.getByRole('button', { name: 'Queue' })).toContainText(queueLabel)
    await expect(reopenedLogout.getByRole('switch', { name: 'Skip this action' })).toHaveAttribute(
      'aria-checked',
      'true',
    )
    await reopenedLogout.getByRole('button', { name: 'Close' }).click()

    if (verificationFile) {
      await expect.poll(() => existsSync(verificationFile), { timeout: 120_000 }).toBe(true)
      const rawEvidence = JSON.parse(readFileSync(verificationFile, 'utf8')) as {
        actions?: Array<{ action?: string; id?: string; skip_module?: boolean }>
      }
      expect(rawEvidence.actions).toEqual([
        { action: 'login', id: rawQueueId, skip_module: true },
        { action: 'logout', id: rawQueueId, skip_module: true },
      ])
    }

    expect(issues).toEqual([])
  } finally {
    if (opened) await deleteCallflowRoute(page, routeName!)
  }
})

test('classifies every installed palette action without planned gaps', async ({ page }) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      request.url().includes('/callflows') &&
      !['GET', 'HEAD', 'OPTIONS'].includes(request.method())
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })

  await page.goto('/call-routing')
  await page.getByRole('button', { name: 'Open complex demo' }).click()
  const workspace = page.getByRole('region', { name: 'Callflow workspace' })
  const palette = workspace.getByRole('region', { name: 'Callflow action catalog' })
  const categories = palette.locator('button[aria-expanded]')
  const actionTitles: string[] = []

  await expect(categories).toHaveCount(9)
  for (let index = 0; index < (await categories.count()); index += 1) {
    const category = categories.nth(index)
    if ((await category.getAttribute('aria-expanded')) !== 'true') await category.click()
    await expect(category).toHaveAttribute('aria-expanded', 'true')

    const visibleActions = palette.locator(
      'button[title$="Guided now"]:visible, button[title$="Capability required"]:visible, button[title$="Visual editor planned"]:visible',
    )
    const visibleTitles = await visibleActions.evaluateAll((actions) =>
      actions.map((action) => action.getAttribute('title') ?? ''),
    )
    actionTitles.push(...visibleTitles)

    for (const title of visibleTitles.filter((value) => value.endsWith('Capability required'))) {
      await expect(palette.getByTitle(title, { exact: true })).toBeDisabled()
    }
  }

  expect(new Set(actionTitles).size).toBe(49)
  expect(actionTitles.filter((title) => title.endsWith('Guided now'))).toHaveLength(40)
  expect(actionTitles.filter((title) => title.endsWith('Capability required'))).toHaveLength(9)
  expect(actionTitles.filter((title) => title.endsWith('Visual editor planned'))).toEqual([])
  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})

test('creates, edits, branches, and removes live guided inline actions', async ({ page }) => {
  test.setTimeout(90_000)
  const issues = collectPageIssues(page)
  const suffix = Date.now().toString().slice(-8)
  const seededRouteName = process.env.GRID_E2E_CALL_PRIORITY_ROUTE_NAME?.trim()
  const routeName = seededRouteName || `E2E Call Priority ${suffix}`
  let created = false

  try {
    await page.goto('/call-routing')
    await expect(page.getByRole('heading', { name: 'Call Routing', exact: true })).toBeVisible()
    if (seededRouteName) {
      await page.getByRole('button', { name: `View ${routeName}` }).click()
      created = true
    } else {
      const editorResponse = page.waitForResponse(
        (response) =>
          response.request().method() === 'GET' &&
          /\/api\/v1\/accounts\/[^/]+\/callflows\/editor$/.test(new URL(response.url()).pathname),
      )
      await page.getByRole('button', { name: 'Create route' }).click()
      const editor = (await (await editorResponse).json()) as {
        data: {
          phone_numbers: Array<{ id: string; number: string; available: boolean }>
          destination_types: Array<{ value: string }>
          destinations: Record<string, Array<{ id: string }>>
        }
      }
      const availableNumber = editor.data.phone_numbers.find(({ available }) => available)
      const availableDestination = editor.data.destination_types.find(
        ({ value }) => (editor.data.destinations[value]?.length ?? 0) > 0,
      )

      test.skip(
        !availableNumber || !availableDestination,
        'The connected account needs one unassigned phone number and one projected destination.',
      )

      const createPanel = page.getByRole('dialog', { name: 'Create call route' })
      await createPanel.getByLabel('Route name').fill(routeName)
      await createPanel.getByRole('checkbox', { name: availableNumber!.number }).check()
      const createResponse = page.waitForResponse(
        (response) =>
          response.request().method() === 'POST' &&
          /\/api\/v1\/accounts\/[^/]+\/callflows$/.test(new URL(response.url()).pathname),
      )
      await createPanel.getByRole('button', { name: 'Create route' }).click()
      expect((await createResponse).status()).toBe(201)
      created = true
    }

    const workspace = page.getByRole('region', { name: 'Callflow workspace' })
    await expect(workspace.getByRole('heading', { name: routeName })).toBeVisible()
    const diagram = workspace.getByRole('tree', { name: 'Callflow diagram' })
    await diagram.getByRole('treeitem').nth(1).click()
    const nodeInformation = page.getByRole('dialog')
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await expect(nodeInformation).toBeHidden()

    const palette = workspace.getByRole('region', { name: 'Callflow action catalog' })
    await palette.getByRole('button', { name: /^Advanced/ }).click()
    const paletteSearch = palette.getByRole('searchbox', { name: 'Search callflow actions' })
    await replaceInputValue(paletteSearch, 'Branch by Call Priority')
    await palette.getByTitle('Branch by Call Priority · branch_variable · Guided now').click()
    const addPriority = page.getByRole('dialog', { name: 'Add Branch by Call Priority' })
    await expect(addPriority.getByRole('textbox', { name: 'Variable' })).toBeDisabled()
    await expect(addPriority.getByRole('textbox', { name: 'Scope' })).toBeDisabled()
    const createPriorityResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addPriority.getByRole('button', { name: 'Add action' }).click()
    const createdPriority = await createPriorityResponse
    expect(createdPriority.status()).toBe(200)
    expect(createdPriority.request().postDataJSON()).toMatchObject({
      module: 'branch_variable',
      data: {
        variable: 'call_priority',
        scope: 'custom_channel_vars',
        skip_module: false,
      },
    })

    const priorityNode = diagram.getByRole('treeitem', { name: 'Branch by Call Priority' })
    await expect(priorityNode).toBeVisible()
    await priorityNode.click()
    await page.getByRole('dialog').getByRole('button', { name: 'Edit action target' }).click()
    const editPriority = page.getByRole('dialog', { name: 'Edit Branch by Call Priority' })
    await editPriority.getByRole('switch', { name: 'Skip this action' }).click()
    const updatePriorityResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editPriority.getByRole('button', { name: 'Save action' }).click()
    const updatedPriority = await updatePriorityResponse
    expect(updatedPriority.status()).toBe(200)
    expect(updatedPriority.request().postDataJSON()).toMatchObject({
      node_path: ['_'],
      module: 'branch_variable',
      data: { skip_module: true },
    })
    await expect(editPriority).toBeHidden()

    await priorityNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await expect(nodeInformation).toBeHidden()
    await replaceInputValue(paletteSearch, 'Hangup')
    await palette.getByTitle('Hangup · hangup · Guided now').click()
    const addHangup = page.getByRole('dialog', { name: 'Add Hangup' })
    const priorityBranchOption = page.getByRole('option', { name: /^Priority 42\b/ })
    await expect(async () => {
      if (!(await priorityBranchOption.isVisible())) {
        await addHangup.getByRole('button', { name: 'Parent branch' }).click()
      }
      await expect(priorityBranchOption).toBeVisible()
    }).toPass()
    await priorityBranchOption.click()
    const createBranchResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addHangup.getByRole('button', { name: 'Add action' }).click()
    const createdBranch = await createBranchResponse
    expect(createdBranch.status()).toBe(200)
    expect(createdBranch.request().postDataJSON()).toMatchObject({
      parent_path: ['_'],
      branch: '42',
      module: 'hangup',
    })
    await expect(workspace.getByText('Priority 42', { exact: true })).toBeVisible()
    await expect(diagram.getByRole('treeitem', { name: 'Hangup' })).toBeVisible()

    await priorityNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Branch Bnumber')
    await palette.getByTitle('Branch Bnumber · branch_bnumber · Guided now').click()
    const addCapturedNumber = page.getByRole('dialog', { name: 'Add Branch Bnumber' })
    await addCapturedNumber.getByRole('switch', { name: 'Hunt for a matching callflow' }).click()
    await addCapturedNumber.getByLabel('Allowed-number pattern').fill('^1\\d{3}$')
    const createCapturedNumberResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addCapturedNumber.getByRole('button', { name: 'Add action' }).click()
    const createdCapturedNumber = await createCapturedNumberResponse
    expect(createdCapturedNumber.status()).toBe(200)
    expect(createdCapturedNumber.request().postDataJSON()).toMatchObject({
      parent_path: ['_'],
      branch: '_',
      module: 'branch_bnumber',
      data: { hunt: true, hunt_allow: '^1\\d{3}$', hunt_deny: null, skip_module: false },
    })

    const capturedNumberNode = diagram.getByRole('treeitem', { name: 'Branch Bnumber' })
    await capturedNumberNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editCapturedNumber = page.getByRole('dialog', { name: 'Edit Branch Bnumber' })
    await editCapturedNumber.getByRole('switch', { name: 'Hunt for a matching callflow' }).click()
    const updateCapturedNumberResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editCapturedNumber.getByRole('button', { name: 'Save action' }).click()
    const updatedCapturedNumber = await updateCapturedNumberResponse
    expect(updatedCapturedNumber.status()).toBe(200)
    expect(updatedCapturedNumber.request().postDataJSON()).toMatchObject({
      node_path: ['_', '_'],
      module: 'branch_bnumber',
      data: { hunt: false, hunt_allow: null, hunt_deny: null, skip_module: false },
    })

    await capturedNumberNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Hangup')
    await palette.getByTitle('Hangup · hangup · Guided now').click()
    const addCapturedHangup = page.getByRole('dialog', { name: 'Add Hangup' })
    await addCapturedHangup.getByLabel('Captured number branch').fill('1000')
    const createCapturedBranchResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addCapturedHangup.getByRole('button', { name: 'Add action' }).click()
    const createdCapturedBranch = await createCapturedBranchResponse
    expect(createdCapturedBranch.status()).toBe(200)
    expect(createdCapturedBranch.request().postDataJSON()).toMatchObject({
      parent_path: ['_', '_'],
      branch: '1000',
      module: 'hangup',
    })
    await expect(workspace.getByText('Captured number 1000', { exact: true })).toBeVisible()

    await capturedNumberNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Set CAV')
    await palette.getByTitle('Set CAV · set_variables · Guided now').click()
    const addSetCav = page.getByRole('dialog', { name: 'Add Set CAV' })
    await addSetCav.getByRole('button', { name: 'Add variable' }).click()
    await addSetCav.getByRole('button', { name: 'Add variable' }).click()
    await addSetCav.getByLabel('Variable 1 name').fill('gridpbx_test')
    await addSetCav.getByLabel('Variable 1 value').fill('created')
    await addSetCav.getByLabel('Variable 2 name').fill('flow_stage')
    await addSetCav.getByLabel('Variable 2 value').fill('verification')
    await addSetCav.getByRole('switch', { name: 'Export to future bridged legs' }).click()
    const createSetCavResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addSetCav.getByRole('button', { name: 'Add action' }).click()
    const createdSetCav = await createSetCavResponse
    expect(createdSetCav.status()).toBe(200)
    expect(createdSetCav.request().postDataJSON()).toMatchObject({
      parent_path: ['_', '_'],
      branch: '_',
      module: 'set_variables',
      data: {
        custom_application_vars: {
          gridpbx_test: 'created',
          flow_stage: 'verification',
        },
        export: true,
        skip_module: false,
      },
    })

    const setCavNode = diagram.getByRole('treeitem', { name: 'Set CAV' })
    await expect(setCavNode).toBeVisible()
    await setCavNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editSetCav = page.getByRole('dialog', { name: 'Edit Set CAV' })
    await expect(editSetCav.getByLabel('Variable 1 name')).toHaveValue('gridpbx_test')
    await expect(editSetCav.getByLabel('Variable 2 name')).toHaveValue('flow_stage')
    await replaceInputValue(editSetCav.getByLabel('Variable 1 value'), 'updated')
    await editSetCav.getByRole('button', { name: 'Remove variable 2' }).click()
    await editSetCav.getByRole('switch', { name: 'Export to future bridged legs' }).click()
    const updateSetCavResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editSetCav.getByRole('button', { name: 'Save action' }).click()
    const updatedSetCav = await updateSetCavResponse
    expect(updatedSetCav.status()).toBe(200)
    expect(updatedSetCav.request().postDataJSON()).toMatchObject({
      node_path: ['_', '_', '_'],
      module: 'set_variables',
      data: {
        custom_application_vars: { gridpbx_test: 'updated' },
        export: false,
        skip_module: false,
      },
    })
    await expect(editSetCav).toBeHidden()

    await setCavNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedSetCav = page.getByRole('dialog', { name: 'Edit Set CAV' })
    await expect(reopenedSetCav.getByLabel('Variable 1 name')).toHaveValue('gridpbx_test')
    await expect(reopenedSetCav.getByLabel('Variable 1 value')).toHaveValue('updated')
    await expect(reopenedSetCav.getByLabel('Variable 2 name')).toHaveCount(0)
    await reopenedSetCav.getByRole('button', { name: 'Close' }).click()

    await setCavNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Manual Presence')
    await palette.getByTitle('Manual Presence · manual_presence · Guided now').click()
    const addManualPresence = page.getByRole('dialog', { name: 'Add Manual Presence' })
    await expect(addManualPresence.getByRole('button', { name: 'Presence status' })).toContainText(
      'Busy',
    )
    await replaceInputValue(addManualPresence.getByLabel('Presence ID'), '1001')
    const createManualPresenceResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addManualPresence.getByRole('button', { name: 'Add action' }).click()
    const createdManualPresence = await createManualPresenceResponse
    expect(createdManualPresence.status()).toBe(200)
    expect(createdManualPresence.request().postDataJSON()).toMatchObject({
      parent_path: ['_', '_', '_'],
      branch: '_',
      module: 'manual_presence',
      data: { presence_id: '1001', status: 'busy', skip_module: false },
    })

    const manualPresenceNode = diagram.getByRole('treeitem', { name: 'Manual Presence' })
    await expect(manualPresenceNode).toBeVisible()
    await manualPresenceNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editManualPresence = page.getByRole('dialog', { name: 'Edit Manual Presence' })
    await expect(editManualPresence.getByLabel('Presence ID')).toHaveValue('1001')
    await replaceInputValue(editManualPresence.getByLabel('Presence ID'), '1001@example.com')
    await editManualPresence.getByRole('button', { name: 'Presence status' }).click()
    await page.getByRole('option', { name: 'Idle', exact: true }).click()
    await editManualPresence.getByRole('switch', { name: 'Skip this action' }).click()
    const updateManualPresenceResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editManualPresence.getByRole('button', { name: 'Save action' }).click()
    const updatedManualPresence = await updateManualPresenceResponse
    expect(updatedManualPresence.status()).toBe(200)
    expect(updatedManualPresence.request().postDataJSON()).toMatchObject({
      node_path: ['_', '_', '_', '_'],
      module: 'manual_presence',
      data: { presence_id: '1001@example.com', status: 'idle', skip_module: true },
    })
    await expect(editManualPresence).toBeHidden()

    await manualPresenceNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedManualPresence = page.getByRole('dialog', { name: 'Edit Manual Presence' })
    await expect(reopenedManualPresence.getByLabel('Presence ID')).toHaveValue('1001@example.com')
    await expect(
      reopenedManualPresence.getByRole('button', { name: 'Presence status' }),
    ).toContainText('Idle')
    await expect(
      reopenedManualPresence.getByRole('switch', { name: 'Skip this action' }),
    ).toHaveAttribute('aria-checked', 'true')
    await reopenedManualPresence.getByRole('button', { name: 'Close' }).click()

    await manualPresenceNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Group Pickup')
    await palette.getByTitle('Group Pickup · group_pickup · Guided now').click()
    const addGroupPickup = page.getByRole('dialog', { name: 'Add Group Pickup' })
    await addGroupPickup.getByRole('button', { name: 'Pickup target' }).click()
    const extensionOption = page
      .getByRole('option')
      .filter({ hasText: /Extension/ })
      .first()
    const extensionLabel =
      (await extensionOption.locator('span').first().textContent())?.trim() ?? ''
    await extensionOption.click()
    const createGroupPickupResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addGroupPickup.getByRole('button', { name: 'Add action' }).click()
    const createdGroupPickup = await createGroupPickupResponse
    expect(createdGroupPickup.status()).toBe(200)
    const createdGroupPickupPayload = createdGroupPickup.request().postDataJSON()
    expect(createdGroupPickupPayload).toMatchObject({
      parent_path: ['_', '_', '_', '_'],
      branch: '_',
      module: 'group_pickup',
      data: {
        target_type: 'extension',
        target_id: expect.stringMatching(
          /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
        ),
        skip_module: false,
      },
    })

    const groupPickupNode = diagram.getByRole('treeitem', { name: 'Group Pickup' })
    await expect(groupPickupNode).toContainText(extensionLabel)
    await groupPickupNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editGroupPickup = page.getByRole('dialog', { name: 'Edit Group Pickup' })
    await expect(editGroupPickup.getByRole('button', { name: 'Pickup target' })).toContainText(
      extensionLabel,
    )
    await editGroupPickup.getByRole('button', { name: 'Pickup target' }).click()
    const deviceOption = page
      .getByRole('option')
      .filter({ hasText: /Device/ })
      .first()
    const deviceLabel = (await deviceOption.locator('span').first().textContent())?.trim() ?? ''
    await deviceOption.click()
    await editGroupPickup.getByRole('switch', { name: 'Skip this action' }).click()
    const updateGroupPickupResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editGroupPickup.getByRole('button', { name: 'Save action' }).click()
    const updatedGroupPickup = await updateGroupPickupResponse
    expect(updatedGroupPickup.status()).toBe(200)
    const updatedGroupPickupPayload = updatedGroupPickup.request().postDataJSON()
    expect(updatedGroupPickupPayload).toMatchObject({
      node_path: ['_', '_', '_', '_', '_'],
      module: 'group_pickup',
      data: {
        target_type: 'device',
        target_id: expect.stringMatching(
          /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
        ),
        skip_module: true,
      },
    })
    expect(updatedGroupPickupPayload.data.target_id).not.toBe(
      createdGroupPickupPayload.data.target_id,
    )
    await expect(editGroupPickup).toBeHidden()

    await groupPickupNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedGroupPickup = page.getByRole('dialog', { name: 'Edit Group Pickup' })
    await expect(reopenedGroupPickup.getByRole('button', { name: 'Pickup target' })).toContainText(
      deviceLabel,
    )
    await expect(
      reopenedGroupPickup.getByRole('switch', { name: 'Skip this action' }),
    ).toHaveAttribute('aria-checked', 'true')
    await reopenedGroupPickup.getByRole('button', { name: 'Close' }).click()

    await groupPickupNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Receive Fax')
    await palette.getByTitle('Receive Fax · receive_fax · Guided now').click()
    const addReceiveFax = page.getByRole('dialog', { name: 'Add Receive Fax' })
    await addReceiveFax.getByRole('button', { name: 'Fax owner' }).click()
    const faxOwnerOption = page.getByRole('option').first()
    const faxOwnerLabel = (await faxOwnerOption.locator('span').first().textContent())?.trim() ?? ''
    await faxOwnerOption.click()
    await addReceiveFax.getByRole('button', { name: 'T.38 negotiation' }).click()
    await page.getByRole('option', { name: /^Automatic/ }).click()
    const createReceiveFaxResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addReceiveFax.getByRole('button', { name: 'Add action' }).click()
    const createdReceiveFax = await createReceiveFaxResponse
    expect(createdReceiveFax.status()).toBe(200)
    const createdReceiveFaxPayload = createdReceiveFax.request().postDataJSON()
    expect(createdReceiveFaxPayload).toMatchObject({
      parent_path: ['_', '_', '_', '_', '_'],
      branch: '_',
      module: 'receive_fax',
      data: {
        owner_id: expect.stringMatching(
          /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
        ),
        fax_option: 'auto',
        skip_module: false,
      },
    })
    const createdReceiveFaxBody = (await createdReceiveFax.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    const createdReceiveFaxNode = findCallflowNodeByModule(
      createdReceiveFaxBody.data.flow,
      'receive_fax',
    )
    expect(createdReceiveFaxNode?.settings?.owner_id).toBe(createdReceiveFaxPayload.data.owner_id)

    const receiveFaxRawOwnerId = process.env.GRID_E2E_RECEIVE_FAX_RAW_OWNER_ID?.trim()
    const receiveFaxPrivateMarker = process.env.GRID_E2E_RECEIVE_FAX_PRIVATE_MARKER?.trim()
    const receiveFaxInjectionFile = process.env.GRID_E2E_RECEIVE_FAX_INJECTION_FILE?.trim()
    if (receiveFaxRawOwnerId) {
      expect(JSON.stringify(createdReceiveFaxBody)).not.toContain(receiveFaxRawOwnerId)
    }
    if (receiveFaxPrivateMarker) {
      expect(JSON.stringify(createdReceiveFaxBody)).not.toContain(receiveFaxPrivateMarker)
    }
    if (receiveFaxInjectionFile) {
      await expect.poll(() => existsSync(receiveFaxInjectionFile), { timeout: 20_000 }).toBe(true)
    }

    const receiveFaxNode = diagram.getByRole('treeitem', { name: 'Receive Fax' })
    await expect(receiveFaxNode).toContainText(faxOwnerLabel)
    await receiveFaxNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editReceiveFax = page.getByRole('dialog', { name: 'Edit Receive Fax' })
    await expect(editReceiveFax.getByRole('button', { name: 'Fax owner' })).toContainText(
      faxOwnerLabel,
    )
    await expect(editReceiveFax.getByRole('button', { name: 'T.38 negotiation' })).toContainText(
      'Automatic',
    )
    await editReceiveFax.getByRole('button', { name: 'T.38 negotiation' }).click()
    await page.getByRole('option', { name: /^Enabled/ }).click()
    await editReceiveFax.getByRole('switch', { name: 'Skip this action' }).click()
    const updateReceiveFaxResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editReceiveFax.getByRole('button', { name: 'Save action' }).click()
    const updatedReceiveFax = await updateReceiveFaxResponse
    expect(updatedReceiveFax.status()).toBe(200)
    expect(updatedReceiveFax.request().postDataJSON()).toMatchObject({
      node_path: ['_', '_', '_', '_', '_', '_'],
      module: 'receive_fax',
      data: { fax_option: true, skip_module: true },
    })
    const updatedReceiveFaxBody = (await updatedReceiveFax.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    const updatedReceiveFaxNode = findCallflowNodeByModule(
      updatedReceiveFaxBody.data.flow,
      'receive_fax',
    )
    expect(updatedReceiveFaxNode?.settings).toMatchObject({
      owner_id: createdReceiveFaxPayload.data.owner_id,
      fax_option: true,
      skip_module: true,
    })
    if (receiveFaxRawOwnerId) {
      expect(JSON.stringify(updatedReceiveFaxBody)).not.toContain(receiveFaxRawOwnerId)
    }
    if (receiveFaxPrivateMarker) {
      expect(JSON.stringify(updatedReceiveFaxBody)).not.toContain(receiveFaxPrivateMarker)
    }
    await expect(editReceiveFax).toBeHidden()

    await receiveFaxNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedReceiveFax = page.getByRole('dialog', { name: 'Edit Receive Fax' })
    await expect(reopenedReceiveFax.getByRole('button', { name: 'Fax owner' })).toContainText(
      faxOwnerLabel,
    )
    await expect(
      reopenedReceiveFax.getByRole('button', { name: 'T.38 negotiation' }),
    ).toContainText('Enabled')
    await expect(
      reopenedReceiveFax.getByRole('switch', { name: 'Skip this action' }),
    ).toHaveAttribute('aria-checked', 'true')
    await reopenedReceiveFax.getByRole('button', { name: 'Close' }).click()

    await receiveFaxNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Conference Service')
    await palette.getByTitle('Conference Service · conference · Guided now').click()
    const addConferenceService = page.getByRole('dialog', { name: 'Add Conference Service' })
    await expect(addConferenceService).toContainText(
      'does not store or expose a conference resource ID',
    )
    const createConferenceServiceResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addConferenceService.getByRole('button', { name: 'Add action' }).click()
    const createdConferenceService = await createConferenceServiceResponse
    expect(createdConferenceService.status()).toBe(200)
    expect(createdConferenceService.request().postDataJSON()).toMatchObject({
      parent_path: ['_', '_', '_', '_', '_', '_'],
      branch: '_',
      module: 'conference',
      data: { service_mode: true, skip_module: false },
    })
    const createdConferenceServiceBody = (await createdConferenceService.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    const createdConferenceServiceNode = findCallflowNodeByModule(
      createdConferenceServiceBody.data.flow,
      'conference',
    )
    expect(createdConferenceServiceNode).toMatchObject({
      target: null,
      reference_status: 'not_applicable',
      settings: { service_mode: true, skip_module: false },
    })

    const conferenceServicePrivateMarker =
      process.env.GRID_E2E_CONFERENCE_SERVICE_PRIVATE_MARKER?.trim()
    const conferenceServiceInjectionFile =
      process.env.GRID_E2E_CONFERENCE_SERVICE_INJECTION_FILE?.trim()
    if (conferenceServicePrivateMarker) {
      expect(JSON.stringify(createdConferenceServiceBody)).not.toContain(
        conferenceServicePrivateMarker,
      )
    }
    if (conferenceServiceInjectionFile) {
      await expect
        .poll(() => existsSync(conferenceServiceInjectionFile), { timeout: 20_000 })
        .toBe(true)
    }

    const conferenceServiceNode = diagram.getByRole('treeitem', {
      name: 'Conference Service',
    })
    await conferenceServiceNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editConferenceService = page.getByRole('dialog', { name: 'Edit Conference Service' })
    await expect(editConferenceService).toContainText(
      'does not store or expose a conference resource ID',
    )
    await editConferenceService.getByRole('switch', { name: 'Skip this action' }).click()
    const updateConferenceServiceResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editConferenceService.getByRole('button', { name: 'Save action' }).click()
    const updatedConferenceService = await updateConferenceServiceResponse
    expect(updatedConferenceService.status()).toBe(200)
    expect(updatedConferenceService.request().postDataJSON()).toMatchObject({
      node_path: ['_', '_', '_', '_', '_', '_', '_'],
      module: 'conference',
      data: { service_mode: true, skip_module: true },
    })
    const updatedConferenceServiceBody = (await updatedConferenceService.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    expect(
      findCallflowNodeByModule(updatedConferenceServiceBody.data.flow, 'conference'),
    ).toMatchObject({
      target: null,
      reference_status: 'not_applicable',
      settings: { service_mode: true, skip_module: true },
    })
    if (conferenceServicePrivateMarker) {
      expect(JSON.stringify(updatedConferenceServiceBody)).not.toContain(
        conferenceServicePrivateMarker,
      )
    }
    await expect(editConferenceService).toBeHidden()

    await conferenceServiceNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedConferenceService = page.getByRole('dialog', {
      name: 'Edit Conference Service',
    })
    await expect(
      reopenedConferenceService.getByRole('switch', { name: 'Skip this action' }),
    ).toHaveAttribute('aria-checked', 'true')
    await reopenedConferenceService.getByRole('button', { name: 'Close' }).click()

    await conferenceServiceNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Check Voicemail')
    await palette.getByTitle('Check Voicemail · voicemail · Guided now').click()
    const addCheckVoicemail = page.getByRole('dialog', { name: 'Add Check Voicemail' })
    await expect(addCheckVoicemail).toContainText(
      'does not store or expose a voicemail box resource ID',
    )
    const createCheckVoicemailResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addCheckVoicemail.getByRole('button', { name: 'Add action' }).click()
    const createdCheckVoicemail = await createCheckVoicemailResponse
    expect(createdCheckVoicemail.status()).toBe(200)
    expect(createdCheckVoicemail.request().postDataJSON()).toMatchObject({
      parent_path: ['_', '_', '_', '_', '_', '_', '_'],
      branch: '_',
      module: 'voicemail',
      data: { action: 'check', skip_module: false },
    })
    const createdCheckVoicemailBody = (await createdCheckVoicemail.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    expect(
      findCallflowNodeByModule(createdCheckVoicemailBody.data.flow, 'voicemail'),
    ).toMatchObject({
      target: null,
      reference_status: 'not_applicable',
      settings: { action: 'check', skip_module: false },
    })

    let checkVoicemailPrivateMarker = process.env.GRID_E2E_CHECK_VOICEMAIL_PRIVATE_MARKER?.trim()
    const checkVoicemailInjectionFile = process.env.GRID_E2E_CHECK_VOICEMAIL_INJECTION_FILE?.trim()
    if (checkVoicemailPrivateMarker) {
      expect(JSON.stringify(createdCheckVoicemailBody)).not.toContain(checkVoicemailPrivateMarker)
    }
    if (checkVoicemailInjectionFile) {
      await expect
        .poll(() => existsSync(checkVoicemailInjectionFile), { timeout: 20_000 })
        .toBe(true)
      checkVoicemailPrivateMarker ||= readFileSync(checkVoicemailInjectionFile, 'utf8').trim()
    }

    const checkVoicemailNode = diagram.getByRole('treeitem', { name: 'Check Voicemail' })
    await checkVoicemailNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editCheckVoicemail = page.getByRole('dialog', { name: 'Edit Check Voicemail' })
    await expect(editCheckVoicemail).toContainText(
      'does not store or expose a voicemail box resource ID',
    )
    await editCheckVoicemail.getByRole('switch', { name: 'Skip this action' }).click()
    const updateCheckVoicemailResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editCheckVoicemail.getByRole('button', { name: 'Save action' }).click()
    const updatedCheckVoicemail = await updateCheckVoicemailResponse
    expect(updatedCheckVoicemail.status()).toBe(200)
    expect(updatedCheckVoicemail.request().postDataJSON()).toMatchObject({
      node_path: ['_', '_', '_', '_', '_', '_', '_', '_'],
      module: 'voicemail',
      data: { action: 'check', skip_module: true },
    })
    const updatedCheckVoicemailBody = (await updatedCheckVoicemail.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    expect(
      findCallflowNodeByModule(updatedCheckVoicemailBody.data.flow, 'voicemail'),
    ).toMatchObject({
      target: null,
      reference_status: 'not_applicable',
      settings: { action: 'check', skip_module: true },
    })
    if (checkVoicemailPrivateMarker) {
      expect(JSON.stringify(updatedCheckVoicemailBody)).not.toContain(checkVoicemailPrivateMarker)
    }
    await expect(editCheckVoicemail).toBeHidden()

    await checkVoicemailNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedCheckVoicemail = page.getByRole('dialog', {
      name: 'Edit Check Voicemail',
    })
    await expect(
      reopenedCheckVoicemail.getByRole('switch', { name: 'Skip this action' }),
    ).toHaveAttribute('aria-checked', 'true')
    await reopenedCheckVoicemail.getByRole('button', { name: 'Close' }).click()

    await checkVoicemailNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Page Group')
    await palette.getByTitle('Page Group · page_group · Guided now').click()
    const addPageGroup = page.getByRole('dialog', { name: 'Add Page Group' })
    const pageDevice = addPageGroup.getByRole('checkbox').first()
    const pageDeviceLabel = (await pageDevice.getAttribute('aria-label')) ?? ''
    const publicPageDeviceId = (await pageDevice.getAttribute('value')) ?? ''
    expect(publicPageDeviceId).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    )
    await pageDevice.check()
    const createPageGroupResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addPageGroup.getByRole('button', { name: 'Add action' }).click()
    const createdPageGroup = await createPageGroupResponse
    expect(createdPageGroup.status()).toBe(200)
    expect(createdPageGroup.request().postDataJSON()).toMatchObject({
      parent_path: ['_', '_', '_', '_', '_', '_', '_', '_'],
      branch: '_',
      module: 'page_group',
      data: {
        audio: 'one-way',
        device_ids: [publicPageDeviceId],
        skip_module: false,
      },
    })
    const createdPageGroupBody = (await createdPageGroup.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    const createdPublicPageGroupNode = findCallflowNodeByModule(
      createdPageGroupBody.data.flow,
      'page_group',
    )
    expect(createdPublicPageGroupNode).toMatchObject({
      target: null,
      reference_status: 'resolved',
      settings: {
        supported_configuration: true,
        audio: 'one-way',
        device_ids: [publicPageDeviceId],
        skip_module: false,
      },
    })
    expect(createdPublicPageGroupNode?.settings).not.toHaveProperty('weight')

    const pageGroupRawDeviceId = process.env.GRID_E2E_PAGE_GROUP_RAW_DEVICE_ID?.trim()
    const pageGroupVerificationFile = process.env.GRID_E2E_PAGE_GROUP_VERIFICATION_FILE?.trim()
    if (pageGroupRawDeviceId) {
      expect(JSON.stringify(createdPageGroupBody)).not.toContain(pageGroupRawDeviceId)
    }
    const pageGroupNode = diagram.getByRole('treeitem', { name: 'Page Group' })
    await expect(pageGroupNode).toBeVisible()
    await pageGroupNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editPageGroup = page.getByRole('dialog', { name: 'Edit Page Group' })
    await expect(editPageGroup.getByRole('checkbox', { name: pageDeviceLabel })).toBeChecked()
    await editPageGroup.getByRole('button', { name: 'Page audio' }).click()
    await page.getByRole('option', { name: /^Two-way/ }).click()
    await editPageGroup.getByRole('switch', { name: 'Skip this action' }).click()
    const updatePageGroupResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editPageGroup.getByRole('button', { name: 'Save action' }).click()
    const updatedPageGroup = await updatePageGroupResponse
    expect(updatedPageGroup.status()).toBe(200)
    expect(updatedPageGroup.request().postDataJSON()).toMatchObject({
      node_path: ['_', '_', '_', '_', '_', '_', '_', '_', '_'],
      module: 'page_group',
      data: {
        audio: 'two-way',
        device_ids: [publicPageDeviceId],
        skip_module: true,
      },
    })
    const updatedPageGroupBody = (await updatedPageGroup.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    const updatedPublicPageGroupNode = findCallflowNodeByModule(
      updatedPageGroupBody.data.flow,
      'page_group',
    )
    expect(updatedPublicPageGroupNode).toMatchObject({
      settings: {
        supported_configuration: true,
        audio: 'two-way',
        device_ids: [publicPageDeviceId],
        skip_module: true,
      },
    })
    expect(updatedPublicPageGroupNode?.settings).not.toHaveProperty('weight')
    if (pageGroupRawDeviceId) {
      expect(JSON.stringify(updatedPageGroupBody)).not.toContain(pageGroupRawDeviceId)
    }
    if (pageGroupVerificationFile) {
      await expect.poll(() => existsSync(pageGroupVerificationFile), { timeout: 20_000 }).toBe(true)
      const rawEvidence = JSON.parse(readFileSync(pageGroupVerificationFile, 'utf8')) as {
        audio?: string
        skip_module?: boolean
        endpoint_type?: string
        id?: string
        timeout?: number
        endpoint_delay?: number
        endpoint_timeout?: number
      }
      expect(rawEvidence).toMatchObject({
        audio: 'two-way',
        skip_module: true,
        endpoint_type: 'device',
        ...(pageGroupRawDeviceId ? { id: pageGroupRawDeviceId } : {}),
        timeout: 5,
        endpoint_delay: 0,
        endpoint_timeout: 20,
      })
    }
    await expect(editPageGroup).toBeHidden()

    await pageGroupNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedPageGroup = page.getByRole('dialog', { name: 'Edit Page Group' })
    await expect(reopenedPageGroup.getByRole('button', { name: 'Page audio' })).toContainText(
      'Two-way',
    )
    await expect(reopenedPageGroup.getByRole('checkbox', { name: pageDeviceLabel })).toBeChecked()
    await expect(
      reopenedPageGroup.getByRole('switch', { name: 'Skip this action' }),
    ).toHaveAttribute('aria-checked', 'true')
    await reopenedPageGroup.getByRole('button', { name: 'Close' }).click()

    await pageGroupNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Ring Group')
    await palette.getByTitle('Ring Group · ring_group · Guided now').click()
    const addRingGroup = page.getByRole('dialog', { name: 'Add Ring Group' })
    await addRingGroup.getByRole('button', { name: 'Add Ring Group device' }).click()
    await page.getByRole('option').filter({ hasText: pageDeviceLabel }).click()
    await addRingGroup.getByRole('spinbutton', { name: 'Device 1 delay' }).fill('5')
    await addRingGroup.getByRole('spinbutton', { name: 'Attempts' }).fill('2')
    const createRingGroupResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addRingGroup.getByRole('button', { name: 'Add action' }).click()
    const createdRingGroup = await createRingGroupResponse
    expect(createdRingGroup.status()).toBe(200)
    expect(createdRingGroup.request().postDataJSON()).toMatchObject({
      parent_path: ['_', '_', '_', '_', '_', '_', '_', '_', '_'],
      branch: '_',
      module: 'ring_group',
      data: {
        strategy: 'simultaneous',
        endpoints: [{ device_id: publicPageDeviceId, delay: 5, timeout: 20 }],
        repeats: 2,
        skip_module: false,
      },
    })
    const createdRingGroupBody = (await createdRingGroup.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    const createdPublicRingGroupNode = findCallflowNodeByModule(
      createdRingGroupBody.data.flow,
      'ring_group',
    )
    expect(createdPublicRingGroupNode).toMatchObject({
      target: null,
      reference_status: 'resolved',
      settings: {
        supported_configuration: true,
        strategy: 'simultaneous',
        endpoints: [{ device_id: publicPageDeviceId, delay: 5, timeout: 20 }],
        repeats: 2,
        skip_module: false,
      },
    })
    expect(createdPublicRingGroupNode?.settings).not.toHaveProperty('timeout')
    expect(createdPublicRingGroupNode?.settings).not.toHaveProperty('ringback')

    const ringGroupRawDeviceId = process.env.GRID_E2E_RING_GROUP_RAW_DEVICE_ID?.trim()
    let ringGroupPrivateMarker = process.env.GRID_E2E_RING_GROUP_PRIVATE_MARKER?.trim()
    const ringGroupInjectionFile = process.env.GRID_E2E_RING_GROUP_INJECTION_FILE?.trim()
    const ringGroupVerificationFile = process.env.GRID_E2E_RING_GROUP_VERIFICATION_FILE?.trim()
    if (ringGroupRawDeviceId) {
      expect(JSON.stringify(createdRingGroupBody)).not.toContain(ringGroupRawDeviceId)
    }
    if (ringGroupPrivateMarker) {
      expect(JSON.stringify(createdRingGroupBody)).not.toContain(ringGroupPrivateMarker)
    }
    if (ringGroupInjectionFile) {
      await expect.poll(() => existsSync(ringGroupInjectionFile), { timeout: 20_000 }).toBe(true)
      ringGroupPrivateMarker ||= readFileSync(ringGroupInjectionFile, 'utf8').trim()
    }

    const ringGroupNode = diagram.getByRole('treeitem', { name: 'Ring Group' })
    await expect(ringGroupNode).toBeVisible()
    await ringGroupNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editRingGroup = page.getByRole('dialog', { name: 'Edit Ring Group' })
    await expect(editRingGroup.getByRole('button', { name: 'Ring strategy' })).toContainText(
      'At the same time',
    )
    await expect(editRingGroup.getByRole('spinbutton', { name: 'Device 1 delay' })).toHaveValue('5')
    await editRingGroup.getByRole('button', { name: 'Ring strategy' }).click()
    await page.getByRole('option', { name: 'Weighted random order' }).click()
    await expect(editRingGroup.getByRole('spinbutton', { name: 'Device 1 delay' })).toHaveValue('0')
    await editRingGroup.getByRole('spinbutton', { name: 'Device 1 timeout' }).fill('30')
    await editRingGroup.getByRole('spinbutton', { name: 'Device 1 weight' }).fill('75')
    await editRingGroup.getByRole('spinbutton', { name: 'Attempts' }).fill('1')
    await editRingGroup.getByRole('switch', { name: 'Skip this action' }).click()
    const updateRingGroupResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editRingGroup.getByRole('button', { name: 'Save action' }).click()
    const updatedRingGroup = await updateRingGroupResponse
    expect(updatedRingGroup.status()).toBe(200)
    expect(updatedRingGroup.request().postDataJSON()).toMatchObject({
      node_path: ['_', '_', '_', '_', '_', '_', '_', '_', '_', '_'],
      module: 'ring_group',
      data: {
        strategy: 'weighted_random',
        endpoints: [{ device_id: publicPageDeviceId, delay: 0, timeout: 30, weight: 75 }],
        repeats: 1,
        skip_module: true,
      },
    })
    const updatedRingGroupBody = (await updatedRingGroup.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    const updatedPublicRingGroupNode = findCallflowNodeByModule(
      updatedRingGroupBody.data.flow,
      'ring_group',
    )
    expect(updatedPublicRingGroupNode).toMatchObject({
      settings: {
        supported_configuration: true,
        strategy: 'weighted_random',
        endpoints: [{ device_id: publicPageDeviceId, delay: 0, timeout: 30, weight: 75 }],
        repeats: 1,
        skip_module: true,
      },
    })
    expect(updatedPublicRingGroupNode?.settings).not.toHaveProperty('timeout')
    expect(updatedPublicRingGroupNode?.settings).not.toHaveProperty('ringback')
    if (ringGroupRawDeviceId) {
      expect(JSON.stringify(updatedRingGroupBody)).not.toContain(ringGroupRawDeviceId)
    }
    if (ringGroupPrivateMarker) {
      expect(JSON.stringify(updatedRingGroupBody)).not.toContain(ringGroupPrivateMarker)
    }
    if (ringGroupVerificationFile) {
      await expect.poll(() => existsSync(ringGroupVerificationFile), { timeout: 20_000 }).toBe(true)
      const rawEvidence = JSON.parse(readFileSync(ringGroupVerificationFile, 'utf8')) as {
        strategy?: string
        repeats?: number
        timeout?: number
        skip_module?: boolean
        ringback?: string
        endpoint_type?: string
        id?: string
        delay?: number
        endpoint_timeout?: number
        weight?: number
      }
      expect(rawEvidence).toMatchObject({
        strategy: 'weighted_random',
        repeats: 1,
        timeout: 30,
        skip_module: true,
        ...(ringGroupPrivateMarker ? { ringback: ringGroupPrivateMarker } : {}),
        endpoint_type: 'device',
        ...(ringGroupRawDeviceId ? { id: ringGroupRawDeviceId } : {}),
        delay: 0,
        endpoint_timeout: 30,
        weight: 75,
      })
    }
    await expect(editRingGroup).toBeHidden()

    await ringGroupNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedRingGroup = page.getByRole('dialog', { name: 'Edit Ring Group' })
    await expect(reopenedRingGroup.getByRole('button', { name: 'Ring strategy' })).toContainText(
      'Weighted random order',
    )
    await expect(reopenedRingGroup.getByRole('spinbutton', { name: 'Attempts' })).toHaveValue('1')
    await expect(reopenedRingGroup.getByRole('spinbutton', { name: 'Device 1 delay' })).toHaveValue(
      '0',
    )
    await expect(
      reopenedRingGroup.getByRole('spinbutton', { name: 'Device 1 delay' }),
    ).toBeDisabled()
    await expect(
      reopenedRingGroup.getByRole('spinbutton', { name: 'Device 1 timeout' }),
    ).toHaveValue('30')
    await expect(
      reopenedRingGroup.getByRole('spinbutton', { name: 'Device 1 weight' }),
    ).toHaveValue('75')
    await expect(
      reopenedRingGroup.getByRole('switch', { name: 'Skip this action' }),
    ).toHaveAttribute('aria-checked', 'true')
    await reopenedRingGroup.getByRole('button', { name: 'Close' }).click()

    await ringGroupNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Ring Group Login')
    await palette.getByTitle('Ring Group Login · ring_group_toggle · Guided now').click()
    const addRingGroupLogin = page.getByRole('dialog', { name: 'Add Ring Group Login' })
    await addRingGroupLogin.getByRole('button', { name: 'Ring-group callflow' }).click()
    const ringGroupToggleTargetLabel = process.env.GRID_E2E_RING_GROUP_TOGGLE_TARGET_LABEL?.trim()
    const ringGroupToggleTargetOption = ringGroupToggleTargetLabel
      ? page.getByRole('option').filter({ hasText: ringGroupToggleTargetLabel })
      : page.getByRole('option').first()
    const selectedRingGroupToggleLabel =
      (await ringGroupToggleTargetOption.locator('span').first().textContent())?.trim() ?? ''
    await ringGroupToggleTargetOption.click()
    const createRingGroupLoginResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addRingGroupLogin.getByRole('button', { name: 'Add action' }).click()
    const createdRingGroupLogin = await createRingGroupLoginResponse
    expect(createdRingGroupLogin.status()).toBe(200)
    const createdRingGroupLoginPayload = createdRingGroupLogin.request().postDataJSON()
    expect(createdRingGroupLoginPayload).toMatchObject({
      parent_path: ['_', '_', '_', '_', '_', '_', '_', '_', '_', '_'],
      branch: '_',
      module: 'ring_group_toggle',
      data: {
        action: 'login',
        callflow_id: expect.stringMatching(
          /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
        ),
        skip_module: false,
      },
    })
    const createdRingGroupLoginBody = (await createdRingGroupLogin.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    const createdPublicRingGroupLogin = findCallflowNodeByModule(
      createdRingGroupLoginBody.data.flow,
      'ring_group_toggle',
    )
    expect(createdPublicRingGroupLogin).toMatchObject({
      reference_status: 'resolved',
      settings: {
        action: 'login',
        callflow_id: createdRingGroupLoginPayload.data.callflow_id,
        supported_configuration: true,
        skip_module: false,
      },
    })
    const ringGroupToggleRawTargetId = process.env.GRID_E2E_RING_GROUP_TOGGLE_RAW_TARGET_ID?.trim()
    const ringGroupTogglePrivateMarker =
      process.env.GRID_E2E_RING_GROUP_TOGGLE_PRIVATE_MARKER?.trim()
    const ringGroupToggleInjectionFile =
      process.env.GRID_E2E_RING_GROUP_TOGGLE_INJECTION_FILE?.trim()
    const ringGroupToggleVerificationFile =
      process.env.GRID_E2E_RING_GROUP_TOGGLE_VERIFICATION_FILE?.trim()
    if (ringGroupToggleRawTargetId) {
      expect(JSON.stringify(createdRingGroupLoginBody)).not.toContain(ringGroupToggleRawTargetId)
    }
    if (ringGroupTogglePrivateMarker) {
      expect(JSON.stringify(createdRingGroupLoginBody)).not.toContain(ringGroupTogglePrivateMarker)
    }
    if (ringGroupToggleInjectionFile) {
      await expect
        .poll(() => existsSync(ringGroupToggleInjectionFile), { timeout: 20_000 })
        .toBe(true)
    }

    const ringGroupLoginNode = diagram.getByRole('treeitem', { name: 'Ring Group Login' })
    await expect(ringGroupLoginNode).toContainText(selectedRingGroupToggleLabel)
    await ringGroupLoginNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editRingGroupLogin = page.getByRole('dialog', { name: 'Edit Ring Group Login' })
    await expect(
      editRingGroupLogin.getByRole('button', { name: 'Ring-group callflow' }),
    ).toContainText(selectedRingGroupToggleLabel)
    await editRingGroupLogin.getByRole('switch', { name: 'Skip this action' }).click()
    const updateRingGroupLoginResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editRingGroupLogin.getByRole('button', { name: 'Save action' }).click()
    const updatedRingGroupLogin = await updateRingGroupLoginResponse
    expect(updatedRingGroupLogin.status()).toBe(200)
    expect(updatedRingGroupLogin.request().postDataJSON()).toMatchObject({
      node_path: ['_', '_', '_', '_', '_', '_', '_', '_', '_', '_', '_'],
      module: 'ring_group_toggle',
      data: {
        action: 'login',
        callflow_id: createdRingGroupLoginPayload.data.callflow_id,
        skip_module: true,
      },
    })
    const updatedRingGroupLoginBody = (await updatedRingGroupLogin.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    if (ringGroupToggleRawTargetId) {
      expect(JSON.stringify(updatedRingGroupLoginBody)).not.toContain(ringGroupToggleRawTargetId)
    }
    if (ringGroupTogglePrivateMarker) {
      expect(JSON.stringify(updatedRingGroupLoginBody)).not.toContain(ringGroupTogglePrivateMarker)
    }
    await expect(editRingGroupLogin).toBeHidden()

    await ringGroupLoginNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedRingGroupLogin = page.getByRole('dialog', { name: 'Edit Ring Group Login' })
    await expect(
      reopenedRingGroupLogin.getByRole('button', { name: 'Ring-group callflow' }),
    ).toContainText(selectedRingGroupToggleLabel)
    await expect(
      reopenedRingGroupLogin.getByRole('switch', { name: 'Skip this action' }),
    ).toHaveAttribute('aria-checked', 'true')
    await reopenedRingGroupLogin.getByRole('button', { name: 'Close' }).click()

    await ringGroupLoginNode.click()
    await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
    await replaceInputValue(paletteSearch, 'Ring Group Logout')
    await palette.getByTitle('Ring Group Logout · ring_group_toggle · Guided now').click()
    const addRingGroupLogout = page.getByRole('dialog', { name: 'Add Ring Group Logout' })
    await addRingGroupLogout.getByRole('button', { name: 'Ring-group callflow' }).click()
    await page.getByRole('option').filter({ hasText: selectedRingGroupToggleLabel }).click()
    const createRingGroupLogoutResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addRingGroupLogout.getByRole('button', { name: 'Add action' }).click()
    const createdRingGroupLogout = await createRingGroupLogoutResponse
    expect(createdRingGroupLogout.status()).toBe(200)
    expect(createdRingGroupLogout.request().postDataJSON()).toMatchObject({
      parent_path: ['_', '_', '_', '_', '_', '_', '_', '_', '_', '_', '_'],
      branch: '_',
      module: 'ring_group_toggle',
      data: {
        action: 'logout',
        callflow_id: createdRingGroupLoginPayload.data.callflow_id,
        skip_module: false,
      },
    })
    const createdRingGroupLogoutBody = (await createdRingGroupLogout.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    const createdPublicRingGroupLogout = findCallflowNodeByModule(
      findCallflowNodeByModule(createdRingGroupLogoutBody.data.flow, 'ring_group_toggle')?.children
        ?._,
      'ring_group_toggle',
    )
    expect(createdPublicRingGroupLogout).toMatchObject({
      reference_status: 'resolved',
      settings: {
        action: 'logout',
        callflow_id: createdRingGroupLoginPayload.data.callflow_id,
        supported_configuration: true,
        skip_module: false,
      },
    })
    if (ringGroupToggleRawTargetId) {
      expect(JSON.stringify(createdRingGroupLogoutBody)).not.toContain(ringGroupToggleRawTargetId)
    }

    const ringGroupLogoutNode = diagram.getByRole('treeitem', { name: 'Ring Group Logout' })
    await ringGroupLogoutNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const editRingGroupLogout = page.getByRole('dialog', { name: 'Edit Ring Group Logout' })
    await editRingGroupLogout.getByRole('switch', { name: 'Skip this action' }).click()
    const updateRingGroupLogoutResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editRingGroupLogout.getByRole('button', { name: 'Save action' }).click()
    const updatedRingGroupLogout = await updateRingGroupLogoutResponse
    expect(updatedRingGroupLogout.status()).toBe(200)
    expect(updatedRingGroupLogout.request().postDataJSON()).toMatchObject({
      node_path: ['_', '_', '_', '_', '_', '_', '_', '_', '_', '_', '_', '_'],
      module: 'ring_group_toggle',
      data: {
        action: 'logout',
        callflow_id: createdRingGroupLoginPayload.data.callflow_id,
        skip_module: true,
      },
    })
    const updatedRingGroupLogoutBody = (await updatedRingGroupLogout.json()) as {
      data: { flow?: PublicCallflowNode | null }
    }
    if (ringGroupToggleRawTargetId) {
      expect(JSON.stringify(updatedRingGroupLogoutBody)).not.toContain(ringGroupToggleRawTargetId)
    }
    if (ringGroupTogglePrivateMarker) {
      expect(JSON.stringify(updatedRingGroupLogoutBody)).not.toContain(ringGroupTogglePrivateMarker)
    }
    if (ringGroupToggleVerificationFile) {
      await expect
        .poll(() => existsSync(ringGroupToggleVerificationFile), { timeout: 20_000 })
        .toBe(true)
      const rawEvidence = JSON.parse(readFileSync(ringGroupToggleVerificationFile, 'utf8')) as {
        login_action?: string
        login_callflow_id?: string
        login_skip_module?: boolean
        login_private_marker?: string
        logout_action?: string
        logout_callflow_id?: string
        logout_skip_module?: boolean
      }
      expect(rawEvidence).toMatchObject({
        login_action: 'login',
        ...(ringGroupToggleRawTargetId ? { login_callflow_id: ringGroupToggleRawTargetId } : {}),
        login_skip_module: true,
        ...(ringGroupTogglePrivateMarker
          ? { login_private_marker: ringGroupTogglePrivateMarker }
          : {}),
        logout_action: 'logout',
        ...(ringGroupToggleRawTargetId ? { logout_callflow_id: ringGroupToggleRawTargetId } : {}),
        logout_skip_module: true,
      })
    }
    await expect(editRingGroupLogout).toBeHidden()

    await ringGroupLogoutNode.click()
    await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
    const reopenedRingGroupLogout = page.getByRole('dialog', { name: 'Edit Ring Group Logout' })
    await expect(
      reopenedRingGroupLogout.getByRole('button', { name: 'Ring-group callflow' }),
    ).toContainText(selectedRingGroupToggleLabel)
    await expect(
      reopenedRingGroupLogout.getByRole('switch', { name: 'Skip this action' }),
    ).toHaveAttribute('aria-checked', 'true')
    await reopenedRingGroupLogout.getByRole('button', { name: 'Close' }).click()

    const hotdeskActions = [
      { label: 'Hot Desk login', action: 'login' },
      { label: 'Hot Desk logout', action: 'logout' },
      { label: 'Hot Desk toggle', action: 'toggle' },
    ] as const
    let hotdeskParentNode = ringGroupLogoutNode

    for (const [index, hotdeskAction] of hotdeskActions.entries()) {
      await hotdeskParentNode.click()
      await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
      await replaceInputValue(paletteSearch, hotdeskAction.label)
      await palette.getByTitle(`${hotdeskAction.label} · hotdesk · Guided now`).click()
      const addHotdesk = page.getByRole('dialog', { name: `Add ${hotdeskAction.label}` })
      await expect(addHotdesk).toContainText('caller enters the Hotdesk ID at call time')
      await expect(addHotdesk).toContainText('logout path do not prompt for it')
      const createHotdeskResponse = page.waitForResponse(
        (response) =>
          response.request().method() === 'POST' &&
          /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
            new URL(response.url()).pathname,
          ),
      )
      await addHotdesk.getByRole('button', { name: 'Add action' }).click()
      const createdHotdesk = await createHotdeskResponse
      expect(createdHotdesk.status()).toBe(200)
      const createdHotdeskPayload = createdHotdesk.request().postDataJSON()
      const parentDepth = 12 + index
      expect(createdHotdeskPayload).toEqual({
        parent_path: defaultBranchPath(parentDepth),
        branch: '_',
        module: 'hotdesk',
        data: {
          action: hotdeskAction.action,
          skip_module: false,
        },
      })
      const createdHotdeskBody = (await createdHotdesk.json()) as {
        data: { flow?: PublicCallflowNode | null }
      }
      expect(
        callflowNodeAtPath(createdHotdeskBody.data.flow, defaultBranchPath(parentDepth + 1)),
      ).toMatchObject({
        module: 'hotdesk',
        reference_status: 'not_applicable',
        target: null,
        settings: {
          action: hotdeskAction.action,
          skip_module: false,
        },
      })
      expect(
        callflowNodeAtPath(createdHotdeskBody.data.flow, defaultBranchPath(parentDepth + 1))
          ?.settings,
      ).toEqual({ action: hotdeskAction.action, skip_module: false })

      const hotdeskNode = diagram.getByRole('treeitem', { name: hotdeskAction.label })
      hotdeskParentNode = hotdeskNode
      await hotdeskNode.click()
      await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
      const editHotdesk = page.getByRole('dialog', { name: `Edit ${hotdeskAction.label}` })
      await editHotdesk.getByRole('switch', { name: 'Skip this action' }).click()
      const updateHotdeskResponse = page.waitForResponse(
        (response) =>
          response.request().method() === 'PATCH' &&
          /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
            new URL(response.url()).pathname,
          ),
      )
      await editHotdesk.getByRole('button', { name: 'Save action' }).click()
      const updatedHotdesk = await updateHotdeskResponse
      expect(updatedHotdesk.status()).toBe(200)
      expect(updatedHotdesk.request().postDataJSON()).toEqual({
        node_path: defaultBranchPath(parentDepth + 1),
        module: 'hotdesk',
        data: {
          action: hotdeskAction.action,
          skip_module: true,
        },
      })
      const updatedHotdeskBody = (await updatedHotdesk.json()) as {
        data: { flow?: PublicCallflowNode | null }
      }
      expect(
        callflowNodeAtPath(updatedHotdeskBody.data.flow, defaultBranchPath(parentDepth + 1))
          ?.settings,
      ).toEqual({ action: hotdeskAction.action, skip_module: true })
      await expect(editHotdesk).toBeHidden()

      await hotdeskNode.click()
      await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
      const reopenedHotdesk = page.getByRole('dialog', { name: `Edit ${hotdeskAction.label}` })
      await expect(
        reopenedHotdesk.getByRole('switch', { name: 'Skip this action' }),
      ).toHaveAttribute('aria-checked', 'true')
      await reopenedHotdesk.getByRole('button', { name: 'Close' }).click()
    }

    const hotdeskVerificationFile = process.env.GRID_E2E_HOTDESK_VERIFICATION_FILE?.trim()
    if (hotdeskVerificationFile) {
      await expect.poll(() => existsSync(hotdeskVerificationFile), { timeout: 20_000 }).toBe(true)
      const rawEvidence = JSON.parse(readFileSync(hotdeskVerificationFile, 'utf8')) as {
        actions?: Array<{ action?: string; skip_module?: boolean }>
      }
      expect(rawEvidence.actions).toEqual([
        { action: 'login', skip_module: true },
        { action: 'logout', skip_module: true },
        { action: 'toggle', skip_module: true },
      ])
    }

    const dndActions = [
      { label: 'Activate Do Not Disturb', action: 'activate' },
      { label: 'Deactivate Do Not Disturb', action: 'deactivate' },
      { label: 'Toggle Do Not Disturb', action: 'toggle' },
    ] as const
    let dndParentNode = hotdeskParentNode

    for (const [index, dndAction] of dndActions.entries()) {
      await dndParentNode.click()
      await nodeInformation.getByRole('button', { name: 'Close node information' }).click()
      await replaceInputValue(paletteSearch, dndAction.label)
      await palette.getByRole('button', { name: `Add ${dndAction.label}`, exact: true }).click()
      const addDnd = page.getByRole('dialog', { name: `Add ${dndAction.label}` })
      await expect(addDnd).toContainText('authenticated caller’s owner')
      await expect(addDnd).toContainText('does not prompt for a PIN')
      const createDndResponse = page.waitForResponse(
        (response) =>
          response.request().method() === 'POST' &&
          /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
            new URL(response.url()).pathname,
          ),
      )
      await addDnd.getByRole('button', { name: 'Add action' }).click()
      const createdDnd = await createDndResponse
      expect(createdDnd.status()).toBe(200)
      const createdDndPayload = createdDnd.request().postDataJSON()
      const parentDepth = 15 + index
      expect(createdDndPayload).toEqual({
        parent_path: defaultBranchPath(parentDepth),
        branch: '_',
        module: 'do_not_disturb',
        data: {
          action: dndAction.action,
          skip_module: false,
        },
      })
      const createdDndBody = (await createdDnd.json()) as {
        data: { flow?: PublicCallflowNode | null }
      }
      expect(
        callflowNodeAtPath(createdDndBody.data.flow, defaultBranchPath(parentDepth + 1)),
      ).toMatchObject({
        module: 'do_not_disturb',
        reference_status: 'not_applicable',
        target: null,
        settings: {
          action: dndAction.action,
          skip_module: false,
        },
      })
      expect(
        callflowNodeAtPath(createdDndBody.data.flow, defaultBranchPath(parentDepth + 1))?.settings,
      ).toEqual({ action: dndAction.action, skip_module: false })

      const dndNode = diagram.getByRole('treeitem', { name: dndAction.label })
      dndParentNode = dndNode
      await dndNode.click()
      await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
      const editDnd = page.getByRole('dialog', { name: `Edit ${dndAction.label}` })
      await editDnd.getByRole('switch', { name: 'Skip this action' }).click()
      const updateDndResponse = page.waitForResponse(
        (response) =>
          response.request().method() === 'PATCH' &&
          /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
            new URL(response.url()).pathname,
          ),
      )
      await editDnd.getByRole('button', { name: 'Save action' }).click()
      const updatedDnd = await updateDndResponse
      expect(updatedDnd.status()).toBe(200)
      expect(updatedDnd.request().postDataJSON()).toEqual({
        node_path: defaultBranchPath(parentDepth + 1),
        module: 'do_not_disturb',
        data: {
          action: dndAction.action,
          skip_module: true,
        },
      })
      const updatedDndBody = (await updatedDnd.json()) as {
        data: { flow?: PublicCallflowNode | null }
      }
      expect(
        callflowNodeAtPath(updatedDndBody.data.flow, defaultBranchPath(parentDepth + 1))?.settings,
      ).toEqual({ action: dndAction.action, skip_module: true })
      await expect(editDnd).toBeHidden()

      await dndNode.click()
      await nodeInformation.getByRole('button', { name: 'Edit action target' }).click()
      const reopenedDnd = page.getByRole('dialog', { name: `Edit ${dndAction.label}` })
      await expect(reopenedDnd.getByRole('switch', { name: 'Skip this action' })).toHaveAttribute(
        'aria-checked',
        'true',
      )
      await reopenedDnd.getByRole('button', { name: 'Close' }).click()
    }

    const dndVerificationFile = process.env.GRID_E2E_DND_VERIFICATION_FILE?.trim()
    if (dndVerificationFile) {
      await expect.poll(() => existsSync(dndVerificationFile), { timeout: 20_000 }).toBe(true)
      const rawEvidence = JSON.parse(readFileSync(dndVerificationFile, 'utf8')) as {
        actions?: Array<{ action?: string; skip_module?: boolean; has_id?: boolean }>
      }
      expect(rawEvidence.actions).toEqual([
        { action: 'activate', skip_module: true, has_id: false },
        { action: 'deactivate', skip_module: true, has_id: false },
        { action: 'toggle', skip_module: true, has_id: false },
      ])
    }
    expect(issues).toEqual([])
  } finally {
    if (created) await deleteCallflowRoute(page, routeName)
  }
})

test('keeps Callflow validation inline and its destination listbox inside the viewport', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.goto('/call-routing')
  await expect(page.getByRole('heading', { name: 'Call Routing', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Create route' }).click()
  await expect(page.getByRole('heading', { name: 'Create call route' })).toBeVisible()
  const dialog = page.getByRole('dialog', { name: 'Create call route' })

  await dialog.getByRole('button', { name: 'Destination type' }).click()
  const options = page.getByRole('listbox')
  await expect(options).toBeVisible()
  const box = await options.boundingBox()
  const viewport = page.viewportSize()
  expect(box).not.toBeNull()
  expect(viewport).not.toBeNull()
  expect(box!.x).toBeGreaterThanOrEqual(0)
  expect(box!.y).toBeGreaterThanOrEqual(0)
  expect(box!.x + box!.width).toBeLessThanOrEqual(viewport!.width)
  expect(box!.y + box!.height).toBeLessThanOrEqual(viewport!.height)
  await page.getByRole('option', { name: 'Extension', exact: true }).click()

  const fallbackToggle = dialog.getByRole('switch', { name: 'Use a fallback destination' })
  await expect(fallbackToggle).toBeVisible()
  await fallbackToggle.click()
  await expect(fallbackToggle).toHaveAttribute('aria-checked', 'true')
  await expect(dialog.getByRole('button', { name: 'Fallback type' })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Fallback destination' })).toBeVisible()

  await page.locator('button[type="submit"]').click()
  const name = page.getByLabel('Route name')
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a route name.')).toBeVisible()
  await expect(page.getByText('Select at least one phone number.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})

test('shows safe Menu key routes without offering the legacy hash branch', async ({ page }) => {
  const issues = collectPageIssues(page)
  await page.route('**/api/v1/accounts/*/callflows/editor', async (route) => {
    const branchKeys = ['timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*']
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          mode: 'create',
          editable: true,
          blocked_reason: null,
          fallback: { editable: true, blocked_reason: null, target: null },
          menu_branches: {
            editable: true,
            blocked_reason: null,
            branches: branchKeys.map((key) => ({
              key,
              label: key === 'timeout' ? 'Timeout' : key === '*' ? 'Star' : key,
              editable: true,
              blocked_reason: null,
              target: null,
            })),
            legacy_hash_present: false,
            unknown_branch_keys: [],
          },
          temporal_match: {
            editable: true,
            blocked_reason: null,
            target: null,
            preserved_branch_count: 0,
          },
          direct_temporal_routes: [],
          temporal_rule_sets: {},
          temporal_rules: [],
          caller_id_lists: [
            {
              id: 'dded4533-55cb-4b40-acb6-b02248532c09',
              label: 'VIP callers',
              detail: '2 entries',
            },
          ],
          destination_types: [
            { value: 'extension', label: 'Extension' },
            { value: 'menu', label: 'Menu / IVR' },
          ],
          destinations: {
            extension: [
              {
                id: '16f95ac5-243c-476a-b238-9f51108f82e1',
                label: 'Operator',
                detail: '1000',
              },
            ],
            menu: [
              {
                id: 'c48df137-8660-405d-bb64-eec23c394129',
                label: 'Main IVR',
                detail: 'Interactive voice menu',
              },
            ],
          },
          phone_numbers: [],
        },
      }),
    })
  })
  await page.goto('/call-routing')
  await page.getByRole('button', { name: 'Create route' }).click()
  const dialog = page.getByRole('dialog', { name: 'Create call route' })
  await expect(page.getByRole('heading', { name: 'Create call route' })).toBeVisible()

  await dialog.getByRole('button', { name: 'Destination type' }).click()
  const menuOption = page.getByRole('option', { name: 'Menu / IVR', exact: true })
  await menuOption.click()

  await expect(dialog.getByRole('heading', { name: 'Menu key routes' })).toBeVisible()
  await dialog.getByRole('button', { name: 'Add key route' }).click()
  const keyButton = dialog.getByRole('button', { name: 'Menu branch key 1' })
  await expect(keyButton).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Menu branch type 1' })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Menu branch destination 1' })).toBeVisible()

  await keyButton.click()
  await expect(page.getByRole('option', { name: '#', exact: true })).toHaveCount(0)
  await expect(page.getByRole('option', { name: 'Timeout', exact: true })).toBeVisible()
  await expect(page.getByRole('option', { name: 'Star', exact: true })).toBeVisible()

  const options = page.getByRole('listbox')
  const box = await options.boundingBox()
  const viewport = page.viewportSize()
  expect(box).not.toBeNull()
  expect(viewport).not.toBeNull()
  expect(box!.x + box!.width).toBeLessThanOrEqual(viewport!.width)
  expect(box!.y + box!.height).toBeLessThanOrEqual(viewport!.height)
  expect(issues).toEqual([])
})

test('shows the ordered Rule Set and one schema-correct temporal match route', async ({ page }) => {
  const issues = collectPageIssues(page)
  const ruleSetId = 'd5149b3a-a4f9-4b68-b970-d1657886e92e'
  await page.route('**/api/v1/accounts/*/callflows/editor', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          mode: 'create',
          editable: true,
          blocked_reason: null,
          fallback: { editable: true, blocked_reason: null, target: null },
          menu_branches: {
            editable: true,
            blocked_reason: null,
            branches: [],
            legacy_hash_present: false,
            unknown_branch_keys: [],
          },
          temporal_match: {
            editable: true,
            blocked_reason: null,
            target: null,
            preserved_branch_count: 0,
          },
          direct_temporal_routes: [],
          temporal_rule_sets: {
            [ruleSetId]: [
              {
                id: '24af1546-200c-4431-8f96-e05aadd75569',
                label: 'Weekdays 09:00–17:00',
                position: 0,
                resolved: true,
              },
            ],
          },
          temporal_rules: [],
          destination_types: [
            { value: 'extension', label: 'Extension' },
            { value: 'temporal_rule_set', label: 'Business Hours / Schedule' },
          ],
          destinations: {
            extension: [
              {
                id: '16f95ac5-243c-476a-b238-9f51108f82e1',
                label: 'Reception',
                detail: '1000',
              },
            ],
            temporal_rule_set: [
              { id: ruleSetId, label: 'Office hours', detail: '1 schedule rule' },
            ],
          },
          phone_numbers: [],
        },
      }),
    })
  })

  await page.goto('/call-routing')
  await page.getByRole('button', { name: 'Create route' }).click()
  const dialog = page.getByRole('dialog', { name: 'Create call route' })
  await dialog.getByRole('button', { name: 'Destination type' }).click()
  await page.getByRole('option', { name: 'Business Hours / Schedule', exact: true }).click()

  await expect(dialog.getByRole('heading', { name: 'Schedule routes' })).toBeVisible()
  await expect(dialog.getByText('Weekdays 09:00–17:00')).toBeVisible()
  await expect(dialog.getByRole('switch', { name: 'Route matching calls' })).toHaveAttribute(
    'aria-checked',
    'true',
  )
  await expect(
    dialog.getByRole('button', { name: 'Schedule match destination type' }),
  ).toBeVisible()
  await expect(
    dialog.getByRole('button', { name: 'Schedule match destination', exact: true }),
  ).toBeVisible()
  expect(issues).toEqual([])
})

test('orders direct Temporal Rules and configures one public match destination per rule', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const weekdayId = '24af1546-200c-4431-8f96-e05aadd75569'
  const holidayId = 'c927fca2-86d3-4fe8-b1e7-e575c492ad0b'
  await page.route('**/api/v1/accounts/*/callflows/editor', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          mode: 'create',
          editable: true,
          blocked_reason: null,
          fallback: { editable: true, blocked_reason: null, target: null },
          menu_branches: {
            editable: true,
            blocked_reason: null,
            branches: [],
            legacy_hash_present: false,
            unknown_branch_keys: [],
          },
          temporal_match: {
            editable: true,
            blocked_reason: null,
            target: null,
            preserved_branch_count: 0,
          },
          direct_temporal_routes: [],
          temporal_rule_sets: {},
          temporal_rules: [
            { id: weekdayId, label: 'Weekdays', detail: 'Weekly recurrence' },
            { id: holidayId, label: 'Holidays', detail: 'Yearly recurrence' },
          ],
          destination_types: [
            { value: 'extension', label: 'Extension' },
            { value: 'temporal_rules', label: 'Direct Temporal Rules' },
          ],
          destinations: {
            extension: [
              {
                id: '16f95ac5-243c-476a-b238-9f51108f82e1',
                label: 'Reception',
                detail: '1000',
              },
            ],
            temporal_rules: [],
          },
          phone_numbers: [],
        },
      }),
    })
  })

  await page.goto('/call-routing')
  await page.getByRole('button', { name: 'Create route' }).click()
  const dialog = page.getByRole('dialog', { name: 'Create call route' })
  await dialog.getByRole('button', { name: 'Destination type' }).click()
  await page.getByRole('option', { name: 'Direct Temporal Rules', exact: true }).click()

  await expect(dialog.getByLabel('Route name')).toBeVisible()
  await dialog.getByRole('checkbox', { name: 'Use Temporal Rule Weekdays' }).check()
  await dialog.getByRole('checkbox', { name: 'Use Temporal Rule Holidays' }).check()
  await expect(dialog.getByRole('heading', { name: 'Temporal Rule match routes' })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Weekdays destination type' })).toBeVisible()
  await expect(
    dialog.getByRole('button', { name: 'Weekdays destination', exact: true }),
  ).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Holidays destination type' })).toBeVisible()
  await expect(
    dialog.getByRole('button', { name: 'Holidays destination', exact: true }),
  ).toBeVisible()

  await dialog.getByRole('button', { name: 'Move Holidays up' }).click()
  const orderedRules = dialog.locator('ol li')
  await expect(orderedRules.nth(0)).toContainText('Holidays')
  await expect(orderedRules.nth(1)).toContainText('Weekdays')
  expect(issues).toEqual([])
})

test('renders a recursive visual route map without exposing preserved Switch branch keys', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  let movePayload: unknown = null
  let createNodePayload: unknown = null
  let updateNodePayload: unknown = null
  let inlineCreatePayload: unknown = null
  const callflowId = '8224c7ce-e17a-4ff5-abf6-54a502705a19'
  const callflow = {
    id: callflowId,
    name: 'Visual diagram route',
    route_type: 'phone_number',
    numbers: ['+15550001234'],
    patterns: [],
    flags: [],
    modules: ['temporal_route', 'user', 'custom_vendor'],
    root_module: 'temporal_route',
    node_count: 3,
    max_depth: 2,
    feature_code: null,
    flow: {
      module: 'temporal_route',
      target: {
        type: 'temporal_rule_set',
        id: 'd5149b3a-a4f9-4b68-b970-d1657886e92e',
        label: 'Office hours',
      },
      reference_status: 'resolved',
      branch: null,
      children: {
        rule_set: {
          module: 'user',
          target: {
            type: 'extension',
            id: '16f95ac5-243c-476a-b238-9f51108f82e1',
            label: 'Reception',
          },
          reference_status: 'resolved',
          branch: { key: 'rule_set', label: 'Schedule matches', kind: 'schedule_match' },
          children: {},
        },
        preserved_1: {
          module: 'custom_vendor',
          target: null,
          reference_status: 'not_applicable',
          branch: { key: 'preserved_1', label: 'Preserved branch 1', kind: 'preserved' },
          children: {},
        },
      },
    },
    linked_extension: null,
    phone_numbers: [
      {
        id: '1078f5f7-a8c4-4296-abf8-610612cac312',
        number: '+15550001234',
        state: 'in_service',
      },
    ],
    sync_status: 'healthy',
    last_synced_at: '2026-08-29T18:00:00+08:00',
  }

  await page.route('**/api/v1/accounts/*/callflows?*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [callflow],
        links: { first: null, last: null, prev: null, next: null },
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
          sync: { status: 'healthy', last_successful_at: null, error_message: null },
        },
      }),
    })
  })
  await page.route(`**/api/v1/accounts/*/callflows/${callflowId}`, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: callflow }),
    })
  })
  await page.route(`**/api/v1/accounts/*/callflows/${callflowId}/tree`, async (route) => {
    movePayload = route.request().postDataJSON()
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          ...callflow,
          max_depth: 2,
          flow: {
            ...callflow.flow,
            children: {
              _: {
                ...callflow.flow.children.rule_set,
                branch: { key: '_', label: 'No schedule match', kind: 'default' },
              },
              preserved_1: callflow.flow.children.preserved_1,
            },
          },
        },
      }),
    })
  })
  await page.route(`**/api/v1/accounts/*/callflows/${callflowId}/editor`, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          mode: 'update',
          editable: true,
          blocked_reason: null,
          fallback: { editable: true, blocked_reason: null, target: null },
          menu_branches: {
            editable: true,
            blocked_reason: null,
            branches: [],
            legacy_hash_present: false,
            unknown_branch_keys: [],
          },
          temporal_match: {
            editable: true,
            blocked_reason: null,
            target: null,
            preserved_branch_count: 0,
          },
          direct_temporal_routes: [],
          temporal_rule_sets: {},
          temporal_rules: [],
          caller_id_lists: [
            {
              id: 'dded4533-55cb-4b40-acb6-b02248532c09',
              label: 'VIP callers',
              detail: '2 entries',
            },
          ],
          destination_types: [
            { value: 'extension', label: 'Extension' },
            { value: 'voicemail', label: 'Voicemail' },
          ],
          destinations: {
            extension: [
              {
                id: '16f95ac5-243c-476a-b238-9f51108f82e1',
                label: 'Reception',
                detail: '1000',
              },
              {
                id: 'aa90a27d-6726-43ee-820c-bbf68008a0f6',
                label: 'Support',
                detail: '1001',
              },
            ],
            device: [],
            voicemail: [
              {
                id: '216fe383-b79f-45ee-a98e-a507ef3b2995',
                label: 'Reception mailbox',
                detail: '1000',
              },
            ],
            callflow: [],
            media: [],
            directory: [],
            group: [],
            queue: [],
            menu: [],
            conference: [],
            fax_box: [],
            temporal_rule_set: [],
            temporal_rules: [],
          },
          phone_numbers: [],
        },
      }),
    })
  })
  await page.route(`**/api/v1/accounts/*/callflows/${callflowId}/tree/nodes`, async (route) => {
    const isCreate = route.request().method() === 'POST'
    if (isCreate) createNodePayload = route.request().postDataJSON()
    else updateNodePayload = route.request().postDataJSON()

    const extensionTarget = isCreate
      ? callflow.flow.children.rule_set.target
      : {
          type: 'extension',
          id: 'aa90a27d-6726-43ee-820c-bbf68008a0f6',
          label: 'Support',
        }
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          ...callflow,
          node_count: 4,
          max_depth: 3,
          flow: {
            ...callflow.flow,
            children: {
              _: {
                ...callflow.flow.children.rule_set,
                target: extensionTarget,
                branch: { key: '_', label: 'No schedule match', kind: 'default' },
                children: {
                  _: {
                    module: 'voicemail',
                    target: {
                      type: 'voicemail',
                      id: '216fe383-b79f-45ee-a98e-a507ef3b2995',
                      label: 'Reception mailbox',
                    },
                    reference_status: 'resolved',
                    branch: { key: '_', label: 'Default branch', kind: 'default' },
                    children: {},
                  },
                },
              },
              preserved_1: callflow.flow.children.preserved_1,
            },
          },
        },
      }),
    })
  })
  await page.route(
    `**/api/v1/accounts/*/callflows/${callflowId}/tree/inline-nodes`,
    async (route) => {
      inlineCreatePayload = route.request().postDataJSON()
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            ...callflow,
            node_count: 5,
            max_depth: 4,
            flow: {
              ...callflow.flow,
              children: {
                _: {
                  ...callflow.flow.children.rule_set,
                  target: {
                    type: 'extension',
                    id: 'aa90a27d-6726-43ee-820c-bbf68008a0f6',
                    label: 'Support',
                  },
                  branch: { key: '_', label: 'No schedule match', kind: 'default' },
                  children: {
                    _: {
                      module: 'voicemail',
                      target: {
                        type: 'voicemail',
                        id: '216fe383-b79f-45ee-a98e-a507ef3b2995',
                        label: 'Reception mailbox',
                      },
                      reference_status: 'resolved',
                      branch: { key: '_', label: 'Default branch', kind: 'default' },
                      children: {
                        _: {
                          module: 'tts',
                          target: null,
                          reference_status: 'not_applicable',
                          settings: {
                            text: 'Please hold for the next available representative.',
                            voice: 'female',
                            language: null,
                            engine: null,
                            endless_playback: false,
                            terminators: ['#'],
                            skip_module: false,
                          },
                          branch: { key: '_', label: 'Default branch', kind: 'default' },
                          children: {},
                        },
                      },
                    },
                  },
                },
                preserved_1: callflow.flow.children.preserved_1,
              },
            },
          },
        }),
      })
    },
  )

  await page.goto('/call-routing')
  await page.getByRole('button', { name: 'View Visual diagram route' }).click()
  const workspace = page.getByRole('region', { name: 'Callflow workspace' })
  await expect(workspace.getByRole('heading', { name: 'Visual route map' })).toBeVisible()
  await expect(
    workspace.getByRole('treeitem', { name: 'Callflow entry: +15550001234' }),
  ).toBeVisible()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(workspace.getByText('Schedule matches', { exact: true }).last()).toBeVisible()
  await expect(workspace.getByText('Preserved branch 1', { exact: true }).last()).toBeVisible()
  await expect(workspace.getByText('Reception', { exact: true })).toBeVisible()
  await expect(workspace.getByText('switch-rule-secret')).toHaveCount(0)

  await workspace.getByRole('treeitem', { name: 'User: Reception' }).click()
  const nodeInfo = page.getByRole('dialog', { name: 'User: Reception' })
  await expect(nodeInfo).toContainText('Root / Schedule matches')
  await expect(nodeInfo).toContainText('user')
  await expect(nodeInfo).toContainText('Reception')
  await expect(nodeInfo).toContainText('Guided now')
  await nodeInfo.getByRole('button', { name: 'Close node information' }).click()
  await expect(nodeInfo).toHaveCount(0)

  const actionPalette = workspace.getByRole('region', { name: 'Callflow action catalog' })
  const paletteBeforeMove = await actionPalette.boundingBox()
  const paletteHandle = actionPalette.getByRole('button', { name: 'Move action palette' })
  const handleBox = await paletteHandle.boundingBox()
  expect(paletteBeforeMove).not.toBeNull()
  expect(handleBox).not.toBeNull()
  await paletteHandle.dispatchEvent('pointerdown', {
    button: 0,
    clientX: handleBox!.x + handleBox!.width / 2,
    clientY: handleBox!.y + handleBox!.height / 2,
  })
  await page.mouse.move(handleBox!.x - 180, handleBox!.y + 70, { steps: 8 })
  await page.locator('body').dispatchEvent('pointerup')
  await expect(actionPalette.getByRole('button', { name: 'Dock action palette' })).toBeVisible()
  const floatingPaletteBox = await actionPalette.boundingBox()
  expect(floatingPaletteBox).not.toBeNull()
  expect(floatingPaletteBox!.x).toBeLessThan(paletteBeforeMove!.x)
  await actionPalette.getByRole('button', { name: 'Dock action palette' }).click()
  await expect(actionPalette.getByRole('button', { name: 'Dock action palette' })).toHaveCount(0)

  const actionSearch = workspace.getByRole('searchbox', { name: 'Search callflow actions' })
  await actionSearch.fill('webhook')
  await expect(workspace.getByText('Webhook', { exact: true })).toBeVisible()
  await expect(workspace.getByText('Capability required', { exact: true })).toBeVisible()
  await expect(workspace.getByText('1 action', { exact: true })).toBeVisible()

  await workspace
    .getByRole('treeitem', { name: 'User: Reception' })
    .dragTo(workspace.getByRole('treeitem', { name: 'Time of Day: Office hours' }))
  await expect
    .poll(() => movePayload)
    .toEqual({
      source_path: ['rule_set'],
      destination_parent_path: [],
      destination_branch: '_',
    })
  await expect(workspace.getByText('No schedule match', { exact: true }).last()).toBeVisible()
  await expect(workspace.getByText('Schedule matches', { exact: true })).toHaveCount(0)

  await workspace.getByRole('treeitem', { name: 'User: Reception' }).click()
  await page
    .getByRole('dialog', { name: 'User: Reception' })
    .getByRole('button', { name: 'Close node information' })
    .click()
  await expect(page.getByRole('dialog', { name: 'User: Reception' })).toHaveCount(0)
  const actionSearchAfterMove = workspace.getByRole('searchbox', {
    name: 'Search callflow actions',
  })
  await actionSearchAfterMove.fill('voicemail')
  await workspace.getByRole('button', { name: 'Add Voicemail' }).click()
  const addPanel = page.getByRole('dialog', { name: 'Add Voicemail' })
  await expect(page.getByRole('heading', { name: 'Add Voicemail' })).toBeVisible()
  await expect(addPanel.getByRole('button', { name: 'Action destination' })).toContainText(
    'Reception mailbox',
  )
  await addPanel.getByRole('button', { name: 'Add action' }).click()
  await expect
    .poll(() => createNodePayload)
    .toEqual({
      parent_path: ['_'],
      branch: '_',
      destination_type: 'voicemail',
      destination_id: '216fe383-b79f-45ee-a98e-a507ef3b2995',
    })
  await expect(addPanel).toHaveCount(0)
  await expect(
    workspace.getByRole('treeitem', { name: 'Voicemail: Reception mailbox' }),
  ).toBeVisible()

  await workspace.getByRole('treeitem', { name: 'User: Reception' }).click()
  await page
    .getByRole('dialog', { name: 'User: Reception' })
    .getByRole('button', { name: 'Edit action target' })
    .click()
  const editPanel = page.getByRole('dialog', { name: 'Edit User' })
  await editPanel.getByRole('button', { name: 'Action destination' }).click()
  await page.getByRole('option', { name: /Support/ }).click()
  await editPanel.getByRole('button', { name: 'Save target' }).click()
  await expect
    .poll(() => updateNodePayload)
    .toEqual({
      node_path: ['_'],
      destination_type: 'extension',
      destination_id: 'aa90a27d-6726-43ee-820c-bbf68008a0f6',
    })
  await expect(editPanel).toHaveCount(0)
  await expect(workspace.getByRole('treeitem', { name: 'User: Support' })).toBeVisible()

  await workspace.getByRole('treeitem', { name: 'Voicemail: Reception mailbox' }).click()
  await page
    .getByRole('dialog', { name: 'Voicemail: Reception mailbox' })
    .getByRole('button', { name: 'Close node information' })
    .click()
  const inlineActionSearch = workspace.getByRole('searchbox', {
    name: 'Search callflow actions',
  })
  await inlineActionSearch.click()
  await inlineActionSearch.press('Control+A')
  await inlineActionSearch.press('Backspace')
  await inlineActionSearch.type('tts')
  await expect(inlineActionSearch).toHaveValue('tts')
  await workspace
    .getByRole('button', { name: 'Add TTS' })
    .dragTo(workspace.getByRole('treeitem', { name: 'Voicemail: Reception mailbox' }))
  const ttsPanel = page.getByRole('dialog', { name: 'Add TTS' })
  await ttsPanel.getByRole('button', { name: 'Add action' }).click()
  await expect(ttsPanel.getByRole('textbox', { name: 'Text to speak' })).toHaveAttribute(
    'aria-invalid',
    'true',
  )
  await ttsPanel
    .getByRole('textbox', { name: 'Text to speak' })
    .fill('Please hold for the next available representative.')
  await ttsPanel.getByRole('button', { name: 'Add action' }).click()
  await expect
    .poll(() => inlineCreatePayload)
    .toEqual({
      parent_path: ['_', '_'],
      branch: '_',
      module: 'tts',
      data: {
        text: 'Please hold for the next available representative.',
        voice: 'female',
        language: null,
        engine: null,
        endless_playback: false,
        terminators: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '#', '*'],
        skip_module: false,
      },
    })
  await expect(ttsPanel).toHaveCount(0)
  await expect(workspace.getByRole('treeitem', { name: 'TTS' })).toBeVisible()

  await inlineActionSearch.click()
  await inlineActionSearch.press('Control+A')
  await inlineActionSearch.fill('check caller id')
  await workspace
    .getByRole('button', { name: 'Add Check CID' })
    .dragTo(workspace.getByRole('treeitem', { name: 'TTS' }))
  const checkCidPanel = page.getByRole('dialog', { name: 'Add Check CID' })
  const callerIdPattern = checkCidPanel.getByRole('textbox', { name: 'Caller ID pattern' })
  await callerIdPattern.fill('(?R)')
  await checkCidPanel.getByRole('button', { name: 'Add action' }).click()
  await expect(callerIdPattern).toHaveAttribute('aria-invalid', 'true')
  await expect(checkCidPanel.getByText('Enter a supported regular expression.')).toBeVisible()
  await checkCidPanel.getByRole('button', { name: 'Cancel' }).click()
  await expect(checkCidPanel).toHaveCount(0)

  inlineCreatePayload = null
  await inlineActionSearch.fill('caller id list match')
  await workspace
    .getByRole('button', { name: 'Add Caller ID List Match' })
    .dragTo(workspace.getByRole('treeitem', { name: 'TTS' }))
  const listMatchPanel = page.getByRole('dialog', { name: 'Add Caller ID List Match' })
  const listChoice = listMatchPanel.getByRole('button', { name: 'Caller-ID List' })
  await listChoice.click()
  await page.getByRole('option', { name: /VIP callers/ }).click()
  await listMatchPanel.getByRole('button', { name: 'Add action' }).click()
  await expect
    .poll(() => inlineCreatePayload)
    .toEqual({
      parent_path: ['_', '_', '_'],
      branch: '_',
      module: 'cidlistmatch',
      data: {
        caller_id_list_id: 'dded4533-55cb-4b40-acb6-b02248532c09',
        skip_module: false,
      },
    })
  await expect(listMatchPanel).toHaveCount(0)

  inlineCreatePayload = null
  await inlineActionSearch.fill('response')
  await workspace
    .getByRole('button', { name: 'Add Response', exact: true })
    .dragTo(workspace.getByRole('treeitem', { name: 'TTS' }))
  const responsePanel = page.getByRole('dialog', { name: 'Add Response' })
  const responseCode = responsePanel.getByRole('spinbutton', { name: 'SIP response code' })
  await responseCode.fill('399')
  await responsePanel.getByRole('button', { name: 'Add action' }).click()
  await expect(responseCode).toHaveAttribute('aria-invalid', 'true')
  await expect(responseCode).toHaveClass(/border-red-400/)
  await responseCode.fill('603')
  await responsePanel.getByRole('textbox', { name: 'Cause text' }).fill('Decline')
  await responsePanel.getByRole('button', { name: 'Add action' }).click()
  await expect
    .poll(() => inlineCreatePayload)
    .toEqual({
      parent_path: ['_', '_', '_'],
      branch: '_',
      module: 'response',
      data: { code: 603, message: 'Decline', skip_module: false },
    })
  await expect(responsePanel).toHaveCount(0)

  inlineCreatePayload = null
  await inlineActionSearch.fill('hangup')
  await workspace
    .getByRole('button', { name: 'Add Hangup', exact: true })
    .dragTo(workspace.getByRole('treeitem', { name: 'TTS' }))
  const hangupPanel = page.getByRole('dialog', { name: 'Add Hangup' })
  await expect(hangupPanel).toContainText('disconnects the call')
  await expect(hangupPanel.getByRole('textbox')).toHaveCount(0)
  await hangupPanel.getByRole('button', { name: 'Add action' }).click()
  await expect
    .poll(() => inlineCreatePayload)
    .toEqual({
      parent_path: ['_', '_', '_'],
      branch: '_',
      module: 'hangup',
      data: { skip_module: false },
    })
  await expect(hangupPanel).toHaveCount(0)

  inlineCreatePayload = null
  await inlineActionSearch.fill('set variable')
  await workspace
    .getByRole('button', { name: 'Add Set Variable', exact: true })
    .dragTo(workspace.getByRole('treeitem', { name: 'TTS' }))
  const variablePanel = page.getByRole('dialog', { name: 'Add Set Variable' })
  const priority = variablePanel.getByRole('spinbutton', { name: 'Call priority' })
  await expect(variablePanel.getByRole('textbox', { name: 'Variable' })).toBeDisabled()
  await priority.fill('256')
  await variablePanel.getByRole('button', { name: 'Add action' }).click()
  await expect(priority).toHaveAttribute('aria-invalid', 'true')
  await priority.fill('9')
  await variablePanel.getByRole('button', { name: 'Call priority channel' }).click()
  await page.getByRole('option', { name: 'Both call legs' }).click()
  await variablePanel.getByRole('button', { name: 'Add action' }).click()
  await expect
    .poll(() => inlineCreatePayload)
    .toEqual({
      parent_path: ['_', '_', '_'],
      branch: '_',
      module: 'set_variable',
      data: {
        variable: 'call_priority',
        value: '9',
        channel: 'both',
        skip_module: false,
      },
    })
  await expect(variablePanel).toHaveCount(0)

  const map = workspace.getByRole('tree', { name: 'Callflow diagram' })
  const mapBox = await map.boundingBox()
  const workspaceBox = await workspace.boundingBox()
  const paletteBox = await actionPalette.boundingBox()
  const mainBox = await page.getByRole('main').boundingBox()
  const viewport = page.viewportSize()
  expect(mapBox).not.toBeNull()
  expect(workspaceBox).not.toBeNull()
  expect(paletteBox).not.toBeNull()
  expect(mainBox).not.toBeNull()
  expect(viewport).not.toBeNull()
  expect(mapBox!.x).toBeGreaterThanOrEqual(workspaceBox!.x)
  expect(paletteBox!.x).toBeGreaterThan(mapBox!.x)
  expect(workspaceBox!.x).toBeGreaterThanOrEqual(mainBox!.x + 16)
  expect(workspaceBox!.x + workspaceBox!.width).toBeLessThanOrEqual(
    mainBox!.x + mainBox!.width - 16,
  )
  expect(issues).toEqual([])
})
