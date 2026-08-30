import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormListbox from '@/shared/components/FormListbox.vue'
import CallflowInlineNodeEditorPanel from './CallflowInlineNodeEditorPanel.vue'
import type { CallflowEditor, CallflowNodeEditorContext } from '../types/callRouting'

const stubs = {
  CrudSlideOver: { template: '<div><slot /></div>' },
}

describe('CallflowInlineNodeEditorPanel', () => {
  it('keeps a shared-module palette preset in the submitted Switch action', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'record_call',
      preset: { action: 'stop' },
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    expect(wrapper.text()).toContain('Stop Call Recording')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'record_call',
      data: { action: 'stop' },
    })
  })

  it('shows Zod validation beside the invalid TTS control', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'tts',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    await wrapper.get('form').trigger('submit')

    const text = wrapper.get('textarea[aria-label="Text to speak"]')
    expect(text.attributes('aria-invalid')).toBe('true')
    expect(text.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter the text that Switch should speak.')
  })

  it('never displays or submits server-owned recording storage settings', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'update',
      path: ['_'],
      module: 'record_call',
      node: {
        module: 'record_call',
        target: null,
        reference_status: 'not_applicable',
        settings: {
          action: 'start',
          format: 'mp3',
          label: 'Support',
          record_min_sec: 2,
          record_on_answer: true,
          record_on_bridge: false,
          record_sample_rate: 16000,
          should_follow_transfer: true,
          time_limit: 120,
          skip_module: false,
          url: 'https://storage.internal.invalid/recordings',
          method: 'put',
        },
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    expect(wrapper.text()).not.toContain('storage.internal.invalid')
    await wrapper.get('form').trigger('submit')

    const input = wrapper.emitted('save')?.[0]?.[0] as { data: Record<string, unknown> }
    expect(input.data).toMatchObject({ label: 'Support', time_limit: 120 })
    expect(input.data).not.toHaveProperty('url')
    expect(input.data).not.toHaveProperty('method')
  })

  it('marks invalid Send DTMF digits and submits the current Kazoo schema fields', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'send_dtmf',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('input[aria-label="DTMF digits"]').attributes('aria-invalid')).toBe('true')

    await wrapper.get('input[aria-label="DTMF digits"]').setValue('1234#')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'send_dtmf',
      data: { digits: '1234#', duration_ms: 2000, skip_module: false },
    })
  })

  it('validates direct email recipients for Missed Call Alert', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'missed_call_alert',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    const addEmail = wrapper.findAll('button').find((button) => button.text().trim() === 'Email')
    expect(addEmail).toBeDefined()
    await addEmail!.trigger('click')
    await wrapper.get('input[type="email"]').setValue('not-an-email')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.get('input[type="email"]').attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('Enter a valid email address.')
  })

  it('shows field-level Alert-Info validation and submits the safe header value', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'set_alert_info',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    await wrapper.get('form').trigger('submit')
    const input = wrapper.get('input[aria-label="Alert-Info"]')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.classes()).toContain('!border-red-400')

    await input.setValue('Bellcore-dr2')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'set_alert_info',
      data: { alert_info: 'Bellcore-dr2', skip_module: false },
    })
  })

  it('validates Response codes without exposing Switch-managed media', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'update',
      path: ['_'],
      module: 'response',
      node: {
        module: 'response',
        target: null,
        reference_status: 'not_applicable',
        settings: {
          code: 486,
          message: 'Busy here',
          media: 'private-media-id',
          skip_module: false,
        },
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })
    const code = wrapper.get('input[aria-label="SIP response code"]')

    expect(wrapper.text()).not.toContain('private-media-id')
    await code.setValue('399')
    await wrapper.get('form').trigger('submit')
    expect(code.attributes('aria-invalid')).toBe('true')
    expect(code.classes()).toContain('!border-red-400')

    await code.setValue('603')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'response',
      data: { code: 603, message: 'Busy here', skip_module: false },
    })
    expect(wrapper.emitted('save')?.[0]?.[0]).not.toHaveProperty('data.media')
  })

  it('renders and submits only the schema-backed Hangup behavior', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'hangup',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    expect(wrapper.text()).toContain('disconnects the call')
    expect(wrapper.text()).not.toContain('Cause code')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'hangup',
      data: { skip_module: false },
    })
  })

  it('bounds Set variable to Kazoo call priority and locks unsupported existing names', async () => {
    const createContext: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'set_variable',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context: createContext, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })
    const priority = wrapper.get('input[aria-label="Call priority"]')

    expect(wrapper.get('input[aria-label="Variable"]').attributes('disabled')).toBeDefined()
    await priority.setValue('256')
    await wrapper.get('form').trigger('submit')
    expect(priority.attributes('aria-invalid')).toBe('true')
    await priority.setValue('6')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'set_variable',
      data: { variable: 'call_priority', value: '6', channel: 'a', skip_module: false },
    })

    await wrapper.setProps({
      context: {
        operation: 'update',
        path: ['_'],
        module: 'set_variable',
        node: {
          module: 'set_variable',
          target: null,
          reference_status: 'not_applicable',
          settings: { supported_variable: false, skip_module: false },
          children: {},
        },
      },
    })
    expect(wrapper.text()).toContain('preserves it without exposing its name or value')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('configures only Call Priority branches and locks unsupported existing scopes', async () => {
    const createContext: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'branch_variable',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context: createContext, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    expect(wrapper.get('input[aria-label="Variable"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('input[aria-label="Scope"]').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Priority 0–255')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'branch_variable',
      data: {
        variable: 'call_priority',
        scope: 'custom_channel_vars',
        skip_module: false,
      },
    })

    await wrapper.setProps({
      context: {
        operation: 'update',
        path: ['_'],
        module: 'branch_variable',
        node: {
          module: 'branch_variable',
          target: null,
          reference_status: 'not_applicable',
          settings: { supported_variable: false, skip_module: false },
          children: {},
        },
      },
    })
    expect(wrapper.text()).toContain('preserves its settings and dynamic branches')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('validates and submits regex-mode Check CID without exposing absolute mode', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'check_cid',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })
    const pattern = wrapper.get('input[aria-label="Caller ID pattern"]')

    await pattern.setValue('(?R)')
    await wrapper.get('form').trigger('submit')
    expect(pattern.attributes('aria-invalid')).toBe('true')
    expect(pattern.classes()).toContain('!border-red-400')

    await pattern.setValue('^\\+1555')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'check_cid',
      data: {
        regex: '^\\+1555',
        use_absolute_mode: false,
        user_id: null,
        skip_module: false,
      },
    })
  })

  it('keeps existing absolute-mode Check CID nodes read-only', () => {
    const context: CallflowNodeEditorContext = {
      operation: 'update',
      path: ['_'],
      module: 'check_cid',
      node: {
        module: 'check_cid',
        target: null,
        reference_status: 'not_applicable',
        settings: { regex: '.*', use_absolute_mode: true },
        children: {},
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    expect(wrapper.text()).toContain('absolute caller-number branches')
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(false)
  })

  it('uses projected public Caller-ID Lists for list matching', async () => {
    const listId = 'dded4533-55cb-4b40-acb6-b02248532c09'
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'cidlistmatch',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const editor = {
      caller_id_lists: [{ id: listId, label: 'VIP callers', detail: '2 entries' }],
    } as CallflowEditor
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, editor, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Select a synchronized Caller-ID List.')

    const listbox = wrapper.findAllComponents(FormListbox).at(-1)
    expect(listbox).toBeDefined()
    listbox!.vm.$emit('update:modelValue', listId)
    await wrapper.vm.$nextTick()
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'cidlistmatch',
      data: { caller_id_list_id: listId, skip_module: false },
    })
  })
})
