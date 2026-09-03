import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormInput from '@/shared/components/FormInput.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import CallflowNodeEditorPanel from './CallflowNodeEditorPanel.vue'
import type { CallflowEditor, CallflowNodeEditorContext } from '../types/callRouting'

const destinationId = '54d9431a-f090-413b-a17e-88e02f0c0b44'
const stubs = {
  CrudSlideOver: { template: '<div><slot /></div>' },
  CallflowResourceActionsDialog: {
    name: 'CallflowResourceActionsDialog',
    props: ['open', 'type', 'selectedId', 'selectedLabel'],
    template: '<div data-resource-actions-dialog />',
  },
}

function editor(type: 'extension' | 'menu'): CallflowEditor {
  return {
    destinations: {
      extension:
        type === 'extension' ? [{ id: destinationId, label: 'Alice', detail: '1001' }] : [],
      menu: type === 'menu' ? [{ id: destinationId, label: 'Main menu', detail: null }] : [],
    },
  } as CallflowEditor
}

describe('CallflowNodeEditorPanel', () => {
  it('renders and submits the Switch User timeout and can-call-self fields', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'update',
      path: ['_'],
      module: 'user',
      node: {
        module: 'user',
        target: { type: 'extension', id: destinationId, label: 'Alice' },
        reference_status: 'resolved',
        settings: { timeout: 35, can_call_self: true },
        children: {},
      },
    }
    const wrapper = mount(CallflowNodeEditorPanel, {
      props: {
        context,
        editor: editor('extension'),
        loading: false,
        saving: false,
        error: null,
        fieldErrors: {},
      },
      global: { stubs },
    })

    const timeout = wrapper
      .findAllComponents(FormInput)
      .find((input) => input.props('label') === 'Timeout')
    const canCallSelf = wrapper.findComponent(ToggleSwitch)
    expect(timeout?.props('modelValue')).toBe(35)
    expect(canCallSelf.props('modelValue')).toBe(true)

    timeout!.vm.$emit('update:modelValue', 45)
    canCallSelf.vm.$emit('update:modelValue', false)
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({
      node_path: ['_'],
      destination_type: 'extension',
      destination_id: destinationId,
      data: { timeout: 45, can_call_self: false },
    })
  })

  it('keeps ID-only resource nodes free of endpoint fields and opens resource actions', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'menu',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const wrapper = mount(CallflowNodeEditorPanel, {
      props: {
        context,
        editor: editor('menu'),
        loading: false,
        saving: false,
        error: null,
        fieldErrors: {},
      },
      global: { stubs },
    })

    expect(wrapper.findComponent(ToggleSwitch).exists()).toBe(false)
    expect(wrapper.findAllComponents(FormInput)).toHaveLength(0)
    const actionsButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Links / actions'))
    expect(actionsButton).toBeDefined()
    await actionsButton!.trigger('click')

    expect(wrapper.findComponent({ name: 'CallflowResourceActionsDialog' }).props()).toMatchObject({
      open: true,
      type: 'menu',
      selectedId: destinationId,
      selectedLabel: 'Main menu',
    })

    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[0]?.[0]).not.toHaveProperty('data')
  })
})
