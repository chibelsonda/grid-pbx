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
