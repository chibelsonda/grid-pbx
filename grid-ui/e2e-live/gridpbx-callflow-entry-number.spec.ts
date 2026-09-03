import process from 'node:process'

import { expect, test, type Page } from '@playwright/test'

type ApiResult<T> = {
  status: number
  body: T | null
}

type CallflowResponse = {
  data: {
    id: string
    name: string
    numbers: string[]
  }
}

type CallflowEditorResponse = {
  data: {
    destinations?: {
      extension?: Array<{ id: string; label: string; detail?: string | null }>
    }
    extension_numbers?: string[]
    phone_numbers?: Array<{
      id: string
      number: string
      available: boolean
      selected: boolean
    }>
    phone_number_inventory?: {
      status: 'healthy' | 'syncing' | 'stale' | 'error'
      last_successful_at: string | null
      total_count: number
      unassigned_count: number
    }
  }
}

type CallflowListResponse = {
  data: Array<{
    id: string
    name: string | null
    numbers: string[]
    patterns: string[]
    feature_code: { name: string | null; number: string | null } | null
  }>
}

type ExtensionDirectoryResponse = {
  data: {
    entries: Array<{
      number: string
      label: string
      current: boolean
    }>
    suggested_extension: string | null
  }
}

type ExtensionAvailabilityResponse = {
  data: {
    number: string
    available: boolean
    reason: string | null
    suggested_extension: string | null
  }
}

type SyncRunResponse = {
  data: {
    id: string
    status: 'queued' | 'running' | 'succeeded' | 'failed'
    error_message: string | null
  }
}

async function apiRequest<T>(
  page: Page,
  url: string,
  method: 'GET' | 'POST' | 'PUT' | 'DELETE',
  data?: Record<string, unknown>,
): Promise<ApiResult<T>> {
  return page.evaluate(
    async ({ requestUrl, requestMethod, requestData }) => {
      const token = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] ?? '',
      )
      const response = await fetch(requestUrl, {
        method: requestMethod,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          ...(requestData ? { 'Content-Type': 'application/json' } : {}),
          ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        ...(requestData ? { body: JSON.stringify(requestData) } : {}),
      })
      const text = await response.text()

      return {
        status: response.status,
        body: text ? (JSON.parse(text) as T) : null,
      }
    },
    { requestUrl: url, requestMethod: method, requestData: data },
  )
}

test('verifies live Callflow entry discovery and inventory refresh without purchasing', async ({
  page,
}, testInfo) => {
  test.setTimeout(90_000)
  const browserIssues: string[] = []
  const forbiddenRequests: string[] = []

  page.on('console', (message) => {
    if (message.type() === 'error') browserIssues.push(`console: ${message.text()}`)
  })
  page.on('pageerror', (error) => browserIssues.push(`page: ${error.message}`))
  page.on('response', (response) => {
    if (response.status() >= 500) {
      browserIssues.push(`response: ${response.status()} ${new URL(response.url()).pathname}`)
    }
  })
  page.on('request', (request) => {
    const url = new URL(request.url())
    const method = request.method()

    if (
      method === 'GET' &&
      /\/api\/v1\/accounts\/[^/]+\/phone-numbers$/.test(url.pathname) &&
      url.searchParams.has('prefix')
    ) {
      forbiddenRequests.push(`${method} ${url.pathname}?prefix`)
    }

    if (
      ['PUT', 'PATCH', 'DELETE'].includes(method) &&
      /\/api\/v1\/accounts\/[^/]+\/phone-numbers(?:\/|$)/.test(url.pathname)
    ) {
      forbiddenRequests.push(`${method} ${url.pathname}`)
    }

    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) &&
      /\/api\/v1\/accounts\/[^/]+\/callflows(?:\/|$)/.test(url.pathname)
    ) {
      forbiddenRequests.push(`${method} ${url.pathname}`)
    }
  })

  const listResponse = page.waitForResponse(
    (response) =>
      response.request().method() === 'GET' &&
      /\/api\/v1\/accounts\/[^/]+\/callflows(?:\?|$)/.test(response.url()),
  )
  await page.goto('/call-routing')
  const resolvedListResponse = await listResponse
  const listUrl = new URL(resolvedListResponse.url())
  const apiOrigin = listUrl.origin
  const accountId = listUrl.pathname.match(/\/accounts\/([^/]+)\/callflows/)?.[1]
  expect(accountId).toBeTruthy()
  expect(resolvedListResponse.ok()).toBe(true)
  const callflows = (await resolvedListResponse.json()) as CallflowListResponse

  const editor = await apiRequest<CallflowEditorResponse>(
    page,
    `${apiOrigin}/api/v1/accounts/${accountId}/callflows/editor`,
    'GET',
  )
  expect(editor.status).toBe(200)
  expect(editor.body?.data.phone_number_inventory).toBeDefined()
  expect(JSON.stringify(editor.body)).not.toContain('switch_resource_id')

  const directory = await apiRequest<ExtensionDirectoryResponse>(
    page,
    `${apiOrigin}/api/v1/accounts/${accountId}/callflows/extension-directory`,
    'GET',
  )
  expect(directory.status).toBe(200)
  expect(JSON.stringify(directory.body)).not.toContain('switch_resource_id')
  const occupiedExtension = directory.body?.data.entries.find(({ current }) => !current)
  expect(occupiedExtension, 'A projected occupied extension is required.').toBeDefined()

  const conflict = await apiRequest<ExtensionAvailabilityResponse>(
    page,
    `${apiOrigin}/api/v1/accounts/${accountId}/callflows/extension-availability?number=${occupiedExtension!.number}`,
    'GET',
  )
  expect(conflict.status).toBe(200)
  expect(conflict.body?.data.available).toBe(false)
  expect(conflict.body?.data.reason).toContain(occupiedExtension!.number)
  expect(conflict.body?.data.suggested_extension).toMatch(/^[0-9]{2,15}$/)

  const startedSync = await apiRequest<SyncRunResponse>(
    page,
    `${apiOrigin}/api/v1/accounts/${accountId}/sync/phone-numbers`,
    'POST',
  )
  expect([200, 202]).toContain(startedSync.status)
  expect(startedSync.body?.data.id).toBeTruthy()
  let syncRun = startedSync.body!.data

  for (
    let attempt = 0;
    attempt < 40 && ['queued', 'running'].includes(syncRun.status);
    attempt += 1
  ) {
    await page.waitForTimeout(500)
    const status = await apiRequest<SyncRunResponse>(
      page,
      `${apiOrigin}/api/v1/accounts/${accountId}/sync/phone-numbers/${syncRun.id}`,
      'GET',
    )
    expect(status.status).toBe(200)
    syncRun = status.body!.data
  }

  expect(
    syncRun.status,
    syncRun.error_message ?? 'Phone-number synchronization did not finish.',
  ).toBe('succeeded')
  const refreshedEditor = await apiRequest<CallflowEditorResponse>(
    page,
    `${apiOrigin}/api/v1/accounts/${accountId}/callflows/editor`,
    'GET',
  )
  expect(refreshedEditor.status).toBe(200)
  expect(refreshedEditor.body?.data.phone_number_inventory?.status).toBe('healthy')
  expect(refreshedEditor.body?.data.phone_number_inventory?.last_successful_at).toBeTruthy()

  await page.getByRole('button', { name: 'Create callflow', exact: true }).click()
  const createWorkspace = page.getByRole('region', { name: 'Create callflow' })
  await expect(createWorkspace).toBeVisible()
  await createWorkspace.getByRole('button', { name: 'Add callflow entry number' }).first().click()
  const createDialog = page.getByRole('dialog', { name: 'Add number' })
  await expect(createDialog.getByRole('heading', { name: 'Add number' })).toBeVisible()
  await createDialog.getByRole('radio', { name: 'Extension' }).click()
  await createDialog.getByLabel('Extension number').fill(occupiedExtension!.number)
  await expect(createDialog.getByLabel('Extension number')).toHaveAttribute('aria-invalid', 'true')
  await expect(createDialog.getByText(conflict.body!.data.reason!)).toBeVisible()
  await createDialog.getByRole('button', { name: 'Browse extensions already in use' }).click()
  await createDialog.getByLabel('Search used extensions').fill(occupiedExtension!.number)
  await expect(createDialog.getByText(occupiedExtension!.number, { exact: true })).toBeVisible()
  await expect(createDialog.getByText(occupiedExtension!.label, { exact: true })).toBeVisible()

  const suggestion = conflict.body!.data.suggested_extension!
  await createDialog.getByRole('button', { name: `Use suggested extension ${suggestion}` }).click()
  await expect(createDialog.getByText(`Extension ${suggestion} is available.`)).toBeVisible()
  await expect(createDialog.getByRole('alert')).toHaveCount(0)
  await page.screenshot({ path: testInfo.outputPath('callflow-live-create-entry-discovery.png') })
  await createDialog.getByRole('button', { name: 'Cancel' }).click()
  await page.getByRole('button', { name: 'Cancel', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Callflows', level: 1 })).toBeVisible()

  const editableCallflow = callflows.data.find(
    ({ name, numbers, patterns, feature_code: featureCode }) =>
      name && featureCode === null && numbers.length + patterns.length < 2,
  )
  expect(
    editableCallflow,
    'An editable Callflow with an open entry slot is required.',
  ).toBeDefined()
  await page.getByRole('button', { name: editableCallflow!.name!, exact: true }).click()
  const editWorkspace = page.getByRole('region', { name: 'Callflow workspace' })
  await expect(editWorkspace).toBeVisible()
  await editWorkspace.getByRole('button', { name: 'Add callflow entry number' }).click()
  const editDialog = page.getByRole('dialog', { name: 'Add number' })
  await expect(editDialog.getByRole('heading', { name: 'Add number' })).toBeVisible()
  await expect(
    editDialog.getByText('Number purchasing is unavailable', { exact: false }),
  ).toBeVisible()
  await page.screenshot({ path: testInfo.outputPath('callflow-live-edit-entry-dialog.png') })
  await editDialog.getByRole('button', { name: 'Cancel' }).click()

  expect(forbiddenRequests).toEqual([])
  expect(browserIssues).toEqual([])
})

test('creates, replaces, and clears an internal callflow entry number in Switch', async ({
  page,
}) => {
  test.setTimeout(90_000)
  test.skip(
    process.env.GRID_E2E_LIVE_CALLFLOW_ENTRY_LIFECYCLE !== 'true',
    'Set GRID_E2E_LIVE_CALLFLOW_ENTRY_LIFECYCLE=true to mutate and clean up a disposable Switch callflow.',
  )

  const routeName = `GridPBX entry-number lifecycle ${Date.now()}`
  const suffix = Date.now().toString().slice(-6)
  const createdEntryNumber = `7${suffix}`
  const updatedEntryNumber = `8${suffix}`
  const retainedEntryNumber = `9${suffix}`
  let apiOrigin: string | null = null
  let accountId: string | null = null
  let callflowId: string | null = null

  try {
    const listResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows(?:\?|$)/.test(response.url()),
    )
    await page.goto('/call-routing')
    const resolvedListResponse = await listResponse
    const listUrl = new URL(resolvedListResponse.url())
    apiOrigin = listUrl.origin
    accountId = listUrl.pathname.match(/\/accounts\/([^/]+)\/callflows/)?.[1] ?? null
    expect(accountId).not.toBeNull()

    const createEditor = await apiRequest<CallflowEditorResponse>(
      page,
      `${apiOrigin}/api/v1/accounts/${accountId}/callflows/editor`,
      'GET',
    )
    expect(createEditor.status).toBe(200)
    const destination = createEditor.body?.data.destinations?.extension?.[0]
    test.skip(!destination, 'A synchronized extension is required for this live lifecycle test.')

    const create = await apiRequest<CallflowResponse>(
      page,
      `${apiOrigin}/api/v1/accounts/${accountId}/callflows`,
      'POST',
      {
        name: routeName,
        destination_type: 'extension',
        destination_id: destination!.id,
        phone_number_ids: [],
        extension_numbers: [createdEntryNumber, retainedEntryNumber],
      },
    )
    expect(create.status).toBe(201)
    expect(create.body?.data.numbers).toContain(createdEntryNumber)
    callflowId = create.body?.data.id ?? null
    expect(callflowId).not.toBeNull()

    const createdEditor = await apiRequest<CallflowEditorResponse>(
      page,
      `${apiOrigin}/api/v1/accounts/${accountId}/callflows/${callflowId}/editor`,
      'GET',
    )
    expect(createdEditor.status).toBe(200)
    expect(createdEditor.body?.data.extension_numbers).toEqual([
      createdEntryNumber,
      retainedEntryNumber,
    ])

    const update = await apiRequest<CallflowResponse>(
      page,
      `${apiOrigin}/api/v1/accounts/${accountId}/callflows/${callflowId}`,
      'PUT',
      {
        name: routeName,
        destination_type: 'extension',
        destination_id: destination!.id,
        phone_number_ids: [],
        extension_numbers: [updatedEntryNumber, retainedEntryNumber],
      },
    )
    expect(update.status).toBe(200)
    expect(update.body?.data.numbers).toContain(updatedEntryNumber)
    expect(update.body?.data.numbers).not.toContain(createdEntryNumber)

    const updatedEditor = await apiRequest<CallflowEditorResponse>(
      page,
      `${apiOrigin}/api/v1/accounts/${accountId}/callflows/${callflowId}/editor`,
      'GET',
    )
    expect(updatedEditor.status).toBe(200)
    expect(updatedEditor.body?.data.extension_numbers).toEqual([
      updatedEntryNumber,
      retainedEntryNumber,
    ])

    const clear = await apiRequest<CallflowResponse>(
      page,
      `${apiOrigin}/api/v1/accounts/${accountId}/callflows/${callflowId}`,
      'PUT',
      {
        name: routeName,
        destination_type: 'extension',
        destination_id: destination!.id,
        phone_number_ids: [],
        extension_numbers: [retainedEntryNumber],
      },
    )
    expect(clear.status, JSON.stringify(clear.body)).toBe(200)
    expect(clear.body?.data.numbers).not.toContain(updatedEntryNumber)
    expect(clear.body?.data.numbers).toContain(retainedEntryNumber)

    const clearedEditor = await apiRequest<CallflowEditorResponse>(
      page,
      `${apiOrigin}/api/v1/accounts/${accountId}/callflows/${callflowId}/editor`,
      'GET',
    )
    expect(clearedEditor.status).toBe(200)
    expect(clearedEditor.body?.data.extension_numbers).toEqual([retainedEntryNumber])
  } finally {
    if (apiOrigin && accountId && callflowId) {
      const deleted = await apiRequest<null>(
        page,
        `${apiOrigin}/api/v1/accounts/${accountId}/callflows/${callflowId}`,
        'DELETE',
      )
      expect(deleted.status).toBe(204)
    }
  }
})
