import { chromium, type FullConfig } from '@playwright/test'
import { mkdir, writeFile } from 'node:fs/promises'
import process from 'node:process'

const authDirectory = '.playwright/.auth'
const emptyStorageState = JSON.stringify({ cookies: [], origins: [] })

async function saveGridPbxSession(baseURL: string): Promise<void> {
  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({
    baseURL,
    ignoreHTTPSErrors: process.env.GRID_E2E_IGNORE_HTTPS_ERRORS === 'true',
  })
  const page = await context.newPage()

  try {
    await page.goto('/login')

    if (new URL(page.url()).pathname.includes('/login')) {
      await page
        .getByLabel('Email address')
        .fill(process.env.GRID_E2E_EMAIL ?? 'admin@gridpbx.local')
      await page.getByLabel('Password').fill(process.env.GRID_E2E_PASSWORD ?? 'admin-change-me')
      await page.getByRole('button', { name: 'Sign in' }).click()
      await page.waitForURL((url) => !url.pathname.includes('/login'))
    }

    await context.storageState({ path: `${authDirectory}/gridpbx.json` })
  } finally {
    await browser.close()
  }
}

async function saveSwitchUiSession(baseURL: string): Promise<void> {
  const username = process.env.SWITCH_E2E_USERNAME
  const password = process.env.SWITCH_E2E_PASSWORD
  const accountName = process.env.SWITCH_E2E_ACCOUNT_NAME

  if (!username || !password || !accountName) {
    await writeFile(`${authDirectory}/switch-ui.json`, emptyStorageState)
    return
  }

  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({ baseURL })
  const page = await context.newPage()

  try {
    await page.goto('/')
    await page.locator('#login').fill(username)
    await page.locator('#password').fill(password)
    await page.locator('#account_name').fill(accountName)
    await page.locator('#form_login button.login').click()
    await page.waitForFunction(() => !document.querySelector('#form_login'))
    await context.storageState({ path: `${authDirectory}/switch-ui.json` })
  } finally {
    await browser.close()
  }
}

export default async function globalSetup(config: FullConfig): Promise<void> {
  await mkdir(authDirectory, { recursive: true })

  const gridProject = config.projects.find(({ name }) => name === 'gridpbx-live')
  const switchProject = config.projects.find(({ name }) => name === 'switch-ui-reference')

  if (gridProject) {
    await saveGridPbxSession(String(gridProject.use.baseURL))
  } else {
    await writeFile(`${authDirectory}/gridpbx.json`, emptyStorageState)
  }

  if (switchProject) {
    await saveSwitchUiSession(String(switchProject.use.baseURL))
  } else {
    await writeFile(`${authDirectory}/switch-ui.json`, emptyStorageState)
  }
}
