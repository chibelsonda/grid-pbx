import { existsSync, readFileSync } from 'node:fs'
import process from 'node:process'

import { expect, test, type Page } from '@playwright/test'

type PublicCallflowNode = {
  module?: string
  settings?: Record<string, unknown>
  children?: Record<string, PublicCallflowNode>
}

type RingGroupMediaLegEvidence = {
  route_name: string
  observer: 'freeswitch_esl'
  carrier_attempts: number
  legs: Array<{
    direction: 'internal' | 'external'
    call_id: string
    source: 'account_local_sip'
    alert_info: string
    ringback_media_matches_seed: boolean
    audible_ringback: boolean
  }>
}

function verifyMediaLegEvidence(evidence: RingGroupMediaLegEvidence, routeName: string): void {
  expect(evidence).toMatchObject({
    route_name: routeName,
    observer: 'freeswitch_esl',
    carrier_attempts: 0,
  })
  expect(evidence.legs).toHaveLength(2)
  expect(new Set(evidence.legs.map(({ call_id }) => call_id.trim())).size).toBe(2)

  for (const [direction, alertInfo] of [
    ['internal', 'internal-ring'],
    ['external', 'external-ring'],
  ] as const) {
    expect(evidence.legs).toContainEqual({
      direction,
      call_id: expect.stringMatching(/\S/),
      source: 'account_local_sip',
      alert_info: alertInfo,
      ringback_media_matches_seed: true,
      audible_ringback: true,
    })
  }
}

function findCallflowNode(
  node: PublicCallflowNode | null | undefined,
  module: string,
): PublicCallflowNode | null {
  if (!node) return null
  if (node.module === module) return node

  for (const child of Object.values(node.children ?? {})) {
    const match = findCallflowNode(child, module)
    if (match) return match
  }

  return null
}

async function openRoute(page: Page, routeName: string): Promise<void> {
  await page.goto('/call-routing')
  const routeSearch = page.getByRole('searchbox', { name: 'Search call routes' })
  await routeSearch.fill(routeName)
  await page.getByRole('button', { name: 'Apply filters' }).click()
  await page.getByRole('button', { name: `View ${routeName}` }).click()
}

async function deleteRoute(page: Page, routeName: string): Promise<void> {
  const closePanel = page.getByRole('button', { name: 'Close panel' })
  if (await closePanel.isVisible().catch(() => false)) await closePanel.click()

  let workspace = page.getByRole('region', { name: 'Callflow workspace' })
  if (!(await workspace.isVisible().catch(() => false))) {
    await openRoute(page, routeName)
    workspace = page.getByRole('region', { name: 'Callflow workspace' })
  }
  await workspace.getByRole('button', { name: 'Delete route' }).click()
  const confirmation = page.getByRole('dialog', { name: 'Delete this route?' })
  const response = page.waitForResponse(
    (candidate) =>
      candidate.request().method() === 'DELETE' &&
      /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+$/.test(new URL(candidate.url()).pathname),
  )
  await confirmation.getByRole('button', { name: 'Delete route' }).click()
  expect((await response).status()).toBe(204)
}

test('verifies weighted Ring Group guided inline actions', async ({ page }) => {
  const routeName = process.env.GRID_E2E_CALL_PRIORITY_ROUTE_NAME?.trim()
  const seedFile = process.env.GRID_E2E_RING_GROUP_SEED_FILE?.trim()
  const seed = seedFile
    ? (JSON.parse(readFileSync(seedFile, 'utf8')) as {
        raw_device_id?: string
        raw_media_id?: string
        media_label?: string
      })
    : null
  const rawDeviceId =
    process.env.GRID_E2E_RING_GROUP_RAW_DEVICE_ID?.trim() ?? seed?.raw_device_id?.trim()
  const rawMediaId =
    process.env.GRID_E2E_RING_GROUP_RAW_MEDIA_ID?.trim() ?? seed?.raw_media_id?.trim()
  const mediaLabel =
    process.env.GRID_E2E_RING_GROUP_MEDIA_LABEL?.trim() ?? seed?.media_label?.trim()
  const privateMarker = process.env.GRID_E2E_RING_GROUP_PRIVATE_MARKER?.trim()
  const privateReadyFile = process.env.GRID_E2E_RING_GROUP_PRIVATE_READY_FILE?.trim()
  const verificationFile = process.env.GRID_E2E_RING_GROUP_VERIFICATION_FILE?.trim()
  const mediaLegVerificationFile = process.env.GRID_E2E_RING_GROUP_MEDIA_LEG_FILE?.trim()

  test.setTimeout(mediaLegVerificationFile ? 150_000 : 60_000)

  test.skip(!routeName, 'Set GRID_E2E_CALL_PRIORITY_ROUTE_NAME to a disposable route.')

  let opened = false

  try {
    await openRoute(page, routeName!)
    opened = true

    const workspace = page.getByRole('region', { name: 'Callflow workspace' })
    const diagram = workspace.getByRole('tree', { name: 'Callflow diagram' })
    await diagram.getByRole('treeitem').nth(1).click()
    await page.getByRole('dialog').getByRole('button', { name: 'Close node information' }).click()

    const palette = workspace.getByRole('region', { name: 'Callflow action catalog' })
    const basicCategory = palette.getByRole('button', { name: /^Basic/ })
    if ((await basicCategory.getAttribute('aria-expanded')) !== 'true') await basicCategory.click()
    await palette.getByTitle('Ring Group · ring_group · Guided now').click()

    const addRingGroup = page.getByRole('dialog', { name: 'Add Ring Group' })
    await addRingGroup.getByRole('button', { name: 'Add Ring Group device' }).click()
    const deviceOption = page.getByRole('option').first()
    await deviceOption.click()
    await addRingGroup.getByRole('spinbutton', { name: 'Device 1 delay' }).fill('5')
    await addRingGroup.getByRole('spinbutton', { name: 'Attempts' }).fill('2')
    await addRingGroup.getByRole('button', { name: 'Ringback audio' }).click()
    const ringbackOption = mediaLabel
      ? page.getByRole('option', { name: mediaLabel })
      : page.getByRole('option').filter({ hasNotText: 'Switch default' }).first()
    await ringbackOption.click()
    await addRingGroup.getByRole('textbox', { name: 'Internal phone alert' }).fill('internal-ring')
    await addRingGroup.getByRole('textbox', { name: 'External phone alert' }).fill('external-ring')

    const createResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await addRingGroup.getByRole('button', { name: 'Add action' }).click()
    const created = await createResponse
    expect(created.status()).toBe(200)
    const createdPayload = created.request().postDataJSON()
    expect(createdPayload).toMatchObject({
      module: 'ring_group',
      data: {
        strategy: 'simultaneous',
        endpoints: [{ delay: 5, timeout: 20 }],
        repeats: 2,
        ignore_forward: true,
        fail_on_single_reject: false,
        ringtone_internal: 'internal-ring',
        ringtone_external: 'external-ring',
        skip_module: false,
      },
    })
    const publicDeviceId = createdPayload.data.endpoints[0].device_id as string
    const publicMediaId = createdPayload.data.ringback_media_id as string
    expect(publicDeviceId).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    )
    expect(publicMediaId).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    )
    const createdBody = (await created.json()) as { data: { flow?: PublicCallflowNode | null } }
    expect(findCallflowNode(createdBody.data.flow, 'ring_group')).toMatchObject({
      reference_status: 'resolved',
      settings: {
        supported_configuration: true,
        strategy: 'simultaneous',
        endpoints: [{ device_id: publicDeviceId, delay: 5, timeout: 20 }],
        repeats: 2,
        ignore_forward: true,
        fail_on_single_reject: false,
        ringback_media_id: publicMediaId,
        ringtone_internal: 'internal-ring',
        ringtone_external: 'external-ring',
        skip_module: false,
      },
    })
    if (rawDeviceId) expect(JSON.stringify(createdBody)).not.toContain(rawDeviceId)
    if (rawMediaId) expect(JSON.stringify(createdBody)).not.toContain(rawMediaId)
    if (privateMarker) expect(JSON.stringify(createdBody)).not.toContain(privateMarker)

    if (privateReadyFile) {
      await expect.poll(() => existsSync(privateReadyFile), { timeout: 20_000 }).toBe(true)
    }

    if (mediaLegVerificationFile) {
      await expect.poll(() => existsSync(mediaLegVerificationFile), { timeout: 90_000 }).toBe(true)
      verifyMediaLegEvidence(
        JSON.parse(readFileSync(mediaLegVerificationFile, 'utf8')) as RingGroupMediaLegEvidence,
        routeName!,
      )
    }

    const ringGroupNode = diagram.getByRole('treeitem', { name: 'Ring Group' })
    await ringGroupNode.click()
    await page.getByRole('dialog').getByRole('button', { name: 'Edit action target' }).click()
    const editRingGroup = page.getByRole('dialog', { name: 'Edit Ring Group' })
    await expect(editRingGroup.getByRole('button', { name: 'Ring strategy' })).toContainText(
      'At the same time',
    )
    await editRingGroup.getByRole('button', { name: 'Ring strategy' }).click()
    await page.getByRole('option', { name: 'Weighted random order' }).click()
    await expect(editRingGroup.getByRole('spinbutton', { name: 'Device 1 delay' })).toHaveValue('0')
    await editRingGroup.getByRole('spinbutton', { name: 'Device 1 timeout' }).fill('30')
    await editRingGroup.getByRole('spinbutton', { name: 'Device 1 weight' }).fill('75')
    await editRingGroup.getByRole('spinbutton', { name: 'Attempts' }).fill('1')
    await editRingGroup.getByRole('checkbox', { name: 'Ignore device forwarding' }).uncheck()
    await editRingGroup.getByRole('checkbox', { name: 'Stop when one device rejects' }).check()
    await expect(editRingGroup.getByRole('button', { name: 'Ringback audio' })).toContainText(
      mediaLabel ?? '',
    )
    await editRingGroup
      .getByRole('textbox', { name: 'Internal phone alert' })
      .fill('internal-priority')
    await editRingGroup
      .getByRole('textbox', { name: 'External phone alert' })
      .fill('external-priority')
    await editRingGroup.getByRole('switch', { name: 'Skip this action' }).click()

    const updateResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PATCH' &&
        /\/api\/v1\/accounts\/[^/]+\/callflows\/[^/]+\/tree\/inline-nodes$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await editRingGroup.getByRole('button', { name: 'Save action' }).click()
    const updated = await updateResponse
    expect(updated.status()).toBe(200)
    expect(updated.request().postDataJSON()).toMatchObject({
      module: 'ring_group',
      data: {
        strategy: 'weighted_random',
        endpoints: [{ device_id: publicDeviceId, delay: 0, timeout: 30, weight: 75 }],
        repeats: 1,
        ignore_forward: false,
        fail_on_single_reject: true,
        ringback_media_id: publicMediaId,
        ringtone_internal: 'internal-priority',
        ringtone_external: 'external-priority',
        skip_module: true,
      },
    })
    const updatedBody = (await updated.json()) as { data: { flow?: PublicCallflowNode | null } }
    const updatedNode = findCallflowNode(updatedBody.data.flow, 'ring_group')
    expect(updatedNode).toMatchObject({
      reference_status: 'resolved',
      settings: {
        supported_configuration: true,
        strategy: 'weighted_random',
        endpoints: [{ device_id: publicDeviceId, delay: 0, timeout: 30, weight: 75 }],
        repeats: 1,
        ignore_forward: false,
        fail_on_single_reject: true,
        ringback_media_id: publicMediaId,
        ringtone_internal: 'internal-priority',
        ringtone_external: 'external-priority',
        skip_module: true,
      },
    })
    expect(updatedNode?.settings).not.toHaveProperty('timeout')
    expect(updatedNode?.settings).not.toHaveProperty('ringback')
    expect(updatedNode?.settings).not.toHaveProperty('ringtones')
    expect(JSON.stringify(updatedBody)).not.toContain('attempted-unknown-marker')
    if (rawDeviceId) expect(JSON.stringify(updatedBody)).not.toContain(rawDeviceId)
    if (rawMediaId) expect(JSON.stringify(updatedBody)).not.toContain(rawMediaId)
    if (privateMarker) expect(JSON.stringify(updatedBody)).not.toContain(privateMarker)

    if (verificationFile) {
      await expect.poll(() => existsSync(verificationFile), { timeout: 20_000 }).toBe(true)
      expect(JSON.parse(readFileSync(verificationFile, 'utf8'))).toMatchObject({
        strategy: 'weighted_random',
        repeats: 1,
        timeout: 30,
        ignore_forward: false,
        fail_on_single_reject: true,
        ringback: rawMediaId,
        ringtones_internal: 'internal-priority',
        ringtones_external: 'external-priority',
        unknown_marker_retained: true,
        skip_module: true,
        endpoint_type: 'device',
        raw_device_matches_seed: true,
        ...(rawDeviceId ? { id: rawDeviceId } : {}),
        delay: 0,
        endpoint_timeout: 30,
        weight: 75,
      })
    }

    await ringGroupNode.click()
    await page.getByRole('dialog').getByRole('button', { name: 'Edit action target' }).click()
    const reopened = page.getByRole('dialog', { name: 'Edit Ring Group' })
    await expect(reopened.getByRole('button', { name: 'Ring strategy' })).toContainText(
      'Weighted random order',
    )
    await expect(reopened.getByRole('spinbutton', { name: 'Device 1 delay' })).toHaveValue('0')
    await expect(reopened.getByRole('spinbutton', { name: 'Device 1 timeout' })).toHaveValue('30')
    await expect(reopened.getByRole('spinbutton', { name: 'Device 1 weight' })).toHaveValue('75')
    await expect(reopened.getByRole('spinbutton', { name: 'Attempts' })).toHaveValue('1')
    await expect(reopened.getByRole('button', { name: 'Ringback audio' })).toContainText(
      mediaLabel ?? '',
    )
    await expect(reopened.getByRole('textbox', { name: 'Internal phone alert' })).toHaveValue(
      'internal-priority',
    )
    await expect(reopened.getByRole('textbox', { name: 'External phone alert' })).toHaveValue(
      'external-priority',
    )
    await expect(
      reopened.getByRole('checkbox', { name: 'Ignore device forwarding' }),
    ).not.toBeChecked()
    await expect(
      reopened.getByRole('checkbox', { name: 'Stop when one device rejects' }),
    ).toBeChecked()
    await expect(reopened.getByRole('switch', { name: 'Skip this action' })).toHaveAttribute(
      'aria-checked',
      'true',
    )
  } finally {
    if (opened) await deleteRoute(page, routeName!)
  }
})
