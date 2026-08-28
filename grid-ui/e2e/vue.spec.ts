import { test, expect } from '@playwright/test'

test('signs in and shows the GridPBX dashboard shell', async ({ page }) => {
  await page.route('**/api/v1/session', (route) =>
    route.fulfill({
      status: 401,
      contentType: 'application/json',
      body: '{"message":"Unauthenticated."}',
    }),
  )
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }))
  await page.route('**/login', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { user: { id: 1, name: 'Grid Admin', email: 'admin@gridpbx.local' } },
      }),
    }),
  )
  await page.route('**/api/v1/accounts', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [] }),
    }),
  )

  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible()
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page.getByRole('heading', { name: 'Good day, Grid' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'People & Extensions' })).toBeVisible()
})

test('provisions an extension aggregate from a right-side panel', async ({ page }) => {
  let submitted: Record<string, unknown> | null = null
  let deletionSubmitted: Record<string, unknown> | null = null
  let previewRequests = 0
  let recoveryRequests = 0
  const extension = {
    id: 'extension-public-id',
    display_name: 'Alice Operator',
    first_name: 'Alice',
    last_name: 'Operator',
    username: 'alice.operator',
    email: 'alice@example.test',
    extension: '1001',
    timezone: 'Asia/Manila',
    is_enabled: true,
    is_managed: true,
    sync_status: 'healthy',
    last_synced_at: '2026-08-28T11:00:00+08:00',
    devices: [],
    voicemail_boxes: [
      {
        id: 'voicemail-public-id',
        name: '(1001) Alice Operator',
        mailbox: '1001',
        is_setup: false,
        timezone: 'Asia/Manila',
        notification_emails: [],
        transcribe: false,
        require_pin: false,
        message_count: 0,
        is_managed: true,
        sync_status: 'healthy',
        last_synced_at: '2026-08-28T11:00:00+08:00',
      },
    ],
    callflows: [
      {
        id: 'callflow-public-id',
        name: 'Alice Operator',
        numbers: ['1001'],
        modules: ['user', 'voicemail'],
        is_managed: true,
        sync_status: 'healthy',
        last_synced_at: '2026-08-28T11:00:00+08:00',
      },
    ],
  }

  await page.route('**/api/v1/session', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { user: { id: 'user-public-id', name: 'Grid Admin', email: 'admin@gridpbx.local' } },
      }),
    }),
  )
  await page.route('**/api/v1/accounts', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 'account-1',
            name: 'GridPBX',
            realm: 'gridpbx.local',
            organization: { id: 'organization-1', name: 'GridPBX' },
            organization_role: 'account_administrator',
            permissions: {
              can_manage_extensions: true,
              can_manage_devices: true,
              can_manage_voicemail: true,
              can_manage_call_routing: true,
            },
          },
        ],
      }),
    }),
  )
  await page.route('**/api/v1/accounts/account-1/extensions**', (route) => {
    const path = new URL(route.request().url()).pathname

    if (route.request().method() === 'POST') {
      submitted = route.request().postDataJSON() as Record<string, unknown>
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: extension }),
      })
    }

    if (route.request().method() === 'PUT') {
      submitted = route.request().postDataJSON() as Record<string, unknown>
      Object.assign(extension, {
        display_name: 'Alice Operations',
        last_name: 'Operations',
        extension: '1002',
      })
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: extension }),
      })
    }

    if (route.request().method() === 'DELETE') {
      deletionSubmitted = route.request().postDataJSON() as Record<string, unknown>
      return route.fulfill({ status: 204 })
    }

    if (path.endsWith('/extension-public-id/deletion-preview')) {
      previewRequests += 1
      const blocked = previewRequests === 1
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            extension: {
              id: 'extension-public-id',
              display_name: 'Alice Operations',
              extension: '1002',
              managed: true,
            },
            can_delete: !blocked,
            blockers: blocked
              ? [
                  {
                    code: 'referenced_by_callflow',
                    message: 'Another callflow references this extension.',
                  },
                ]
              : [],
            managed_resources: {
              devices: [],
              voicemail_boxes: [
                {
                  id: 'voicemail-public-id',
                  name: '(1002) Alice Operations',
                  mailbox: '1002',
                  message_count: 0,
                },
              ],
              callflows: [
                {
                  id: 'callflow-public-id',
                  name: 'Alice Operations',
                  numbers: ['1002'],
                  phone_number_count: 0,
                },
              ],
            },
            shared_resources: { device_count: 0, voicemail_box_count: 0, callflow_count: 0 },
            referencing_callflows: blocked ? [{ id: 'callflow-2', name: 'Main menu' }] : [],
            unresolved_callflows: [],
            recovery: null,
          },
        }),
      })
    }

    if (path.endsWith('/extension-public-id')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: extension }),
      })
    }

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [],
        links: { prev: null, next: null },
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 0,
          sync: { status: 'healthy', last_successful_at: null, error_message: null },
        },
      }),
    })
  })
  await page.route('**/api/v1/accounts/account-1/extension-recovery**', (route) => {
    const operation = {
      id: 'operation-public-id',
      operation: 'provision',
      status: route.request().method() === 'POST' ? 'recovered' : 'failed',
      display_name: 'Failed Operator',
      extension: '1099',
      extension_id: null,
      completed_steps: ['user'],
      failed_step: route.request().method() === 'POST' ? null : 'device',
      recovery_action: 'cleanup',
      repair_required: route.request().method() !== 'POST',
      updated_at: '2026-08-28T11:00:00+08:00',
    }

    if (route.request().method() === 'POST') {
      recoveryRequests += 1
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: operation }),
      })
    }

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [operation] }),
    })
  })

  await page.goto('/extensions')
  await page.getByRole('button', { name: 'Create extension' }).click()

  const panel = page.getByRole('dialog', { name: 'Create extension' })
  await expect(panel).toBeVisible()
  await panel.getByLabel('First name').fill('Alice')
  await panel.getByLabel('Last name').fill('Operator')
  await panel.getByLabel('Extension number').fill('1001')
  await panel.getByLabel('Username').fill('alice.operator')
  await panel.getByLabel('Email', { exact: true }).fill('alice@example.test')
  await panel.getByRole('button', { name: 'Create extension' }).click()

  await expect(page).toHaveURL(/\/extensions\/extension-public-id$/)
  await expect(page.getByRole('heading', { name: 'Alice Operator', level: 1 })).toBeVisible()
  expect(submitted).toMatchObject({
    first_name: 'Alice',
    last_name: 'Operator',
    extension: '1001',
    voicemail: { enabled: true },
    device: { enabled: false },
  })

  await page.getByRole('button', { name: 'Edit' }).click()
  const editPanel = page.getByRole('dialog', { name: 'Edit extension' })
  await editPanel.getByLabel('Last name').fill('Operations')
  await editPanel.getByLabel('Extension number').fill('1002')
  await editPanel.getByRole('button', { name: 'Save changes' }).click()
  await expect(page.getByRole('heading', { name: 'Alice Operations', level: 1 })).toBeVisible()
  expect(submitted).toMatchObject({ last_name: 'Operations', extension: '1002' })

  await page.getByRole('button', { name: 'Review deletion' }).click()
  const reviewPanel = page.getByRole('dialog', { name: 'Review deletion' })
  await expect(reviewPanel.getByText('Deletion is blocked')).toBeVisible()
  await expect(reviewPanel.getByText('Another callflow references this extension.')).toBeVisible()
  await expect(
    reviewPanel.getByText('Deletion remains disabled while blockers exist.'),
  ).toBeVisible()
  await reviewPanel.getByRole('button', { name: 'Close review' }).click()

  await page.getByRole('button', { name: 'Review deletion' }).click()
  const confirmedPanel = page.getByRole('dialog', { name: 'Review deletion' })
  await expect(confirmedPanel.getByText('No known blockers')).toBeVisible()
  const deleteButton = confirmedPanel.getByRole('button', { name: 'Delete extension' })
  await expect(deleteButton).toBeDisabled()
  await confirmedPanel.getByLabel(/Type 1002 to confirm/).fill('1002')
  await deleteButton.click()

  await expect(page).toHaveURL(/\/extensions$/)
  expect(deletionSubmitted).toEqual({ confirmation: '1002' })

  await page.getByRole('button', { name: 'Recovery queue' }).click()
  const recoveryPanel = page.getByRole('dialog', { name: 'Extension recovery queue' })
  await expect(recoveryPanel.getByText('Failed Operator')).toBeVisible()
  await expect(recoveryPanel.getByText('Device')).toBeVisible()
  await recoveryPanel.getByRole('button', { name: 'Retry cleanup' }).click()
  await expect(recoveryPanel.getByText('No recovery work pending')).toBeVisible()
  expect(recoveryRequests).toBe(1)
})

test('opens voicemail create as a right-side slide-over panel', async ({ page }) => {
  await page.route('**/api/v1/session', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { user: { id: 1, name: 'Grid Admin', email: 'admin@gridpbx.local' } },
      }),
    }),
  )
  await page.route('**/api/v1/accounts', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 'account-1',
            name: 'GridPBX',
            realm: 'gridpbx.local',
            organization: { id: 'organization-1', name: 'GridPBX' },
            organization_role: 'account_administrator',
            permissions: {
              can_manage_devices: true,
              can_manage_voicemail: true,
              can_manage_call_routing: true,
            },
          },
        ],
      }),
    }),
  )
  await page.route('**/api/v1/accounts/account-1/voicemail-boxes**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [],
        links: { prev: null, next: null },
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 0,
          sync: { status: 'healthy', last_successful_at: null, error_message: null },
        },
      }),
    }),
  )
  await page.route('**/api/v1/accounts/account-1/extensions**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: '{"data":[]}' }),
  )

  await page.goto('/voicemail')
  await expect(page.getByRole('heading', { name: 'Voicemail boxes' })).toBeVisible()
  await page.getByRole('link', { name: 'Add mailbox' }).click()

  const panel = page.getByRole('dialog', { name: 'Add voicemail box' })
  await expect(panel).toBeVisible()
  await expect(panel.getByRole('heading', { name: 'Add voicemail box' })).toBeVisible()
  await expect(panel.getByLabel('Mailbox name')).toBeVisible()
  await expect(page).toHaveURL(/\/voicemail\/new$/)

  await page.keyboard.press('Escape')
  await expect(panel).toBeHidden()
  await expect(page).toHaveURL(/\/voicemail$/)
})

test('shows projected voicemail metadata with protected audio controls', async ({ page }) => {
  let messageFolder: 'new' | 'saved' | 'deleted' = 'new'
  await page.route('**/api/v1/session', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { user: { id: 1, name: 'Grid Admin', email: 'admin@gridpbx.local' } },
      }),
    }),
  )
  await page.route('**/api/v1/accounts', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 'account-1',
            name: 'GridPBX',
            realm: 'gridpbx.local',
            organization: { id: 'organization-1', name: 'GridPBX' },
            organization_role: 'account_administrator',
            permissions: {
              can_manage_devices: true,
              can_manage_voicemail: true,
              can_manage_call_routing: true,
            },
          },
        ],
      }),
    }),
  )
  await page.route(
    '**/api/v1/accounts/account-1/voicemail-boxes/voicemail-1/messages**',
    (route) => {
      const message = {
        id: 'message-1',
        folder: messageFolder,
        caller_id_name: 'Ada Lovelace',
        caller_id_number: '+15551234567',
        from_address: '+15551234567',
        to_address: '5463',
        length: 42000,
        occurred_at: '2026-08-28T08:00:00+08:00',
        transcription_result: 'success',
        transcription_text: 'Please call me back about the deployment.',
        sync_status: 'healthy',
        last_synced_at: '2026-08-28T08:01:00+08:00',
      }

      if (route.request().method() === 'PATCH') {
        const payload = route.request().postDataJSON() as { folder: typeof messageFolder }
        messageFolder = payload.folder
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            data: { folder: messageFolder, succeeded: ['message-1'], failed: [] },
          }),
        })
      }

      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [message],
          links: { prev: null, next: null },
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        }),
      })
    },
  )
  await page.route('**/api/v1/accounts/account-1/voicemail-boxes/voicemail-1', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          id: 'voicemail-1',
          name: 'Main mailbox',
          mailbox: '5463',
          timezone: 'Asia/Manila',
          notification_emails: ['ops@gridpbx.local'],
          transcribe: true,
          require_pin: true,
          is_setup: true,
          message_counts: { total: 1, new: 1, saved: 0, deleted: 0 },
          unavailable_greeting: null,
          assigned_extension: null,
          sync_status: 'healthy',
          last_synced_at: '2026-08-28T08:01:00+08:00',
        },
      }),
    }),
  )

  await page.goto('/voicemail/voicemail-1')

  await expect(page.getByRole('heading', { name: 'Main mailbox' })).toBeVisible()
  await expect(page.getByText('Ada Lovelace')).toBeVisible()
  await expect(page.getByText('Please call me back about the deployment.')).toBeVisible()
  await expect(page.locator('audio')).toHaveAttribute(
    'src',
    /\/api\/v1\/accounts\/account-1\/voicemail-boxes\/voicemail-1\/messages\/message-1\/audio$/,
  )
  await expect(page.getByRole('link', { name: 'Download' })).toHaveAttribute('href', /download=1$/)

  await page.getByLabel('Select message from Ada Lovelace').check()
  await page.getByRole('button', { name: 'Save' }).first().click()
  await expect(page.getByText('saved', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Mark new' }).last()).toBeVisible()

  await page.getByRole('button', { name: 'Upload greeting' }).click()
  const greetingPanel = page.getByRole('dialog', { name: 'Upload unavailable greeting' })
  await expect(greetingPanel).toBeVisible()
  await expect(greetingPanel.getByLabel('Audio file')).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(greetingPanel).toBeHidden()
})

test('manages media and music on hold through right-side panels', async ({ page }) => {
  let mediaName = 'Main hold music'
  let isMusicOnHold = false
  const mediaRecord = () => ({
    id: 'media-1',
    name: mediaName,
    description: 'Lobby loop',
    language: 'en-us',
    media_source: 'upload',
    content_type: 'audio/mpeg',
    content_length: 4096,
    prompt_id: null,
    streamable: true,
    is_music_on_hold: isMusicOnHold,
    dependencies: {
      music_on_hold: Number(isMusicOnHold),
      voicemail_greetings: 0,
      callflows: 0,
      total: Number(isMusicOnHold),
      can_delete: !isMusicOnHold,
    },
    last_synced_at: '2026-08-28T05:00:00Z',
    sync_status: 'healthy',
    created_at: '2026-08-28T04:00:00Z',
    updated_at: '2026-08-28T05:00:00Z',
  })

  await page.route('**/api/v1/session', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { user: { id: 'user-1', name: 'Grid Admin', email: 'admin@gridpbx.local' } },
      }),
    }),
  )
  await page.route('**/api/v1/accounts', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 'account-1',
            name: 'GridPBX',
            realm: 'gridpbx.local',
            organization: { id: 'organization-1', name: 'GridPBX' },
            organization_role: 'account_administrator',
            permissions: {
              can_manage_extensions: true,
              can_manage_devices: true,
              can_manage_voicemail: true,
              can_manage_call_routing: true,
              can_manage_media: true,
              can_sync_call_detail_records: true,
            },
          },
        ],
      }),
    }),
  )
  await page.route('**/api/v1/accounts/account-1/media**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname

    if (path.endsWith('/music-on-hold') && request.method() === 'PUT') {
      isMusicOnHold = true
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { media: mediaRecord() } }),
      })
    }

    if (path.endsWith('/media-1/audio')) {
      return route.fulfill({ status: 200, contentType: 'audio/mpeg', body: 'MP3!' })
    }

    if (path.endsWith('/media-1') && request.method() === 'PUT') {
      mediaName = (request.postDataJSON() as { name: string }).name
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: mediaRecord() }),
      })
    }

    if (path.endsWith('/media-1')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: mediaRecord() }),
      })
    }

    if (request.method() === 'POST') {
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: mediaRecord() }),
      })
    }

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [mediaRecord()],
        links: { prev: null, next: null },
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
          sync: { status: 'healthy', last_successful_at: '2026-08-28T05:00:00Z', error_message: null },
        },
      }),
    })
  })

  await page.goto('/media')
  await expect(page.getByRole('heading', { name: 'Media & Music on Hold' })).toBeVisible()
  await page.getByRole('button', { name: 'Upload media' }).click()
  const createPanel = page.getByRole('dialog', { name: 'Upload media' })
  await expect(createPanel).toBeVisible()
  await createPanel.getByLabel('Name').fill('Main hold music')
  await createPanel.getByLabel('Description').fill('Lobby loop')
  await createPanel.getByLabel('Audio file').setInputFiles({
    name: 'hold.mp3',
    mimeType: 'audio/mpeg',
    buffer: Buffer.from('MP3!'),
  })
  await createPanel.getByRole('button', { name: 'Upload media' }).click()

  const detailPanel = page.getByRole('dialog', { name: 'Main hold music' })
  await expect(detailPanel).toBeVisible()
  await detailPanel.getByRole('button', { name: 'Edit' }).click()
  const editPanel = page.getByRole('dialog', { name: 'Edit media' })
  await editPanel.getByLabel('Name').fill('Reception hold music')
  await editPanel.getByRole('button', { name: 'Save changes' }).click()
  await expect(page.getByRole('dialog', { name: 'Reception hold music' })).toBeVisible()
  await page.keyboard.press('Escape')

  await page.getByRole('button', { name: 'Music on hold' }).click()
  const mohPanel = page.getByRole('dialog', { name: 'Music on hold' })
  await mohPanel.getByLabel('Hold media').selectOption('media-1')
  await mohPanel.getByRole('button', { name: 'Save music on hold' }).click()
  await expect(page.getByText('Reception hold music').first()).toBeVisible()
})

test('shows phone number inventory and opens details in a right-side panel', async ({ page }) => {
  const phoneNumberId = '2baf74c0-70dc-486f-a345-e910034e032c'
  const phoneNumber = {
    id: phoneNumberId,
    number: '+15551234567',
    state: 'in_service',
    used_by: 'callflow',
    carrier_name: 'Test Carrier',
    features: ['cnam', 'e911'],
    cnam: { display_name: 'GridPBX', inbound_lookup: true },
    e911_status: 'PROVISIONED',
    assigned_callflow: {
      id: 'be945751-ec72-413d-9263-e793440b189c',
      name: 'Main number',
      numbers: ['+15551234567'],
    },
    sync_status: 'healthy',
    last_synced_at: '2026-08-28T09:00:00+08:00',
  }

  await page.route('**/api/v1/session', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { user: { id: 'user-public-id', name: 'Grid Admin', email: 'admin@gridpbx.local' } },
      }),
    }),
  )
  await page.route('**/api/v1/accounts', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 'account-1',
            name: 'GridPBX',
            realm: 'gridpbx.local',
            organization: { id: 'organization-1', name: 'GridPBX' },
            organization_role: 'account_administrator',
            permissions: {
              can_manage_devices: true,
              can_manage_voicemail: true,
              can_manage_call_routing: true,
            },
          },
        ],
      }),
    }),
  )
  await page.route('**/api/v1/accounts/account-1/phone-numbers**', (route) => {
    if (new URL(route.request().url()).pathname.endsWith(`/${phoneNumberId}`)) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: phoneNumber }),
      })
    }

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [phoneNumber],
        links: { prev: null, next: null },
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
          sync: {
            status: 'healthy',
            last_successful_at: '2026-08-28T09:00:00+08:00',
            error_message: null,
          },
        },
      }),
    })
  })

  await page.goto('/phone-numbers')

  await expect(page.getByRole('heading', { name: 'Phone Numbers' })).toBeVisible()
  await expect(page.getByText('+15551234567')).toBeVisible()
  await page.getByRole('button', { name: 'View +15551234567' }).click()

  const panel = page.getByRole('dialog', { name: '+15551234567' })
  await expect(panel).toBeVisible()
  await expect(panel.getByText('Test Carrier')).toBeVisible()
  await expect(panel.getByText('Main number')).toBeVisible()
  await expect(panel.getByText('Carrier acquisition, release', { exact: false })).toBeVisible()

  await page.keyboard.press('Escape')
  await expect(panel).toBeHidden()
})

test('shows projected call routes and a safe structural tree in a right-side panel', async ({
  page,
}) => {
  const callflowId = '6db510f0-7821-4ffc-a7fa-eae51d94b6b3'
  let savedRoutePayload: Record<string, unknown> | null = null
  let createdRoutePayload: Record<string, unknown> | null = null
  const callflow = {
    id: callflowId,
    name: 'Main Reception',
    route_type: 'phone_number',
    numbers: ['+15551234567'],
    patterns: [],
    flags: [],
    modules: ['ring_group', 'voicemail'],
    root_module: 'ring_group',
    node_count: 2,
    max_depth: 2,
    feature_code: null,
    flow: {
      module: 'ring_group',
      target: null,
      reference_status: 'not_applicable',
      children: {
        _: {
          module: 'voicemail',
          target: null,
          reference_status: 'unresolved',
          children: {},
        },
      },
    },
    linked_extension: {
      id: 'extension-public-id',
      display_name: 'Reception',
      extension: '1001',
    },
    phone_numbers: [{ id: 'number-public-id', number: '+15551234567', state: 'in_service' }],
    sync_status: 'healthy',
    last_synced_at: '2026-08-28T10:00:00+08:00',
  }

  await page.route('**/api/v1/session', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { user: { id: 'user-public-id', name: 'Grid Admin', email: 'admin@gridpbx.local' } },
      }),
    }),
  )
  await page.route('**/api/v1/accounts', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 'account-1',
            name: 'GridPBX',
            realm: 'gridpbx.local',
            organization: { id: 'organization-1', name: 'GridPBX' },
            organization_role: 'account_administrator',
            permissions: {
              can_manage_devices: true,
              can_manage_voicemail: true,
              can_manage_call_routing: true,
            },
          },
        ],
      }),
    }),
  )
  await page.route('**/api/v1/accounts/account-1/callflows**', (route) => {
    const path = new URL(route.request().url()).pathname

    if (path.endsWith('/callflows/editor')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            mode: 'create',
            editable: true,
            blocked_reason: null,
            destination_types: [{ value: 'extension', label: 'Extension' }],
            destinations: {
              extension: [{ id: 'extension-public-id', label: 'Reception', detail: '1001' }],
              device: [],
              voicemail: [],
              callflow: [],
              media: [],
            },
            phone_numbers: [
              {
                id: 'new-number-public-id',
                number: '+15559876543',
                state: 'in_service',
                selected: false,
                available: true,
                assigned_callflow: null,
              },
            ],
          },
        }),
      })
    }

    if (path.endsWith('/callflows') && route.request().method() === 'POST') {
      createdRoutePayload = route.request().postDataJSON() as Record<string, unknown>

      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            ...callflow,
            id: 'created-route-public-id',
            name: 'After hours route',
            numbers: ['+15559876543'],
            phone_numbers: [
              { id: 'new-number-public-id', number: '+15559876543', state: 'in_service' },
            ],
          },
        }),
      })
    }

    if (path.endsWith(`/${callflowId}/editor`)) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            mode: 'update',
            editable: true,
            blocked_reason: null,
            destination_types: [
              { value: 'extension', label: 'Extension' },
              { value: 'voicemail', label: 'Voicemail' },
            ],
            destinations: {
              extension: [{ id: 'extension-public-id', label: 'Reception', detail: '1001' }],
              device: [],
              voicemail: [{ id: 'mailbox-public-id', label: 'Reception mailbox', detail: '1001' }],
              callflow: [],
              media: [],
            },
            phone_numbers: [
              {
                id: 'number-public-id',
                number: '+15551234567',
                state: 'in_service',
                selected: true,
                available: true,
                assigned_callflow: { id: callflowId, name: 'Main Reception' },
              },
              {
                id: 'busy-number-public-id',
                number: '+15557654321',
                state: 'in_service',
                selected: false,
                available: false,
                assigned_callflow: { id: 'other-route-id', name: 'Support queue' },
              },
            ],
          },
        }),
      })
    }

    if (path.endsWith(`/${callflowId}`) && route.request().method() === 'PUT') {
      savedRoutePayload = route.request().postDataJSON() as Record<string, unknown>

      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            ...callflow,
            name: 'Updated reception route',
            root_module: 'user',
            flow: {
              module: 'user',
              target: { type: 'extension', id: 'extension-public-id', label: 'Reception' },
              reference_status: 'resolved',
              children: callflow.flow.children,
            },
          },
        }),
      })
    }

    if (path.endsWith(`/${callflowId}`)) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: callflow }),
      })
    }

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [callflow],
        links: { prev: null, next: null },
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
          sync: {
            status: 'healthy',
            last_successful_at: '2026-08-28T10:00:00+08:00',
            error_message: null,
            scope: 'pbx_projection',
          },
        },
      }),
    })
  })

  await page.goto('/call-routing')

  await expect(page.getByRole('heading', { name: 'Call Routing' })).toBeVisible()
  await expect(page.getByText('Main Reception')).toBeVisible()
  await page.getByRole('button', { name: 'View Main Reception' }).click()

  const panel = page.getByRole('dialog', { name: 'Main Reception' })
  await expect(panel).toBeVisible()
  await expect(panel.getByText('Ring Group')).toBeVisible()
  await expect(panel.getByText('Voicemail', { exact: true })).toBeVisible()
  await expect(
    panel.getByText('Raw node data and Switch identifiers', { exact: false }),
  ).toBeVisible()
  await expect(panel).not.toContainText('private-switch-id')

  await panel.getByRole('button', { name: 'Edit guided route' }).click()
  const editor = page.getByRole('dialog', { name: 'Edit guided route' })
  await expect(editor).toBeVisible()
  await expect(editor.getByText('Resolved GridPBX target')).toBeVisible()
  await expect(editor.getByText('Currently enters this route')).toBeVisible()
  await expect(editor.getByText('Assigned to Support queue')).toBeVisible()
  await editor.getByLabel('Route name').fill('Updated reception route')
  await editor.getByRole('button', { name: 'Save route' }).click()

  await expect(editor).toBeHidden()
  await expect(page.getByRole('dialog', { name: 'Updated reception route' })).toBeVisible()
  expect(savedRoutePayload).toEqual({
    name: 'Updated reception route',
    destination_type: 'extension',
    destination_id: 'extension-public-id',
    phone_number_ids: ['number-public-id'],
  })

  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog', { name: 'Updated reception route' })).toBeHidden()

  await page.getByRole('button', { name: 'Create route' }).click()
  const creator = page.getByRole('dialog', { name: 'Create call route' })
  await expect(creator).toBeVisible()
  await creator.getByLabel('Route name').fill('After hours route')
  await creator.getByText('+15559876543').click()
  await creator.getByRole('button', { name: 'Create route' }).click()

  await expect(creator).toBeHidden()
  await expect(page.getByRole('dialog', { name: 'After hours route' })).toBeVisible()
  expect(createdRoutePayload).toEqual({
    name: 'After hours route',
    destination_type: 'extension',
    destination_id: 'extension-public-id',
    phone_number_ids: ['new-number-public-id'],
  })
})

test('filters projected call history and opens safe details in a right-side panel', async ({
  page,
}) => {
  let requestedQuery = ''
  const record = {
    id: 'cdr-public-id',
    call_id: 'call-1',
    interaction_id: 'interaction-1',
    direction: 'inbound',
    caller: { name: 'Alice Caller', number: '+14155550100' },
    callee: { name: 'Grid Support', number: '1001' },
    from: 'alice@example.test',
    to: '1001@gridpbx.test',
    request: '1001@gridpbx.test',
    started_at: '2026-08-28T04:00:00Z',
    duration_seconds: 75,
    billing_seconds: 60,
    answered: true,
    hangup_cause: 'NORMAL_CLEARING',
    disposition: 'SUCCESS',
    recording_available: true,
    extension: {
      id: 'extension-public-id',
      display_name: 'Support Operator',
      extension: '1001',
    },
    last_synced_at: '2026-08-28T05:00:00Z',
  }

  await page.route('**/api/v1/session', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { user: { id: 'user-public-id', name: 'Grid Admin', email: 'admin@gridpbx.local' } },
      }),
    }),
  )
  await page.route('**/api/v1/accounts', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 'account-1',
            name: 'GridPBX',
            realm: 'gridpbx.local',
            organization: { id: 'organization-1', name: 'GridPBX' },
            organization_role: 'account_operator',
            permissions: {
              can_manage_extensions: true,
              can_manage_devices: true,
              can_manage_voicemail: true,
              can_manage_call_routing: true,
              can_sync_call_detail_records: true,
            },
          },
        ],
      }),
    }),
  )
  await page.route('**/api/v1/accounts/account-1/call-detail-records**', (route) => {
    const url = new URL(route.request().url())

    if (url.pathname.endsWith('/cdr-public-id')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: record }),
      })
    }

    requestedQuery = url.search
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [record],
        links: { prev: null, next: null },
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
          sync: {
            status: 'healthy',
            last_successful_at: '2026-08-28T05:00:00Z',
            error_message: null,
          },
          import_window_days: 7,
        },
      }),
    })
  })

  await page.goto('/call-history')

  await expect(page.getByRole('heading', { name: 'Call History' })).toBeVisible()
  await expect(page.getByText('Alice Caller')).toBeVisible()
  await page.getByLabel('Search call history').fill('Alice')
  await page.getByLabel('Call direction').selectOption('inbound')
  await page.getByLabel('Call outcome').selectOption('answered')
  await page.getByRole('button', { name: 'Apply filters' }).click()
  await expect.poll(() => requestedQuery).toContain('search=Alice')
  expect(requestedQuery).toContain('direction=inbound')
  expect(requestedQuery).toContain('outcome=answered')

  await page.getByRole('button', { name: 'View call from +14155550100' }).click()
  const panel = page.getByRole('dialog', { name: '+14155550100 → 1001' })
  await expect(panel).toBeVisible()
  await expect(panel.getByText('Support Operator')).toBeVisible()
  await expect(panel.getByText('1m 15s')).toBeVisible()
  await expect(panel.getByText('NORMAL CLEARING', { exact: false })).toBeVisible()
  await expect(
    panel.getByText('Playback and download remain disabled', { exact: false }),
  ).toBeVisible()
  await expect(panel).not.toContainText('switch_resource_id')
})

test('creates directories and groups through right-side panels', async ({ page }) => {
  let directoryPayload: Record<string, unknown> | null = null
  let groupPayload: Record<string, unknown> | null = null
  await page.route('**/api/v1/session', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { user: { id: 'user-1', name: 'Grid Admin', email: 'admin@gridpbx.local' } } }) }))
  await page.route('**/api/v1/accounts', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [{ id: 'account-1', name: 'GridPBX', realm: 'gridpbx.local', organization: { id: 'org-1', name: 'GridPBX' }, organization_role: 'account_operator', permissions: { can_manage_call_routing: true } }] }) }))
  await page.route('**/api/v1/accounts/account-1/directories/options', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { extensions: [{ id: 'extension-1', label: 'Ada Lovelace', detail: '1001' }] } }) }))
  await page.route('**/api/v1/accounts/account-1/directories**', (route) => {
    if (route.request().method() === 'POST') {
      directoryPayload = route.request().postDataJSON() as Record<string, unknown>
      return route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: { id: 'directory-1', name: 'People', confirm_match: true, min_dtmf: 3, max_dtmf: 0, sort_by: 'last_name', members: [], sync_status: 'healthy', last_synced_at: null } }) })
    }
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, from: null, last_page: 1, per_page: 25, to: null, total: 0 } }) })
  })
  await page.route('**/api/v1/accounts/account-1/groups/options', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { users: [{ id: 'extension-1', label: 'Ada Lovelace', detail: '1001' }], devices: [], groups: [], media: [] } }) }))
  await page.route('**/api/v1/accounts/account-1/groups**', (route) => {
    if (route.request().method() === 'POST') {
      groupPayload = route.request().postDataJSON() as Record<string, unknown>
      return route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: { id: 'group-1', name: 'Support', member_count: 1, members: [], music_on_hold_media: null, sync_status: 'healthy', last_synced_at: null } }) })
    }
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, from: null, last_page: 1, per_page: 25, to: null, total: 0 } }) })
  })

  await page.goto('/directories')
  await page.getByRole('button', { name: 'New directory' }).click()
  const directoryPanel = page.getByRole('dialog', { name: 'Create directory' })
  await expect(directoryPanel).toBeVisible()
  await directoryPanel.getByLabel('Name').fill('People')
  await directoryPanel.getByRole('checkbox', { name: /Ada Lovelace/ }).check()
  await directoryPanel.getByRole('button', { name: 'Save directory' }).click()
  expect(directoryPayload).toMatchObject({ name: 'People', member_ids: ['extension-1'] })

  await page.goto('/groups')
  await page.getByRole('button', { name: 'New group' }).click()
  const groupPanel = page.getByRole('dialog', { name: 'Create group' })
  await expect(groupPanel).toBeVisible()
  await groupPanel.getByLabel('Name').fill('Support')
  await groupPanel.locator('select').nth(2).selectOption('extension-1')
  await groupPanel.getByRole('button', { name: 'Add' }).click()
  await groupPanel.getByRole('button', { name: 'Save group' }).click()
  expect(groupPayload).toMatchObject({ name: 'Support', members: [{ type: 'user', id: 'extension-1', weight: 1 }] })
})
