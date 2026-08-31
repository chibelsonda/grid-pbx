import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CallflowCreateWorkspace from './CallflowCreateWorkspace.vue'
import type { CallflowEditor } from '../types/callRouting'

const extensionId = '16f95ac5-243c-476a-b238-9f51108f82e1'
const phoneNumberId = '1078f5f7-a8c4-4296-abf8-610612cac312'
const deviceId = '11111111-1111-4111-8111-111111111111'

function createEditor(): CallflowEditor {
  return {
    mode: 'create',
    editable: true,
    blocked_reason: null,
    fallback: { editable: true, blocked_reason: null, target: null },
    menu_branches: {
      editable: true,
      blocked_reason: null,
      branches: [],
      legacy_hash_present: false,
      unknown_branch_keys: [],
    },
    temporal_match: {
      editable: true,
      blocked_reason: null,
      target: null,
      preserved_branch_count: 0,
    },
    direct_temporal_routes: [],
    temporal_rule_sets: {},
    temporal_rules: [],
    caller_id_lists: [],
    destination_types: [{ value: 'extension', label: 'Extension' }],
    destinations: {
      extension: [{ id: extensionId, label: 'Reception', detail: '1001' }],
      device: [],
      voicemail: [],
      callflow: [],
      media: [],
      directory: [],
      group: [],
      queue: [],
      menu: [],
      conference: [],
      fax_box: [],
      temporal_rule_set: [],
      temporal_rules: [],
    },
    phone_numbers: [
      {
        id: phoneNumberId,
        number: '+15551234567',
        state: 'in_service',
        selected: false,
        available: true,
        assigned_callflow: null,
      },
    ],
  }
}

describe('CallflowCreateWorkspace', () => {
  it('starts with the Switch-style empty callflow root instead of the guided form', () => {
    const wrapper = mount(CallflowCreateWorkspace, {
      props: {
        editor: createEditor(),
        saving: false,
        error: null,
        fieldErrors: {},
      },
    })

    expect(wrapper.get('[aria-label="New callflow canvas"]').text()).toContain(
      'Click to add number',
    )
    expect(wrapper.text()).toContain('Drag an action here or select one from the catalog')
    expect(wrapper.text()).not.toContain('Route identity')
    expect(wrapper.text()).not.toContain('Root destination')
  })

  it('keeps compact create actions inside the node canvas footer', () => {
    const wrapper = mount(CallflowCreateWorkspace, {
      props: {
        editor: createEditor(),
        saving: false,
        error: null,
        fieldErrors: {},
      },
    })

    const canvas = wrapper.get('[aria-label="New callflow canvas"]')
    const cancel = canvas.findAll('button').find((button) => button.text() === 'Cancel')
    const create = canvas.findAll('button').find((button) => button.text() === 'Create callflow')

    expect(cancel?.classes()).toContain('h-8')
    expect(create?.classes()).toContain('h-8')
  })

  it('adds and removes a compact root action before the callflow is saved', async () => {
    const wrapper = mount(CallflowCreateWorkspace, {
      props: {
        editor: createEditor(),
        saving: false,
        error: null,
        fieldErrors: {},
      },
      global: {
        stubs: {
          CallflowNodeInfoDialog: {
            props: ['open'],
            template: '<div v-if="open"><slot /></div>',
          },
        },
      },
    })

    await wrapper.get('input[type="search"]').setValue('user')
    await wrapper.get('[aria-label="Use User as root action"]').trigger('click')

    expect(wrapper.get('[aria-label="New callflow canvas"]').text()).toContain('User')
    await wrapper.get('[aria-label="Remove User"]').trigger('click')

    expect(wrapper.find('[aria-label="Remove User"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Drag an action here or select one from the catalog')
  })

  it('submits a configured Ring Group root with public UUIDs', async () => {
    const wrapper = mount(CallflowCreateWorkspace, {
      props: {
        editor: createEditor(),
        saving: false,
        error: null,
        fieldErrors: {},
      },
      global: {
        stubs: {
          CallflowNodeInfoDialog: {
            props: ['open'],
            template: '<div v-if="open"><slot /></div>',
          },
          CallflowInlineNodeEditorPanel: {
            name: 'CallflowInlineNodeEditorPanel',
            template: '<div />',
          },
        },
      },
    })

    await wrapper.get('[aria-label="Edit callflow name and numbers"]').trigger('click')
    await wrapper.get('input[required]').setValue('Support ring group')
    await wrapper.get('input[type="checkbox"]').setValue(true)
    await wrapper.get('input[type="search"]').setValue('ring group')
    await wrapper.get('[aria-label="Use Ring Group as root action"]').trigger('click')
    wrapper.findComponent({ name: 'CallflowInlineNodeEditorPanel' }).vm.$emit('save', {
      parent_path: [],
      branch: '_',
      module: 'ring_group',
      data: {
        strategy: 'simultaneous',
        endpoints: [{ device_id: deviceId, delay: 0, timeout: 20 }],
        repeats: 1,
        ignore_forward: true,
        fail_on_single_reject: false,
        ringback_media_id: null,
        ringtone_internal: null,
        ringtone_external: null,
        skip_module: false,
      },
    })
    await wrapper.vm.$nextTick()
    await wrapper.get('form').trigger('submit')

    const input = wrapper.emitted('save')?.[0]?.[0]
    expect(input).toMatchObject({
      name: 'Support ring group',
      destination_type: null,
      destination_id: null,
      phone_number_ids: [phoneNumberId],
      root_action: {
        module: 'ring_group',
        data: { endpoints: [{ device_id: deviceId }] },
      },
    })
    expect(JSON.stringify(input)).not.toContain('switch-device')
  })

  it('drags a shared palette action onto the blank callflow root', async () => {
    const setData = vi.fn()
    const dataTransfer = {
      setData,
      getData: vi.fn((type: string) =>
        type === 'application/x-gridpbx-callflow-action' ? 'user' : '',
      ),
      effectAllowed: 'none',
      dropEffect: 'none',
    }
    const wrapper = mount(CallflowCreateWorkspace, {
      props: {
        editor: createEditor(),
        saving: false,
        error: null,
        fieldErrors: {},
      },
      global: {
        stubs: {
          CallflowNodeInfoDialog: {
            props: ['open'],
            template: '<div v-if="open"><slot /></div>',
          },
        },
      },
    })

    await wrapper.get('input[type="search"]').setValue('user')
    const paletteAction = wrapper.get('[aria-label="Use User as root action"]')
    const canvas = wrapper.get('[aria-label="New callflow canvas"]')

    expect(paletteAction.attributes('draggable')).toBe('true')
    await paletteAction.trigger('dragstart', { dataTransfer })
    await canvas.trigger('dragover', { dataTransfer })

    expect(canvas.text()).toContain('Drop to use this as the root action.')
    expect(dataTransfer.dropEffect).toBe('copy')

    await canvas.trigger('drop', { dataTransfer })

    expect(setData).toHaveBeenCalledWith('application/x-gridpbx-callflow-action', 'user')
    expect(canvas.text()).toContain('User')
    expect(wrapper.find('[aria-label="Remove User"]').exists()).toBe(true)
  })
})
