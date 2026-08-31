import process from 'node:process'
import { expect, test } from '@playwright/test'

test('keeps sandbox payment mutations disabled without loading provider code', async ({ page }) => {
  const mutations: string[] = []
  const providerRequests: string[] = []

  page.on('request', (request) => {
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/payments(?:\/|$)/.test(new URL(request.url()).pathname)
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }

    if (new URL(request.url()).hostname.endsWith('authorize.net')) {
      providerRequests.push(request.url())
    }
  })

  const capabilityResponse = page.waitForResponse((response) =>
    /\/api\/v1\/accounts\/[^/]+\/payments\/capabilities$/.test(new URL(response.url()).pathname),
  )

  await page.goto('/services')
  await page.getByRole('button', { name: 'View details' }).click()
  await expect(page.getByRole('heading', { name: 'Sandbox payment verification' })).toBeVisible()

  const response = await capabilityResponse
  const payload = (await response.json()) as {
    data: {
      enabled: boolean
      client: {
        available: boolean
        api_login_id: string | null
        public_client_key: string | null
      }
      mutations: {
        attach_payment_method: boolean
        charge: boolean
        void: boolean
        refund: boolean
      }
    }
  }

  expect(response.status()).toBe(200)
  expect(payload.data.enabled).toBe(false)
  expect(payload.data.client).toMatchObject({
    available: false,
    api_login_id: null,
    public_client_key: null,
  })
  expect(payload.data.mutations).toEqual({
    attach_payment_method: false,
    charge: false,
    void: false,
    refund: false,
  })
  await expect(page.getByText('Sandbox charging is disabled')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Open secure payment form' })).toHaveCount(0)
  await expect(page.locator('iframe[src*="authorize.net"]')).toHaveCount(0)
  expect(mutations).toEqual([])
  expect(providerRequests).toEqual([])
})

test('submits exactly one hosted-tokenized Authorize.Net sandbox charge', async ({ page }) => {
  test.skip(
    process.env.GRID_E2E_RUN_SANDBOX_PAYMENT !== 'true',
    'Requires explicit approval for a sandbox payment mutation.',
  )

  let chargeRequests = 0
  page.on('request', (request) => {
    if (request.method() === 'POST' && request.url().endsWith('/payments/sandbox-charges')) {
      chargeRequests += 1
    }
  })

  await page.goto('/services')
  await page.getByRole('button', { name: 'View details' }).click()
  await expect(page.getByRole('heading', { name: 'Sandbox payment verification' })).toBeVisible()

  const openHostedForm = page.getByRole('button', { name: 'Open secure payment form' })
  await expect(openHostedForm).toBeEnabled()
  await openHostedForm.click()

  const hostedFrame = page.frameLocator('iframe[src*="/acceptMain/acceptMain.html"]')
  await expect
    .poll(async () =>
      hostedFrame.locator('body').evaluate(() => {
        const hostedWindow = window as Window & {
          Accept?: { dispatchData?: unknown }
          encryptEndPoint?: unknown
          isReady?: unknown
        }

        return {
          acceptReady: typeof hostedWindow.Accept?.dispatchData === 'function',
          acceptHandshakeReady: hostedWindow.isReady === true,
          encryptEndpointConfigured:
            typeof hostedWindow.encryptEndPoint === 'string' &&
            hostedWindow.encryptEndPoint.startsWith('https://'),
          hasCryptographicRandomness: typeof hostedWindow.crypto?.getRandomValues === 'function',
          secureContext: hostedWindow.isSecureContext,
        }
      }),
    )
    .toEqual({
      acceptReady: true,
      acceptHandshakeReady: true,
      encryptEndpointConfigured: true,
      hasCryptographicRandomness: true,
      secureContext: true,
    })

  if (process.env.GRID_E2E_PAYMENT_DIAGNOSTIC_ONLY === 'true') return

  await hostedFrame.getByLabel(/card number/i).fill('4111111111111111')
  await hostedFrame.getByLabel(/exp\. date/i).fill('12/30')
  await hostedFrame.getByLabel(/card code/i).fill('900')

  const chargeResponse = page
    .waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        response.url().endsWith('/payments/sandbox-charges'),
    )
    .then((response) => ({ kind: 'response' as const, response }))
  const tokenizationFailure = page
    .getByText(/Authorize\.Net could not tokenize the payment details/)
    .waitFor({ state: 'visible' })
    .then(async () => ({
      kind: 'tokenization-failure' as const,
      message:
        (await page
          .getByText(/Authorize\.Net could not tokenize the payment details/)
          .textContent()) ?? 'Authorize.Net tokenization failed safely.',
    }))
  await hostedFrame.getByRole('button', { name: /submit sandbox payment/i }).click()

  const submission = await Promise.race([chargeResponse, tokenizationFailure])
  if (submission.kind === 'tokenization-failure') {
    throw new Error(submission.message)
  }

  const { response } = submission
  const payload = (await response.json()) as {
    data?: { id?: string; status?: string }
    message?: string
  }

  expect(response.status(), payload.message).toBe(201)
  expect(payload.data?.id).toMatch(
    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
  )
  expect(payload.data?.status).toBe('succeeded')
  expect(JSON.stringify(payload)).not.toContain('4111111111111111')
  expect(JSON.stringify(payload)).not.toContain('900')
  expect(chargeRequests).toBe(1)
  await expect(page.getByText('Payment attempt succeeded')).toBeVisible()
})
