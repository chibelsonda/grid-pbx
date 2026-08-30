import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useVoicemailStore } from '../stores/voicemailStore'
import VoicemailBoxFormPage from './VoicemailBoxFormPage.vue'

const push = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => ({ name: 'voicemail-create', params: {} }),
  useRouter: () => ({ push }),
}))

describe('VoicemailBoxFormPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    push.mockReset()
  })

  it('shows inline invalid controls without promoting Zod errors to a mutation alert', async () => {
    const accounts = useAccountStore()
    accounts.accounts = [
      {
        id: 'account-1',
        name: 'GridPBX',
        realm: 'gridpbx.test',
        timezone: 'Asia/Manila',
        enabled: true,
        organization: { id: 'organization-1', name: 'GridPBX' },
        organization_role: 'admin',
        permissions: {
          can_manage_extensions: true,
          can_manage_devices: true,
          can_manage_voicemail: true,
          can_manage_call_routing: true,
          can_manage_media: true,
          can_sync_call_detail_records: true,
          can_view_services: true,
          can_manage_account_settings: true,
          can_onboard_descendants: true,
        },
      },
    ]
    accounts.selectedId = 'account-1'
    const voicemail = useVoicemailStore()
    vi.spyOn(voicemail, 'loadFormOptions').mockResolvedValue()

    const wrapper = mount(VoicemailBoxFormPage, {
      global: {
        components: { ToggleSwitch },
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          DisclosureCard: { template: '<section><slot /></section>' },
        },
      },
    })

    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[placeholder="Reception voicemail"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a mailbox name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
    expect(voicemail.mutationError).toBeNull()
  })
})
