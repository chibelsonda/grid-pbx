import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CallflowInlineNodeEditorPanel from './CallflowInlineNodeEditorPanel.vue'
import type { CallflowNodeEditorContext } from '../types/callRouting'

const stubs = {
  CrudSlideOver: { template: '<div><slot /></div>' },
}

describe('CallflowInlineNodeEditorPanel', () => {
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
})
