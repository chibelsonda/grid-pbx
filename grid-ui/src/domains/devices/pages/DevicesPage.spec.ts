import { defineComponent } from 'vue'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import type { Account } from '@/domains/accounts/types/account'
import { lineKeyApi } from '@/domains/line-keys/api/lineKeyApi'
import { deviceApi } from '../api/deviceApi'
import type { Device } from '../types/device'
import DevicesPage from './DevicesPage.vue'

vi.mock('../api/deviceApi', () => ({
  deviceApi: {
    list: vi.fn(),
    remove: vi.fn(),
    syncProvisioning: vi.fn(),
  },
}))

vi.mock('@/domains/line-keys/api/lineKeyApi', () => ({
  lineKeyApi: {
    preview: vi.fn(),
    update: vi.fn(),
  },
}))

const RowActionMenuStub = defineComponent({
  name: 'RowActionMenu',
  props: ['label', 'actions'],
  emits: ['select'],
  template: `
    <div :aria-label="label">
      <button
        v-for="action in actions"
        :key="action.id"
        :data-action="action.id"
        :disabled="action.disabled"
        @click="$emit('select', action.id)"
      >
        {{ action.label }}
      </button>
    </div>
  `,
})

const ConfirmDialogStub = defineComponent({
  name: 'ConfirmDialog',
  props: ['open', 'title'],
  emits: ['confirm', 'close'],
  template: `
    <div v-if="open" data-testid="confirm-dialog">
      <span>{{ title }}</span>
      <button data-confirm @click="$emit('confirm')">Confirm</button>
    </div>
  `,
})

const LineKeyPanelStub = defineComponent({
  name: 'LineKeyPanel',
  props: ['preview'],
  template: '<div data-testid="line-key-panel">{{ preview.device.name }}</div>',
})

const permissions: Account['permissions'] = {
  can_manage_extensions: true,
  can_manage_devices: true,
  can_manage_voicemail: true,
  can_manage_call_routing: true,
  can_manage_media: true,
  can_sync_call_detail_records: true,
  can_view_services: true,
  can_manage_account_settings: true,
  can_onboard_descendants: false,
}

const supportedDevice: Device = {
  id: 'device-supported',
  name: 'Reception phone',
  device_type: 'sip_device',
  make: 'Yealink',
  model: 'T54W',
  mac_address: '00:11:22:33:44:55',
  is_enabled: true,
  registration_status: 'registered',
  registration_checked_at: null,
  assigned_extension: null,
  sync_status: 'healthy',
  last_synced_at: null,
}

const unsupportedDevice: Device = {
  ...supportedDevice,
  id: 'device-unsupported',
  name: 'Mobile softphone',
  device_type: 'softphone',
  make: null,
  model: null,
  mac_address: null,
}

async function mountPage() {
  window.localStorage.clear()
  vi.mocked(deviceApi.list).mockResolvedValue({
    data: [supportedDevice, unsupportedDevice],
    links: { prev: null, next: null },
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: 2,
      sync: { status: 'healthy', last_successful_at: null, error_message: null },
    },
  })
  vi.mocked(deviceApi.syncProvisioning).mockResolvedValue({
    message: 'Switch accepted the device synchronization request.',
    command: 'sync',
  })
  vi.mocked(lineKeyApi.preview).mockResolvedValue({
    device: {
      id: supportedDevice.id,
      name: supportedDevice.name,
      make: supportedDevice.make,
      endpoint_family: 't5',
      model: supportedDevice.model,
      mac_address: supportedDevice.mac_address,
      line_keys: [],
    },
    capability: {
      preview_available: true,
      apply_available: true,
      reason: null,
      model: {
        catalog_available: true,
        catalog_reason: null,
        matched: true,
        max_keys: 10,
        max_expansion_modules: 0,
        keys_per_expansion_module: null,
        total_keys: 10,
        supported_key_types: ['line', 'presence'],
        value_sources: ['extensions'],
        manufacturer_provider: 'yealink-rps',
      },
    },
    value_choices: [],
    payload_preview: { provision: { combo_keys: {}, feature_keys: {} } },
  })

  const pinia = createPinia()
  setActivePinia(pinia)
  const accounts = useAccountStore()
  accounts.accounts = [
    {
      id: 'account-1',
      name: 'GridPBX',
      realm: 'gridpbx.example.test',
      timezone: 'America/New_York',
      enabled: true,
      organization: { id: 'organization-1', name: 'GridPBX' },
      organization_role: 'account_admin',
      permissions,
    },
  ]
  accounts.selectedId = 'account-1'

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/devices', name: 'devices', component: { template: '<div />' } },
      { path: '/devices/new', name: 'device-create', component: { template: '<div />' } },
      { path: '/devices/:deviceId', name: 'device-detail', component: { template: '<div />' } },
      {
        path: '/devices/:deviceId/edit',
        name: 'device-edit',
        component: { template: '<div />' },
      },
      {
        path: '/extensions/:extensionId',
        name: 'extension-detail',
        component: { template: '<div />' },
      },
    ],
  })
  await router.push('/devices')
  await router.isReady()

  const wrapper = mount(DevicesPage, {
    global: {
      plugins: [pinia, router],
      stubs: {
        ConfirmDialog: ConfirmDialogStub,
        LineKeyPanel: LineKeyPanelStub,
        RowActionMenu: RowActionMenuStub,
        RouterView: true,
      },
    },
  })
  await vi.waitFor(() => expect(wrapper.findAllComponents(RowActionMenuStub)).toHaveLength(2))

  return wrapper
}

describe('DevicesPage device actions', () => {
  it('gates provisioning actions and runs the supported device workflows', async () => {
    const wrapper = await mountPage()
    const [supportedMenu, unsupportedMenu] = wrapper.findAllComponents(RowActionMenuStub)

    expect(supportedMenu?.props('actions').map(({ id }: { id: string }) => id)).toEqual([
      'view',
      'edit',
      'line-keys',
      'sync',
      'reprovision',
      'delete',
    ])
    expect(unsupportedMenu?.props('actions').map(({ id }: { id: string }) => id)).toEqual([
      'view',
      'edit',
      'delete',
    ])

    unsupportedMenu?.vm.$emit('select', 'reprovision')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[data-testid="confirm-dialog"]').exists()).toBe(false)
    expect(deviceApi.syncProvisioning).not.toHaveBeenCalled()

    await supportedMenu?.get('[data-action="sync"]').trigger('click')
    expect(wrapper.get('[data-testid="confirm-dialog"]').text()).toContain('Send check-sync')
    await wrapper.get('[data-confirm]').trigger('click')
    await vi.waitFor(() =>
      expect(deviceApi.syncProvisioning).toHaveBeenCalledWith(
        'account-1',
        supportedDevice.id,
        'sync',
      ),
    )

    vi.mocked(deviceApi.syncProvisioning).mockClear()
    await supportedMenu?.get('[data-action="reprovision"]').trigger('click')
    expect(wrapper.get('[data-testid="confirm-dialog"]').text()).toContain('Reprovision')
    await wrapper.get('[data-confirm]').trigger('click')
    await vi.waitFor(() =>
      expect(deviceApi.syncProvisioning).toHaveBeenCalledWith(
        'account-1',
        supportedDevice.id,
        'reprovision',
      ),
    )

    await supportedMenu?.get('[data-action="line-keys"]').trigger('click')
    await vi.waitFor(() =>
      expect(lineKeyApi.preview).toHaveBeenCalledWith('account-1', supportedDevice.id),
    )
    await vi.waitFor(() =>
      expect(wrapper.find('[data-testid="line-key-panel"]').exists()).toBe(true),
    )
    expect(wrapper.get('[data-testid="line-key-panel"]').text()).toContain('Reception phone')
  })
})
