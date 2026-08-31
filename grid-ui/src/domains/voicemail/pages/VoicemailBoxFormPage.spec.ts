import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
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

function mountPage() {
  return mount(VoicemailBoxFormPage, {
    global: {
      components: { ToggleSwitch },
      stubs: {
        CrudSlideOver: { template: '<div><slot /></div>' },
        DisclosureCard: { template: '<section><slot /></section>' },
      },
    },
  })
}

describe('VoicemailBoxFormPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    push.mockReset()
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
  })

  it('shows inline invalid controls without promoting Zod errors to a mutation alert', async () => {
    const voicemail = useVoicemailStore()
    vi.spyOn(voicemail, 'loadFormOptions').mockResolvedValue()

    const wrapper = mountPage()

    const viewTabs = wrapper.find('[aria-label="Form sections"]').findAll('[role="tab"]')
    const advancedTabs = wrapper
      .find('[aria-label="Voicemail advanced sections"]')
      .findAll('[role="tab"]')

    expect(viewTabs.map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(advancedTabs.map((tab) => tab.text())).toEqual(['Basic', 'Options'])
    expect(wrapper.find('[aria-label="Timezone"]').isVisible()).toBe(false)

    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[placeholder="Reception voicemail"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a mailbox name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
    expect(viewTabs[0]!.attributes('aria-selected')).toBe('true')
    expect(voicemail.mutationError).toBeNull()
  })

  it('disables unavailable voicemail transcription before a mutation is attempted', async () => {
    const voicemail = useVoicemailStore()
    voicemail.formOptions.capabilities.voicemail_transcription = {
      schema_supported: true,
      runtime_available: false,
      default_enabled: false,
    }
    vi.spyOn(voicemail, 'loadFormOptions').mockResolvedValue()
    const create = vi.spyOn(voicemail, 'create')

    const wrapper = mountPage()
    await flushPromises()
    await wrapper.find('[aria-label="Form sections"]').findAll('[role="tab"]')[1]!.trigger('click')
    await wrapper
      .find('[aria-label="Voicemail advanced sections"]')
      .findAll('[role="tab"]')[1]!
      .trigger('click')
    const transcription = wrapper
      .findAllComponents(ToggleSwitch)
      .find((toggle) => toggle.props('label') === 'Transcribe messages')

    expect(transcription?.props('disabled')).toBe(true)
    expect(wrapper.text()).toContain(
      'Voicemail transcription is unavailable on this Switch cluster.',
    )
    expect(create).not.toHaveBeenCalled()
  })

  it('routes an Options API error to the matching outer and inner tabs', async () => {
    const voicemail = useVoicemailStore()
    vi.spyOn(voicemail, 'loadFormOptions').mockResolvedValue()
    const wrapper = mountPage()
    await flushPromises()

    voicemail.fieldErrors = { timezone: ['Select a supported timezone.'] }
    await nextTick()

    const viewTabs = wrapper.find('[aria-label="Form sections"]').findAll('[role="tab"]')
    const advancedTabs = wrapper
      .find('[aria-label="Voicemail advanced sections"]')
      .findAll('[role="tab"]')
    expect(viewTabs[1]!.attributes('aria-selected')).toBe('true')
    expect(advancedTabs[1]!.attributes('aria-selected')).toBe('true')
    expect(wrapper.get('[aria-label="Timezone"]').attributes('aria-invalid')).toBe('true')
  })
})
