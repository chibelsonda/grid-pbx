import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import DirectoryFormPanel from './DirectoryFormPanel.vue'

describe('DirectoryFormPanel', () => {
  it('keeps client validation inline and marks the owning controls invalid', async () => {
    const wrapper = mount(DirectoryFormPanel, {
      props: {
        record: null,
        options: { extensions: [] },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        components: { ToggleSwitch },
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(wrapper.find('input[aria-label="Minimum digits"]').isVisible()).toBe(false)
    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')
    expect(wrapper.findAll('[role="tab"]')[1]!.attributes('aria-selected')).toBe('true')
    await wrapper.findAll('[role="tab"]')[0]!.trigger('click')

    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[required]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a directory name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })

  it('keeps the slide-over mounted while delete confirmation is open', async () => {
    const wrapper = mount(DirectoryFormPanel, {
      props: {
        record: {
          id: 'public-directory',
          name: 'People',
          confirm_match: true,
          min_dtmf: 3,
          max_dtmf: 0,
          sort_by: 'last_name',
          flags: [],
          member_count: 0,
          members: [],
          sync_status: 'healthy',
          last_synced_at: null,
        },
        options: { extensions: [] },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        components: { ToggleSwitch },
        stubs: {
          CrudSlideOver: {
            template:
              '<div><button data-test="close-panel" @click="$emit(\'close\')">Close panel</button><slot /></div>',
          },
          ConfirmDialog: {
            props: ['open'],
            emits: ['confirm'],
            template:
              '<button v-if="open" data-test="confirm-delete" @click="$emit(\'confirm\')">Confirm delete</button>',
          },
        },
      },
    })

    await wrapper.get('button.text-danger').trigger('click')
    await wrapper.get('[data-test="close-panel"]').trigger('click')
    expect(wrapper.emitted('close')).toBeUndefined()

    await wrapper.get('[data-test="confirm-delete"]').trigger('click')
    expect(wrapper.emitted('remove')).toHaveLength(1)
  })
})
