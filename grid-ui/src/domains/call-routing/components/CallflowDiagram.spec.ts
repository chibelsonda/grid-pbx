import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CallflowDiagram from './CallflowDiagram.vue'
import type { CallflowNode } from '../types/callRouting'

describe('CallflowDiagram', () => {
  it('renders recursive branch semantics without displaying internal map keys', () => {
    const node: CallflowNode = {
      module: 'temporal_route',
      target: {
        type: 'temporal_rule_set',
        id: 'd5149b3a-a4f9-4b68-b970-d1657886e92e',
        label: 'Office hours',
      },
      reference_status: 'resolved',
      branch: null,
      children: {
        rule_set: {
          module: 'user',
          target: {
            type: 'extension',
            id: '16f95ac5-243c-476a-b238-9f51108f82e1',
            label: 'Reception',
          },
          reference_status: 'resolved',
          branch: { key: 'rule_set', label: 'Schedule matches', kind: 'schedule_match' },
          children: {},
        },
        preserved_1: {
          module: 'custom_vendor',
          target: null,
          reference_status: 'not_applicable',
          branch: { key: 'preserved_1', label: 'Preserved branch 1', kind: 'preserved' },
          children: {},
        },
      },
    }

    const wrapper = mount(CallflowDiagram, {
      props: { node, entryName: 'Main line', numbers: ['+15551234567'] },
    })

    expect(wrapper.get('[role="tree"]').attributes('aria-label')).toBe('Callflow diagram')
    expect(wrapper.findAll('[role="treeitem"]')).toHaveLength(4)
    expect(wrapper.get('[aria-label="Callflow entry: +15551234567"]').text()).toContain(
      '+15551234567',
    )
    expect(wrapper.text()).toContain('Schedule matches')
    expect(wrapper.text()).toContain('Preserved branch 1')
    expect(wrapper.text()).toContain('Reception')
    expect(wrapper.text()).not.toContain('switch-rule-secret')
  })

  it('selects nested nodes using only the sanitized public branch path', async () => {
    const node: CallflowNode = {
      module: 'temporal_route',
      target: null,
      reference_status: 'not_applicable',
      children: {
        rule_set: {
          module: 'user',
          target: {
            type: 'extension',
            id: '16f95ac5-243c-476a-b238-9f51108f82e1',
            label: 'Reception',
          },
          reference_status: 'resolved',
          branch: { key: 'rule_set', label: 'Schedule matches', kind: 'schedule_match' },
          children: {},
        },
      },
    }
    const wrapper = mount(CallflowDiagram, {
      props: { node, selectedPath: ['rule_set'] },
    })
    const root = wrapper.get('[aria-label="Temporal Route"]')
    const reception = wrapper.get('[aria-label="User: Reception"]')

    expect(root.attributes('aria-selected')).toBe('false')
    expect(reception.attributes('aria-selected')).toBe('true')
    await reception.trigger('click')

    expect(wrapper.emitted('select')).toEqual([
      [{ node: node.children.rule_set, path: ['rule_set'] }],
    ])
  })

  it('emits a typed subtree move when a guided node is dropped on an empty next branch', async () => {
    const node: CallflowNode = {
      module: 'menu',
      target: null,
      reference_status: 'not_applicable',
      children: {
        '1': {
          module: 'user',
          target: { type: 'extension', id: 'user-public', label: 'Reception' },
          reference_status: 'resolved',
          branch: { key: '1', label: 'Key 1', kind: 'key' },
          children: {},
        },
        '2': {
          module: 'group',
          target: { type: 'group', id: 'group-public', label: 'Support' },
          reference_status: 'resolved',
          branch: { key: '2', label: 'Key 2', kind: 'key' },
          children: {},
        },
      },
    }
    const wrapper = mount(CallflowDiagram, {
      props: { node, editable: true, dragSourcePath: ['1'] },
    })
    const destination = wrapper.get('[aria-label="Group: Support"]')

    await destination.trigger('dragover', { dataTransfer: { dropEffect: 'none' } })
    await destination.trigger('drop', { dataTransfer: { dropEffect: 'move' } })

    expect(wrapper.emitted('move')).toEqual([
      [
        {
          source_path: ['1'],
          destination_parent_path: ['2'],
          destination_branch: '_',
        },
      ],
    ])
  })

  it('opens configuration when a guided palette action is dropped on an eligible node', async () => {
    const node: CallflowNode = {
      module: 'user',
      target: { type: 'extension', id: 'user-public', label: 'Reception' },
      reference_status: 'resolved',
      children: {},
    }
    const action = {
      module: 'tts',
      label: 'Text to speech',
      description: 'Generate speech from configured text.',
      status: 'guided' as const,
    }
    const wrapper = mount(CallflowDiagram, {
      props: { node, editable: true, paletteAction: action },
    })
    const target = wrapper.get('[aria-label="User: Reception"]')

    await target.trigger('dragover', { dataTransfer: { dropEffect: 'none' } })
    await target.trigger('drop', { dataTransfer: { dropEffect: 'copy' } })

    expect(wrapper.emitted('add-action')).toEqual([[{ node, path: [] }, action]])
  })
})
