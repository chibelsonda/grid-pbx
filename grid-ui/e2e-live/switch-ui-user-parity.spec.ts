import process from 'node:process'
import { expect, test, type Locator, type Page } from '@playwright/test'

type VisibleControl = {
  id: string | null
  label: string | null
  name: string | null
  tag: string
  type: string | null
}

async function visibleControls(container: Locator): Promise<VisibleControl[]> {
  return container
    .locator('input:visible, select:visible, textarea:visible')
    .evaluateAll((elements) =>
      elements.map((element) => {
        const control = element as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
        const id = control.id || null
        const explicitLabel = id
          ? document.querySelector<HTMLLabelElement>(`label[for="${CSS.escape(id)}"]`)
          : null
        const wrappingLabel = control.closest('label')

        return {
          id,
          label:
            (explicitLabel?.textContent ?? wrappingLabel?.textContent ?? '')
              .replace(/\s+/g, ' ')
              .trim() || null,
          name: control.getAttribute('name'),
          tag: control.tagName.toLowerCase(),
          type: control.getAttribute('type'),
        }
      }),
    )
}

async function openAuthenticatedCallflows(page: Page): Promise<void> {
  const apiKey = process.env.SWITCH_API_KEY
  const apiUrl = (process.env.SWITCH_E2E_API_URL ?? 'http://127.0.0.1:8000/v2').replace(/\/$/, '')

  if (!apiKey) {
    await page.goto('/#/apps/callflows')
    return
  }

  const authResponse = await fetch(`${apiUrl}/api_auth`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ data: { api_key: apiKey } }),
  })
  const authPayload = (await authResponse.json()) as {
    auth_token?: string
    data?: { account_id?: string }
  }

  if (!authResponse.ok || !authPayload.auth_token || !authPayload.data?.account_id) {
    throw new Error(`Switch API-key authentication failed (${authResponse.status}).`)
  }

  const usersResponse = await fetch(
    `${apiUrl}/accounts/${encodeURIComponent(authPayload.data.account_id)}/users?paginate=false`,
    { headers: { 'X-Auth-Token': authPayload.auth_token } },
  )
  const usersPayload = (await usersResponse.json()) as {
    data?: Array<{ id?: string; priv_level?: string }>
  }
  const ownerId =
    process.env.SWITCH_E2E_USER_ID ??
    usersPayload.data?.find(({ priv_level }) => priv_level === 'admin')?.id ??
    usersPayload.data?.[0]?.id

  if (!usersResponse.ok || !ownerId) {
    throw new Error(`Switch interactive-user discovery failed (${usersResponse.status}).`)
  }

  await page.goto('/')
  await page.waitForFunction('Boolean(window.monster?.apps?.auth)')
  await page.evaluate(
    async ({ authToken, interactiveOwnerId }) => {
      type AuthData = { data: { owner_id?: string } }
      type MonsterAuth = {
        authenticateAuthToken: (
          token: string,
          success: (data: AuthData) => void,
          error: (cause: unknown) => void,
        ) => void
        _afterSuccessfulAuth: (data: AuthData) => void
      }
      const runtime = window as unknown as { monster: { apps: { auth: MonsterAuth } } }
      const auth = runtime.monster.apps.auth

      await new Promise<void>((resolve, reject) => {
        auth.authenticateAuthToken(
          authToken,
          (data) => {
            data.data.owner_id = interactiveOwnerId
            auth._afterSuccessfulAuth(data)
            resolve()
          },
          reject,
        )
      })

      window.location.hash = '/apps/callflows'
    },
    { authToken: authPayload.auth_token, interactiveOwnerId: ownerId },
  )
  await page.waitForFunction('window.monster?.apps?.auth?.appFlags?.isAuthentified === true')
}

test('audits every visible Basic and Advanced User control without submitting', async ({
  page,
}, testInfo) => {
  await openAuthenticatedCallflows(page)
  await page.locator('.entity-manager .entity-element[data-type="user"]').click()
  await page.locator('.entity-edition .list-add').click()

  const editor = page.locator('#user-form')
  await expect(editor).toBeVisible()

  const basicControls = await visibleControls(editor)

  await page.locator('.view-buttons .advanced').click()

  const tabs = page.locator('ul.tabs:visible > li > a')
  const tabLabels = (await tabs.allTextContents()).map((label) => label.replace(/\s+/g, ' ').trim())
  const advancedControls: Record<string, VisibleControl[]> = {}

  for (let index = 0; index < (await tabs.count()); index += 1) {
    const tab = tabs.nth(index)
    const label = (await tab.textContent())?.replace(/\s+/g, ' ').trim() ?? `Tab ${index + 1}`
    await tab.click()

    if (label === 'Hot Desking') {
      await page.locator('#hotdesk_require_pin').check()
      await expect(page.locator('#hotdesk_pin')).toBeVisible()
    }

    advancedControls[label] = await visibleControls(editor)
  }

  await testInfo.attach('switch-user-form-audit.json', {
    body: JSON.stringify({ basicControls, tabLabels, advancedControls }, null, 2),
    contentType: 'application/json',
  })

  expect(tabLabels).toContain('Basic')
  expect(tabLabels).toContain('Caller ID')
  expect(tabLabels).toContain('Options')
  expect(tabLabels).toContain('Call Forward')
  expect(tabLabels).toContain('Hot Desking')
  expect(tabLabels).toContain('Restrictions')
})
