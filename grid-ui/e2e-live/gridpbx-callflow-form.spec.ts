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
  await expect(workspace.getByText('8', { exact: true }).first()).toBeVisible()
  await expect(
    workspace.getByRole('treeitem', { name: 'Time of Day: Office hours and holidays' }),
  ).toBeVisible()
  await expect(workspace.getByRole('treeitem', { name: 'Check CID' })).toBeVisible()
  await expect(
    workspace.getByRole('treeitem', { name: 'ACDC Member: General support queue' }),
  ).toBeVisible()
  await expect(workspace.getByRole('treeitem', { name: 'Response' })).toBeVisible()
  await expect(workspace.getByRole('treeitem', { name: 'Hangup' })).toHaveCount(2)

  await workspace.getByRole('treeitem', { name: 'Check CID' }).click()
  const nodeInfo = page.getByRole('dialog', { name: 'Check Cid' })
  await expect(nodeInfo).toContainText('Business hours match')
  await expect(nodeInfo).toContainText('Key 1 · Support')
  await expect(nodeInfo).toContainText('2')
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
  await expect(workspace.getByText('1 path', { exact: true })).toHaveCount(0)
  await expect(workspace.getByText('Default branch', { exact: true })).toHaveCount(0)
  expect(mutations).toEqual([])
  expect(issues).toEqual([])
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
