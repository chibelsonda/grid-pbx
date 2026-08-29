import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import type { DeviceProvisioningEnrollment } from '../types/device'
import DeviceProvisioningEnrollmentPanel from './DeviceProvisioningEnrollmentPanel.vue'

const unavailableEnrollment: DeviceProvisioningEnrollment = {
  status: 'not_enrolled',
  provider: 'yealink-rps',
  eligible: true,
  adapter_available: false,
  can_enroll: false,
  can_detach: false,
  reason:
    'Manufacturer provisioning enrollment is disabled until the client provider contract and access configuration are available.',
  enrolled_at: null,
  detached_at: null,
}

describe('DeviceProvisioningEnrollmentPanel', () => {
  it('shows safe local state and disables enrollment while the provider adapter is unavailable', () => {
    const wrapper = mount(DeviceProvisioningEnrollmentPanel, {
      props: {
        enrollment: unavailableEnrollment,
        loading: false,
        busy: false,
        canManage: true,
      },
    })

    expect(wrapper.text()).toContain('Not enrolled')
    expect(wrapper.text()).toContain('yealink-rps')
    expect(wrapper.text()).toContain('provider credentials and access tokens are never stored here')
    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
  })

  it('emits enrollment and detach intents only when their controls are available', async () => {
    const wrapper = mount(DeviceProvisioningEnrollmentPanel, {
      props: {
        enrollment: {
          ...unavailableEnrollment,
          adapter_available: true,
          can_enroll: true,
          reason: null,
        },
        loading: false,
        busy: false,
        canManage: true,
      },
    })

    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('enroll')).toHaveLength(1)

    await wrapper.setProps({
      enrollment: {
        ...unavailableEnrollment,
        status: 'enrolled',
        adapter_available: true,
        can_detach: true,
        reason: null,
        enrolled_at: '2026-08-29T05:00:00Z',
      },
    })
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('detach')).toHaveLength(1)
  })
})
