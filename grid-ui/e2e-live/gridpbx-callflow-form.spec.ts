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
          temporal_rule_sets: {},
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

test('renders a recursive visual route map without exposing preserved Switch branch keys', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
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

  await page.goto('/call-routing')
  await page.getByRole('button', { name: 'View Visual diagram route' }).click()
  const dialog = page.getByRole('dialog', { name: 'Visual diagram route' })
  await expect(dialog.getByRole('heading', { name: 'Visual route map' })).toBeVisible()
  await expect(dialog.getByText('Schedule matches', { exact: true }).last()).toBeVisible()
  await expect(dialog.getByText('Preserved branch 1', { exact: true }).last()).toBeVisible()
  await expect(dialog.getByText('Reception', { exact: true })).toBeVisible()
  await expect(dialog.getByText('switch-rule-secret')).toHaveCount(0)

  await dialog.getByRole('treeitem', { name: 'User: Reception' }).click()
  const inspector = dialog.getByRole('region', { name: 'Selected node' })
  await expect(inspector).toContainText('Root / Schedule matches')
  await expect(inspector).toContainText('user')
  await expect(inspector).toContainText('Reception')
  await expect(inspector).toContainText('Guided now')

  const actionSearch = dialog.getByRole('searchbox', { name: 'Search callflow actions' })
  await actionSearch.fill('webhook')
  await expect(dialog.getByText('Webhook', { exact: true })).toBeVisible()
  await expect(dialog.getByText('Capability required', { exact: true })).toBeVisible()
  await expect(dialog.getByText('1 action', { exact: true })).toBeVisible()

  const map = dialog.getByRole('tree', { name: 'Callflow diagram' })
  const mapBox = await map.boundingBox()
  const dialogBox = await dialog.boundingBox()
  expect(mapBox).not.toBeNull()
  expect(dialogBox).not.toBeNull()
  expect(mapBox!.x).toBeGreaterThanOrEqual(dialogBox!.x)
  expect(issues).toEqual([])
})
