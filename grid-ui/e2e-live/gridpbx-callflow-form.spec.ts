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
    .dragTo(workspace.getByRole('treeitem', { name: 'Temporal Route: Office hours' }))
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
    .getByRole('button', { name: 'Add Text to speech' })
    .dragTo(workspace.getByRole('treeitem', { name: 'Voicemail: Reception mailbox' }))
  const ttsPanel = page.getByRole('dialog', { name: 'Add Text to speech' })
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
  await expect(workspace.getByRole('treeitem', { name: 'Text to speech' })).toBeVisible()

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
