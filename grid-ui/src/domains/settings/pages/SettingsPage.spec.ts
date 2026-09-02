import { mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import { useUiStore } from '@/app/stores/uiStore'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import type { Account } from '@/domains/accounts/types/account'
import { useAuthStore } from '@/domains/auth/stores/authStore'
import FormListbox from '@/shared/components/FormListbox.vue'
import FormFileInput from '@/shared/components/FormFileInput.vue'
import SettingsPage from './SettingsPage.vue'

vi.mock('@/domains/call-routing/api/callflowIntegrationProfileApi', () => ({
  callflowIntegrationProfileApi: {
    list: vi.fn().mockResolvedValue([]),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
  },
}))

const permissions: Account['permissions'] = {
  can_manage_extensions: true,
  can_manage_devices: true,
  can_manage_voicemail: true,
  can_manage_call_routing: true,
  can_manage_media: true,
  can_sync_call_detail_records: true,
  can_view_services: true,
  can_manage_account_settings: true,
  can_onboard_descendants: true,
}

function account(id: string, name: string): Account {
  return {
    id,
    name,
    realm: null,
    timezone: 'America/New_York',
    enabled: true,
    organization: { id: `organization-${id}`, name: `${name} Organization` },
    organization_role: 'account_admin',
    permissions,
  }
}

async function mountPage() {
  window.localStorage.clear()
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  const accounts = useAccountStore()
  const ui = useUiStore()
  auth.user = { id: 'user-public-id', name: 'Grid Admin', email: 'admin@example.test' }
  accounts.accounts = [account('account-one', 'GridPBX'), account('account-two', 'Branch Office')]
  accounts.selectedId = 'account-one'

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/settings', component: SettingsPage },
      { path: '/accounts', component: { template: '<div />' } },
      { path: '/system-status', component: { template: '<div />' } },
      { path: '/reseller', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
    ],
  })
  await router.push('/settings')
  await router.isReady()

  const wrapper = mount(SettingsPage, { global: { plugins: [pinia, router] } })

  return { accounts, auth, router, ui, wrapper }
}

async function selectSettingsTab(wrapper: VueWrapper, label: string): Promise<void> {
  const tab = wrapper.findAll('[role="tab"]').find((candidate) => candidate.text() === label)
  if (!tab) throw new Error(`Settings tab not found: ${label}`)

  await tab.trigger('click')
  await wrapper.vm.$nextTick()
}

describe('SettingsPage', () => {
  it('shows personal identity, account-scoped access, and owning-domain links', async () => {
    const { router, wrapper } = await mountPage()

    expect(wrapper.get('h1').text()).toBe('Settings')
    expect(wrapper.text()).toContain('Grid Admin')
    expect(wrapper.text()).toContain('admin@example.test')
    expect(wrapper.text()).not.toContain('Scheduled for a later slice')

    const sectionNavigation = wrapper.get('nav[aria-label="Settings sections"]')
    const sectionTabs = sectionNavigation.findAll('[role="tab"]')
    expect(sectionTabs.map((tab) => tab.text())).toEqual([
      'Profile',
      'Branding',
      'Appearance',
      'Workspace',
      'Administration',
      'Callflow integrations',
      'Access & security',
    ])
    expect(sectionTabs[0]?.attributes('aria-selected')).toBe('true')
    expect(wrapper.find('#profile').exists()).toBe(true)
    expect(wrapper.get('#access-security').isVisible()).toBe(false)

    await selectSettingsTab(wrapper, 'Access & security')
    await vi.waitFor(() => expect(router.currentRoute.value.hash).toBe('#access-security'))
    expect(wrapper.get('#profile').isVisible()).toBe(false)
    expect(wrapper.get('#access-security').isVisible()).toBe(true)
    expect(wrapper.get('#access-security').text()).toContain('Account Admin')

    await selectSettingsTab(wrapper, 'Administration')
    await vi.waitFor(() => expect(router.currentRoute.value.hash).toBe('#administration'))
    expect(wrapper.get('a[href="/accounts"]').text()).toContain('Account configuration')
    expect(wrapper.get('a[href="/system-status"]').text()).toContain('System status')
    expect(wrapper.get('a[href="/reseller"]').text()).toContain('Reseller administration')

    await selectSettingsTab(wrapper, 'Callflow integrations')
    expect(wrapper.get('#callflow-integrations').text()).toContain(
      'No integration profiles configured',
    )
    expect(wrapper.get('#callflow-integrations').text()).toContain('Create integration')
    expect(wrapper.get('#callflow-integrations').text()).not.toContain('Create Pivot profile')
    expect(wrapper.get('#callflow-integrations').text()).not.toContain('Create Webhook profile')
    expect(wrapper.get('#callflow-integrations').text()).not.toMatch(
      /voice_url|custom_request_headers/,
    )
  })

  it('selects only a mapped public account reference through the shared listbox', async () => {
    const { accounts, wrapper } = await mountPage()
    await selectSettingsTab(wrapper, 'Workspace')
    const accountSelector = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Settings workspace account')

    expect(accountSelector?.props('modelValue')).toBe('account-one')
    expect(accountSelector?.attributes('class')).toContain('max-w-xl')
    expect(accountSelector?.props('options')).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ value: 'account-two', label: 'Branch Office' }),
      ]),
    )
    accountSelector?.vm.$emit('update:modelValue', 'account-two')
    await wrapper.vm.$nextTick()

    expect(accounts.selectedId).toBe('account-two')
    expect(window.localStorage.getItem('gridpbx:selected-account')).toBe('account-two')
    expect(wrapper.text()).not.toContain('switch-account')
  })

  it('opens the shared theme customizer and persists compact-sidebar changes', async () => {
    const { ui, wrapper } = await mountPage()

    await selectSettingsTab(wrapper, 'Appearance')
    await wrapper.get('[aria-label="Customize appearance"]').trigger('click')
    expect(ui.themePanelOpen).toBe(true)

    await selectSettingsTab(wrapper, 'Workspace')
    await wrapper.get('[role="switch"]').trigger('click')
    expect(ui.sidebarCollapsed).toBe(true)
    expect(window.localStorage.getItem('gridpbx.sidebar-collapsed.v1')).toBe('true')
  })

  it('persists the validated sidebar branding display preference', async () => {
    const { ui, wrapper } = await mountPage()

    await selectSettingsTab(wrapper, 'Workspace')
    const brandingSelector = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Sidebar branding display')

    expect(brandingSelector?.props('modelValue')).toBe('logo-and-name')
    expect(brandingSelector?.attributes('class')).toContain('max-w-md')
    expect(brandingSelector?.props('options')).toEqual([
      expect.objectContaining({ value: 'logo-and-name', label: 'Logo and company name' }),
      expect.objectContaining({ value: 'logo-only', label: 'Logo only' }),
    ])

    brandingSelector?.vm.$emit('update:modelValue', 'logo-only')
    await wrapper.vm.$nextTick()

    expect(ui.sidebarBrandDisplay).toBe('logo-only')
    expect(window.localStorage.getItem('gridpbx.sidebar-brand-display.v1')).toBe('logo-only')
  })

  it('validates and saves only the application display name', async () => {
    const { auth, wrapper } = await mountPage()
    const updateProfile = vi.spyOn(auth, 'updateProfile').mockImplementation(async (input) => {
      auth.user = { ...auth.user!, name: input.name }
      return true
    })

    await wrapper.get('[aria-label="Edit display name"]').trigger('click')
    await wrapper.get('input[name="name"]').setValue('   ')
    await wrapper.get('form[aria-label="Edit profile"]').trigger('submit')

    expect(wrapper.text()).toContain('Enter your display name.')
    expect(updateProfile).not.toHaveBeenCalled()

    await wrapper.get('input[name="name"]').setValue('Operations Admin')
    await wrapper.get('form[aria-label="Edit profile"]').trigger('submit')

    expect(updateProfile).toHaveBeenCalledWith({ name: 'Operations Admin' })
    expect(wrapper.text()).toContain('Operations Admin')
  })

  it('validates and submits organization branding only for authorized account settings roles', async () => {
    const { accounts, wrapper } = await mountPage()
    const uploadLogo = vi.spyOn(accounts, 'uploadOrganizationLogo').mockResolvedValue(true)
    await selectSettingsTab(wrapper, 'Branding')
    const logoInput = wrapper.findComponent(FormFileInput)

    expect(logoInput.props('dropzone')).toBe(true)
    expect(logoInput.props('dropPrompt')).toBe('Drag and drop your logo here')
    expect(wrapper.get('form[aria-label="Organization branding"]').attributes('novalidate')).toBe(
      '',
    )

    await wrapper.get('form[aria-label="Organization branding"]').trigger('submit')
    expect(wrapper.text()).toContain('Choose a logo image.')
    expect(uploadLogo).not.toHaveBeenCalled()

    logoInput.vm.$emit(
      'update:modelValue',
      new File(['svg'], 'brand.svg', { type: 'image/svg+xml' }),
    )
    await wrapper.vm.$nextTick()
    await wrapper.get('form[aria-label="Organization branding"]').trigger('submit')

    expect(wrapper.text()).toContain('Choose a PNG, JPEG, or WebP image.')
    expect(uploadLogo).not.toHaveBeenCalled()

    const png = new File(['png'], 'brand.png', { type: 'image/png' })
    logoInput.vm.$emit('update:modelValue', png)
    await wrapper.vm.$nextTick()
    await wrapper.get('form[aria-label="Organization branding"]').trigger('submit')

    expect(uploadLogo).toHaveBeenCalledWith(png)
    expect(wrapper.text()).toContain('does not modify Switch/Kazoo whitelabel settings')
  })
})
