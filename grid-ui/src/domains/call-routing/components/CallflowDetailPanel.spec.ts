import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import type { Callflow } from '../types/callRouting'
import CallflowDetailPanel from './CallflowDetailPanel.vue'

const record: Callflow = {
  id: 'f8ff6ea9-c468-47ae-9b47-82947ce0782e',
  name: 'Main line',
  route_type: 'phone_number',
  numbers: ['+15551234567'],
  patterns: [],
  flags: [],
  modules: ['temporal_route', 'user'],
  root_module: 'temporal_route',
  node_count: 2,
  max_depth: 2,
  feature_code: null,
  flow: {
    module: 'temporal_route',
    target: {
      type: 'temporal_rule_set',
      id: '61f7b4bb-b001-4c8b-a2b2-5717b8cfc514',
      label: 'Business hours',
    },
    reference_status: 'resolved',
    branch: null,
    children: {
      rule_set: {
        module: 'user',
        target: {
          type: 'extension',
          id: '54d9431a-f090-413b-a17e-88e02f0c0b44',
          label: 'Reception',
        },
        reference_status: 'resolved',
        branch: { key: 'rule_set', label: 'Schedule matches', kind: 'schedule_match' },
        children: {},
      },
    },
  },
  linked_extension: null,
  phone_numbers: [
    {
      id: '4a2aedf6-41ed-46db-9496-e0468e97cc95',
      number: '+15551234567',
      state: 'in_service',
    },
  ],
  sync_status: 'healthy',
  last_synced_at: '2026-08-30T00:00:00+00:00',
}

describe('CallflowDetailPanel', () => {
  it('renders the route map as an inline main-page workspace', async () => {
    const wrapper = mount(CallflowDetailPanel, {
      props: {
        record,
        loading: false,
        error: null,
        canManage: true,
        deleting: false,
        mutationError: null,
      },
      global: {
        stubs: {
          CallflowActionPalette: { template: '<div>Action catalog</div>' },
          CallflowNodeInfoDialog: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    wrapper.get('[aria-label="Callflow workspace"]')
    wrapper.get('[aria-label="Callflow diagram"]')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('The full-width route map stays on the main page')

    await wrapper.get('[aria-label="Back to call routes"]').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('offers a keyboard-accessible typed subtree move in the node modal', async () => {
    const wrapper = mount(CallflowDetailPanel, {
      props: {
        record,
        loading: false,
        error: null,
        canManage: true,
        deleting: false,
        mutationError: null,
      },
      global: {
        stubs: {
          CallflowActionPalette: { template: '<div>Action catalog</div>' },
          CallflowNodeInfoDialog: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    await wrapper.get('[aria-label="User: Reception"]').trigger('click')
    const begin = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Move or reorder this subtree'))
    expect(begin).toBeDefined()
    await begin!.trigger('click')
    await wrapper.get('[aria-label="Time of Day: Business hours"]').trigger('click')
    const move = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Move subtree here'))
    expect(move).toBeDefined()
    expect(move!.attributes('disabled')).toBeUndefined()
    await move!.trigger('click')

    expect(wrapper.emitted('move-node')).toEqual([
      [
        {
          source_path: ['rule_set'],
          destination_parent_path: [],
          destination_branch: '_',
        },
      ],
    ])
  })

  it('offers a lossless swap for two separate guided subtrees', async () => {
    const reorderRecord: Callflow = {
      ...record,
      flow: {
        ...record.flow!,
        children: {
          ...record.flow!.children,
          timeout: {
            module: 'voicemail',
            target: {
              type: 'voicemail',
              id: '838b0f4a-6f26-4c39-ae16-f553a3a7b9de',
              label: 'After hours',
            },
            reference_status: 'resolved',
            branch: { key: 'timeout', label: 'No schedule match', kind: 'default' },
            children: {},
          },
        },
      },
    }
    const wrapper = mount(CallflowDetailPanel, {
      props: {
        record: reorderRecord,
        loading: false,
        error: null,
        canManage: true,
        deleting: false,
        mutationError: null,
      },
      global: {
        stubs: {
          CallflowActionPalette: { template: '<div>Action catalog</div>' },
          CallflowNodeInfoDialog: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    await wrapper.get('[aria-label="User: Reception"]').trigger('click')
    const begin = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Move or reorder this subtree'))
    await begin!.trigger('click')
    await wrapper.get('[aria-label="Voicemail: After hours"]').trigger('click')
    const swap = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Swap positions'))
    expect(swap?.attributes('disabled')).toBeUndefined()
    await swap!.trigger('click')

    expect(wrapper.emitted('reorder-nodes')).toEqual([
      [
        {
          mode: 'swap',
          source_path: ['rule_set'],
          target_path: ['timeout'],
        },
      ],
    ])
  })

  it('opens the shared action-target editor for a selected guided node', async () => {
    const wrapper = mount(CallflowDetailPanel, {
      props: {
        record,
        loading: false,
        error: null,
        canManage: true,
        deleting: false,
        mutationError: null,
      },
      global: {
        stubs: {
          CallflowActionPalette: { template: '<div>Action catalog</div>' },
          CallflowNodeInfoDialog: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    await wrapper.get('[aria-label="User: Reception"]').trigger('click')
    const edit = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Edit action target'))
    expect(edit).toBeDefined()
    await edit!.trigger('click')

    expect(wrapper.emitted('edit-node')?.[0]?.[0]).toMatchObject({
      operation: 'update',
      path: ['rule_set'],
      module: 'user',
    })
  })
})
