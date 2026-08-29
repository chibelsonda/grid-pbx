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
          ConfirmDialog: true,
        },
      },
    })

    wrapper.get('[aria-label="Callflow workspace"]')
    wrapper.get('[aria-label="Callflow diagram"]')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('The route map and node inspector stay on the main page')

    await wrapper.get('[aria-label="Back to call routes"]').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
