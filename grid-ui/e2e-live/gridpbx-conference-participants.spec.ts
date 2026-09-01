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

const conference = {
  id: 'conference-public-id',
  name: 'Isolated active room',
  owner: null,
  conference_numbers: ['7000'],
  member_numbers: [],
  moderator_numbers: [],
  member_pin_configured: false,
  moderator_pin_configured: false,
  member_join_muted: false,
  member_join_deaf: false,
  member_play_entry_prompt: false,
  moderator_join_muted: false,
  moderator_join_deaf: false,
  max_participants: 20,
  language: 'en-US',
  profile_name: null,
  caller_controls: null,
  moderator_controls: null,
  play_name: false,
  play_welcome: true,
  require_moderator: false,
  wait_for_moderator: false,
  max_members_media: null,
  entry_tone: { mode: 'enabled', media: null },
  exit_tone: { mode: 'enabled', media: null },
  runtime: { members: 1, moderators: 0, duration_seconds: 75, is_locked: false },
  sync_status: 'healthy',
  last_synced_at: null,
}

function conferencePage() {
  return {
    data: [conference],
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 },
  }
}

test('manages an isolated active participant using only an opaque public handle', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const opaqueHandle = 'encrypted-account-conference-participant-handle'
  const mediaId = '11111111-1111-4111-8111-111111111111'
  let canSpeak = true
  let canHear = false
  const commands: Array<{ participant_id: string; action: string }> = []
  const bulkCommands: Array<{
    action: string
    expected_participant_count: number
    expected_target_count: number
    confirmation: boolean
  }> = []
  const playbackCommands: Array<{
    media_id: string
    participant_id: string | null
    confirmation: boolean
  }> = []

  await page.route(/\/api\/v1\/accounts\/[^/]+\/conferences\/options$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          owners: [],
          media: [],
          playable_media: [{ id: mediaId, label: 'Welcome message', detail: 'Streamable audio' }],
        },
      }),
    })
  })

  await page.route(/\/api\/v1\/accounts\/[^/]+\/conferences(?:\?.*)?$/, async (route) => {
    if (route.request().method() !== 'GET') return route.continue()

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(conferencePage()),
    })
  })
  await page.route(
    /\/api\/v1\/accounts\/[^/]+\/conferences\/conference-public-id\/participants(?:\?.*)?$/,
    async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: opaqueHandle,
              display_name: 'Ada Lovelace',
              number: '1001',
              is_moderator: false,
              can_speak: canSpeak,
              can_hear: canHear,
              duration_seconds: 75,
            },
          ],
        }),
      })
    },
  )
  await page.route(
    /\/api\/v1\/accounts\/[^/]+\/conferences\/conference-public-id\/playback$/,
    async (route) => {
      const command = route.request().postDataJSON() as {
        media_id: string
        participant_id: string | null
        confirmation: boolean
      }
      playbackCommands.push(command)
      await route.fulfill({
        status: 202,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            accepted: true,
            action: 'play_media',
            target: command.participant_id === null ? 'room' : 'participant',
            message: 'Switch accepted the media playback request.',
          },
        }),
      })
    },
  )
  await page.route(
    /\/api\/v1\/accounts\/[^/]+\/conferences\/conference-public-id\/participants\/bulk-commands$/,
    async (route) => {
      const command = route.request().postDataJSON() as {
        action: string
        expected_participant_count: number
        expected_target_count: number
        confirmation: boolean
      }
      bulkCommands.push(command)
      if (command.action === 'mute') canSpeak = false
      await route.fulfill({
        status: 202,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            accepted: true,
            action: command.action,
            targeted_participants: command.expected_target_count,
            skipped_moderators: 0,
            message: 'Switch accepted the room-wide participant request.',
          },
        }),
      })
    },
  )
  await page.route(
    /\/api\/v1\/accounts\/[^/]+\/conferences\/conference-public-id\/participants\/commands$/,
    async (route) => {
      const command = route.request().postDataJSON() as {
        participant_id: string
        action: string
      }
      commands.push(command)
      if (command.action === 'mute') canSpeak = false
      if (command.action === 'unmute') canSpeak = true
      if (command.action === 'undeaf') canHear = true
      await route.fulfill({
        status: 202,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            accepted: true,
            action: command.action,
            message: `Switch accepted the participant ${command.action} request.`,
          },
        }),
      })
    },
  )
  await page.route(/\/api\/v1\/accounts\/[^/]+\/sync\/conferences$/, async (route) => {
    await route.fulfill({
      status: 202,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { id: 'sync-public-id', status: 'succeeded', error_message: null },
      }),
    })
  })

  await page.goto('/conferences')
  await expect(page.getByText('Isolated active room', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Manage live room' }).click()
  await expect(page.getByRole('heading', { name: 'Isolated active room' })).toBeVisible()
  await expect(page.getByText('Ada Lovelace')).toBeVisible()
  await expect(page.getByText('1001 · 1:15')).toBeVisible()
  await expect(page.getByText('Muted')).toHaveCount(0)
  await expect(page.getByText('Deafened')).toBeVisible()
  await expect(page.getByText('participant-42')).toHaveCount(0)
  await expect(page.getByText('raw-call-id')).toHaveCount(0)

  await page.getByRole('button', { name: /Mute members/ }).click()
  await expect(page.getByText('Confirm mute for 1 eligible participant(s)?')).toBeVisible()
  await page.getByRole('button', { name: 'Confirm room command' }).click()
  await expect(page.getByText('Muted')).toBeVisible()
  await expect(page.getByRole('status')).toContainText('State observed')
  await expect(page.getByRole('status')).toContainText(
    'Observed the requested state for all 1 targeted participant(s).',
  )

  await page.getByRole('button', { name: 'Conference media' }).click()
  await page.getByRole('option', { name: /Welcome message/ }).click()
  await page.getByRole('button', { name: 'Play audio' }).click()
  await expect(page.getByRole('button', { name: 'Confirm playback' })).toBeVisible()
  await page.getByRole('button', { name: 'Confirm playback' }).click()

  await page.getByRole('button', { name: 'Conference playback target' }).click()
  await page.getByRole('option', { name: /Ada Lovelace/ }).click()
  await page.getByRole('button', { name: 'Play audio' }).click()
  await expect(page.getByRole('button', { name: 'Confirm playback' })).toBeVisible()
  await page.getByRole('button', { name: 'Confirm playback' }).click()

  await page.getByRole('button', { name: 'Unmute', exact: true }).click()
  await expect(page.getByText('Speaking enabled')).toBeVisible()
  await page.getByRole('button', { name: 'Restore hearing', exact: true }).click()
  await expect(page.getByText('Hearing enabled')).toBeVisible()

  expect(commands).toEqual([
    { participant_id: opaqueHandle, action: 'unmute' },
    { participant_id: opaqueHandle, action: 'undeaf' },
  ])
  expect(bulkCommands).toEqual([
    {
      action: 'mute',
      expected_participant_count: 1,
      expected_target_count: 1,
      confirmation: true,
    },
  ])
  expect(playbackCommands).toEqual([
    { media_id: mediaId, participant_id: null, confirmation: true },
    { media_id: mediaId, participant_id: opaqueHandle, confirmation: true },
  ])
  expect(JSON.stringify(playbackCommands)).not.toContain('switch-media-id')
  expect(JSON.stringify(playbackCommands)).not.toContain('participant-42')
  expect(JSON.stringify(commands)).not.toContain('participant-42')
  expect(issues).toEqual([])
})
