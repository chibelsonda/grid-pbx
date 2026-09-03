import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useDeviceStore } from '../stores/deviceStore'
import type { Device } from '../types/device'
import DeviceDetailPage from './DeviceDetailPage.vue'

const device: Device = {
  id: 'device-1',
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

describe('DeviceDetailPage account scope', () => {
  it('returns to the device list instead of loading the old device after an account change', async () => {
    window.localStorage.clear()
    const pinia = createPinia()
    setActivePinia(pinia)
    const accounts = useAccountStore()
    const devices = useDeviceStore()
    accounts.selectedId = 'account-1'

    const loadDetail = vi.spyOn(devices, 'loadDetail').mockImplementation(async () => {
      devices.detail = device
    })
    const loadOptions = vi.spyOn(devices, 'loadOptions').mockResolvedValue()
    const loadHotdeskUsers = vi.spyOn(devices, 'loadHotdeskUsers').mockResolvedValue()
    const loadProvisioningEnrollment = vi
      .spyOn(devices, 'loadProvisioningEnrollment')
      .mockResolvedValue()

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/devices', name: 'devices', component: { template: '<div />' } },
        {
          path: '/devices/:deviceId',
          name: 'device-detail',
          component: { template: '<div />' },
        },
      ],
    })
    await router.push('/devices/device-1')
    await router.isReady()

    const wrapper = mount(DeviceDetailPage, {
      global: {
        plugins: [pinia, router],
        stubs: {
          ConfirmDialog: true,
          DeviceHotdeskPanel: true,
          DeviceProvisioningEnrollmentPanel: true,
          LineKeyPanel: true,
        },
      },
    })
    await flushPromises()

    expect(loadDetail).toHaveBeenCalledWith('account-1', 'device-1')
    expect(loadOptions).toHaveBeenCalledWith('account-1')
    expect(loadHotdeskUsers).toHaveBeenCalledWith('account-1', 'device-1')
    expect(loadProvisioningEnrollment).toHaveBeenCalledWith('account-1', 'device-1')

    accounts.select('account-2')
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('devices'))

    expect(loadDetail).not.toHaveBeenCalledWith('account-2', 'device-1')
    expect(loadOptions).not.toHaveBeenCalledWith('account-2')
    expect(loadHotdeskUsers).not.toHaveBeenCalledWith('account-2', 'device-1')
    expect(loadProvisioningEnrollment).not.toHaveBeenCalledWith('account-2', 'device-1')

    wrapper.unmount()
  })
})
