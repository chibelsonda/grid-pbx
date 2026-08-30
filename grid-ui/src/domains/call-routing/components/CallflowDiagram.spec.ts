import { nextTick } from 'vue'
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CallflowDiagram from './CallflowDiagram.vue'
import type { CallflowNode } from '../types/callRouting'

function dispatchPointerEvent(
  element: Element,
  type: string,
  properties: Record<string, string | number>,
): void {
  const event = new Event(type, { bubbles: true, cancelable: true })
  Object.entries(properties).forEach(([key, value]) => {
    Object.defineProperty(event, key, { value })
  })
  element.dispatchEvent(event)
}

describe('CallflowDiagram', () => {
  it('uses SVG connectors without generic path-count or default-branch labels', () => {
    const node: CallflowNode = {
      module: 'device',
      target: { type: 'device', id: 'device-public', label: 'Reception phone' },
      reference_status: 'resolved',
      children: {
        _: {
          module: 'voicemail',
          target: { type: 'voicemail', id: 'voicemail-public', label: 'Reception mailbox' },
          reference_status: 'resolved',
          branch: { key: '_', label: 'Default branch', kind: 'default' },
          children: {},
        },
      },
    }

    const wrapper = mount(CallflowDiagram, { props: { node } })

    expect(wrapper.text()).not.toContain('1 path')
    expect(wrapper.text()).not.toContain('Default branch')
    expect(wrapper.findAll('svg.h-10.w-5')).toHaveLength(2)
  })

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
    expect(wrapper.findAll('[data-callflow-branch-bus]')).toHaveLength(2)
    expect(wrapper.find('[data-callflow-parent-stem]').exists()).toBe(true)
    expect(wrapper.findAll('svg[data-callflow-branch-bus]')).toHaveLength(2)
    expect(wrapper.findAll('[data-callflow-branch-bus] line[stroke-width="8"]')).toHaveLength(2)
  })

  it('pans the scrollable canvas from blank space without intercepting node interaction', async () => {
    const node: CallflowNode = {
      module: 'device',
      target: { type: 'device', id: 'device-public', label: 'Reception phone' },
      reference_status: 'resolved',
      children: {},
    }
    const wrapper = mount(CallflowDiagram, { props: { node } })
    const canvas = wrapper.get<HTMLElement>('[data-callflow-pan-canvas]')
    canvas.element.scrollLeft = 80
    canvas.element.scrollTop = 120

    dispatchPointerEvent(canvas.element, 'pointerdown', {
      button: 0,
      pointerId: 1,
      pointerType: 'mouse',
      clientX: 100,
      clientY: 100,
    })
    await nextTick()
    expect(canvas.classes()).toContain('cursor-grabbing')

    dispatchPointerEvent(canvas.element, 'pointermove', {
      pointerId: 1,
      pointerType: 'mouse',
      clientX: 70,
      clientY: 55,
    })
    await nextTick()
    expect(canvas.element.scrollLeft).toBe(110)
    expect(canvas.element.scrollTop).toBe(165)

    dispatchPointerEvent(canvas.element, 'pointerup', { pointerId: 1, pointerType: 'mouse' })
    await nextTick()
    expect(canvas.classes()).toContain('cursor-grab')

    dispatchPointerEvent(
      wrapper.get('[aria-label="Device: Reception phone"]').element,
      'pointerdown',
      {
        button: 0,
        pointerId: 2,
        pointerType: 'mouse',
        clientX: 50,
        clientY: 50,
      },
    )
    await nextTick()
    expect(canvas.classes()).not.toContain('cursor-grabbing')
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
    const root = wrapper.get('[aria-label="Time of Day"]')
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
      id: 'tts',
      module: 'tts',
      label: 'TTS',
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
