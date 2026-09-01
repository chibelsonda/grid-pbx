import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormListbox from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
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

  it('submits a resource-free Hotdesk preset and explains its runtime PIN boundary', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'hotdesk',
      preset: { action: 'toggle' },
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

    expect(wrapper.text()).toContain('caller enters the Hotdesk ID at call time')
    expect(wrapper.text()).toContain('logout path do not prompt for it')
    await wrapper.get('form').trigger('submit')

    const input = wrapper.emitted('save')?.[0]?.[0] as { data: Record<string, unknown> }
    expect(input).toMatchObject({
      module: 'hotdesk',
      data: { action: 'toggle', skip_module: false },
    })
    expect(input.data).not.toHaveProperty('id')
    expect(input.data).not.toHaveProperty('interdigit_timeout')
  })

  it('submits a resource-free Do Not Disturb preset and explains its authentication boundary', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'do_not_disturb',
      preset: { action: 'toggle' },
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

    expect(wrapper.text()).toContain('authenticated caller’s owner')
    expect(wrapper.text()).toContain('does not prompt for a PIN')
    await wrapper.get('form').trigger('submit')

    const input = wrapper.emitted('save')?.[0]?.[0] as { data: Record<string, unknown> }
    expect(input).toMatchObject({
      module: 'do_not_disturb',
      data: { action: 'toggle', skip_module: false },
    })
    expect(input.data).not.toHaveProperty('id')
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

  it('requires explicit confirmation before replacing an occupied continuation with Response', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      placement: 'replace',
      path: [],
      module: 'response',
      node: {
        module: 'set_variables',
        target: null,
        reference_status: 'not_applicable',
        children: {
          _: { module: 'device', target: null, reference_status: 'resolved', children: {} },
        },
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    expect(wrapper.text()).toContain('replace that step and its complete downstream subtree')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')).toBeUndefined()
    expect(wrapper.text()).toContain('Confirm that the existing next step will be replaced.')

    await wrapper.get('input[aria-label="Replace the current next step"]').setValue(true)
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      parent_path: [],
      branch: '_',
      placement: 'replace',
      confirm_replace: true,
      module: 'response',
    })
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

  it('configures Branch BNumber hunt filters and exact captured-number child branches', async () => {
    const branchContext: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'branch_bnumber',
      node: {
        module: 'branch_variable',
        target: null,
        reference_status: 'not_applicable',
        settings: { supported_variable: true },
        children: {
          '42': {
            module: 'hangup',
            target: null,
            reference_status: 'not_applicable',
            children: {},
          },
        },
      },
    }
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context: branchContext, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    const hunt = wrapper
      .findAllComponents(ToggleSwitch)
      .find((toggle) => toggle.props('label') === 'Hunt for a matching callflow')
    expect(hunt).toBeDefined()
    expect(hunt!.props('disabled')).toBe(false)
    hunt!.vm.$emit('update:modelValue', true)
    await wrapper.vm.$nextTick()
    await wrapper.get('input[aria-label="Allowed-number pattern"]').setValue('^1\\d{3}$')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'branch_bnumber',
      data: {
        hunt: true,
        hunt_allow: '^1\\d{3}$',
        hunt_deny: null,
        skip_module: false,
      },
    })

    await wrapper.setProps({
      context: {
        operation: 'create',
        path: ['_'],
        module: 'hangup',
        node: {
          module: 'branch_bnumber',
          target: null,
          reference_status: 'not_applicable',
          settings: { hunt: false },
          children: {
            _: {
              module: 'response',
              target: null,
              reference_status: 'not_applicable',
              children: {},
            },
          },
        },
      },
    })
    const captureBranch = wrapper.get('input[aria-label="Captured number branch"]')
    await captureBranch.setValue('1000')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[1]?.[0]).toMatchObject({
      parent_path: ['_'],
      branch: '1000',
      module: 'hangup',
    })
  })

  it('edits Set CAV as validated rows and submits the schema object', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'set_variables',
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

    const addVariable = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Add variable'))
    expect(addVariable).toBeDefined()
    await addVariable!.trigger('click')
    await addVariable!.trigger('click')

    await wrapper.get('input[aria-label="Variable 1 name"]').setValue('account_code')
    await wrapper.get('input[aria-label="Variable 1 value"]').setValue('support')
    await wrapper.get('input[aria-label="Variable 2 name"]').setValue('account_code')
    await wrapper.get('input[aria-label="Variable 2 value"]').setValue('sales')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.get('input[aria-label="Variable 1 name"]').attributes('aria-invalid')).toBe(
      'true',
    )
    expect(wrapper.get('input[aria-label="Variable 2 name"]').attributes('aria-invalid')).toBe(
      'true',
    )

    await wrapper.get('input[aria-label="Variable 2 name"]').setValue('queue')
    const exportToggle = wrapper
      .findAllComponents(ToggleSwitch)
      .find((toggle) => toggle.props('label') === 'Export to future bridged legs')
    expect(exportToggle).toBeDefined()
    exportToggle!.vm.$emit('update:modelValue', true)
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'set_variables',
      data: {
        custom_application_vars: { account_code: 'support', queue: 'sales' },
        export: true,
        skip_module: false,
      },
    })
    expect(wrapper.emitted('save')?.[0]?.[0]).not.toHaveProperty(
      'data.custom_application_variables',
    )
  })

  it('validates and submits the Monster-aligned Manual Presence fields', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'manual_presence',
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
    const presenceId = wrapper.get('input[aria-label="Presence ID"]')

    expect(wrapper.text()).toContain('Busy')
    await presenceId.setValue('bad id')
    await wrapper.get('form').trigger('submit')
    expect(presenceId.attributes('aria-invalid')).toBe('true')
    expect(presenceId.classes()).toContain('!border-red-400')

    await presenceId.setValue('1001@example.com')
    const status = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Presence status')
    expect(status).toBeDefined()
    status!.vm.$emit('update:modelValue', 'ringing')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'manual_presence',
      data: {
        presence_id: '1001@example.com',
        status: 'ringing',
        skip_module: false,
      },
    })
  })

  it('uses one Monster-aligned selector for a Group Pickup target', async () => {
    const groupId = '11111111-1111-4111-8111-111111111111'
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'group_pickup',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const editor = {
      destinations: {
        group: [{ id: groupId, label: 'Support pickup', detail: '3 members' }],
        extension: [
          {
            id: '22222222-2222-4222-8222-222222222222',
            label: 'Reception',
            detail: '1001',
          },
        ],
        device: [
          {
            id: '33333333-3333-4333-8333-333333333333',
            label: 'Front desk phone',
            detail: null,
          },
        ],
      },
    } as CallflowEditor
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, editor, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    await wrapper.get('form').trigger('submit')
    const target = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Pickup target')
    expect(target).toBeDefined()
    expect(target!.props('invalid')).toBe(true)
    expect(target!.props('options')).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ value: `group:${groupId}`, label: 'Support pickup' }),
      ]),
    )

    target!.vm.$emit('update:modelValue', `group:${groupId}`)
    await wrapper.vm.$nextTick()
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'group_pickup',
      data: { target_type: 'group', target_id: groupId, skip_module: false },
    })
    expect(wrapper.text()).not.toContain('approved_group_id')
  })

  it('selects a public Receive Fax owner and supports all Kazoo T.38 modes', async () => {
    const ownerId = '22222222-2222-4222-8222-222222222222'
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'receive_fax',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const editor = {
      destinations: {
        extension: [{ id: ownerId, label: 'Fax Reception', detail: '1099' }],
      },
    } as CallflowEditor
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, editor, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    await wrapper.get('form').trigger('submit')
    const owner = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Fax owner')
    const t38 = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'T.38 negotiation')
    expect(owner).toBeDefined()
    expect(owner!.props('invalid')).toBe(true)
    expect(t38?.props('options')).toHaveLength(3)

    owner!.vm.$emit('update:modelValue', ownerId)
    t38!.vm.$emit('update:modelValue', 'auto')
    await wrapper.vm.$nextTick()
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'receive_fax',
      data: { owner_id: ownerId, fax_option: 'auto', skip_module: false },
    })
  })

  it('selects only public callflows containing Ring Groups for logout', async () => {
    const targetId = '55555555-5555-4555-8555-555555555555'
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'ring_group_toggle',
      preset: { action: 'logout' },
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const editor = {
      destinations: {
        callflow: [
          {
            id: targetId,
            label: 'Support ring group',
            detail: 'ring_group',
            supports_ring_group_toggle: true,
          },
          {
            id: '66666666-6666-4666-8666-666666666666',
            label: 'Reception route',
            detail: 'user',
            supports_ring_group_toggle: false,
          },
        ],
      },
    } as CallflowEditor
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, editor, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    await wrapper.get('form').trigger('submit')
    const target = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Ring-group callflow')
    expect(target).toBeDefined()
    expect(target!.props('invalid')).toBe(true)
    expect(target!.props('options')).toEqual([
      expect.objectContaining({ value: targetId, label: 'Support ring group' }),
    ])

    target!.vm.$emit('update:modelValue', targetId)
    await wrapper.vm.$nextTick()
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'ring_group_toggle',
      data: { action: 'logout', callflow_id: targetId, skip_module: false },
    })
    expect(JSON.stringify(wrapper.emitted('save'))).not.toContain('switch-ring-group-target')
  })

  it('maps a public Queue UUID for an ACDC Queue logout action', async () => {
    const queueId = '77777777-7777-4777-8777-777777777777'
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'acdc_queue',
      preset: { action: 'logout' },
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const editor = {
      destinations: {
        queue: [{ id: queueId, label: 'Support', detail: '4 agents' }],
      },
    } as CallflowEditor
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, editor, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    await wrapper.get('form').trigger('submit')
    const target = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Queue')
    expect(target).toBeDefined()
    expect(target!.props('invalid')).toBe(true)
    expect(target!.props('options')).toEqual([
      expect.objectContaining({ value: queueId, label: 'Support' }),
    ])
    expect(wrapper.text()).toContain('does not prompt for a PIN')
    expect(wrapper.text()).toContain('never stores an agent ID')

    target!.vm.$emit('update:modelValue', queueId)
    await wrapper.vm.$nextTick()
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'acdc_queue',
      data: { action: 'logout', queue_id: queueId, skip_module: false },
    })
    expect(JSON.stringify(wrapper.emitted('save'))).not.toContain('switch-support-queue')
  })

  it('selects bounded public Device UUIDs for a Page Group', async () => {
    const deviceId = '33333333-3333-4333-8333-333333333333'
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'page_group',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const editor = {
      destinations: {
        device: [{ id: deviceId, label: 'Warehouse speaker', detail: 'SIP paging device' }],
      },
    } as CallflowEditor
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, editor, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Select at least one device.')
    expect(wrapper.text()).toContain('1–20 synchronized devices')

    const audio = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Page audio')
    expect(audio?.props('options')).toHaveLength(2)
    audio!.vm.$emit('update:modelValue', 'two-way')
    await wrapper.get('input[aria-label="Warehouse speaker"]').setValue(true)
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'page_group',
      data: { audio: 'two-way', device_ids: [deviceId], skip_module: false },
    })
    expect(JSON.stringify(wrapper.emitted('save'))).not.toContain('switch-page-device')
  })

  it('orders bounded public endpoint UUIDs for a Ring Group', async () => {
    const deviceId = '44444444-4444-4444-8444-444444444444'
    const ringbackId = '77777777-7777-4777-8777-777777777777'
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'ring_group',
      node: {
        module: 'user',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      },
    }
    const editor = {
      destinations: {
        device: [{ id: deviceId, label: 'Reception phone', detail: 'Front desk' }],
        extension: [
          {
            id: '55555555-5555-4555-8555-555555555555',
            label: 'Reception user',
            detail: '1001',
          },
        ],
        group: [
          {
            id: '66666666-6666-4666-8666-666666666666',
            label: 'Support group',
            detail: '12 members',
          },
        ],
        media: [
          {
            id: ringbackId,
            label: 'Support ringback',
            detail: 'audio/mpeg',
            supports_ringback: true,
          },
          {
            id: '88888888-8888-4888-8888-888888888888',
            label: 'Private document',
            detail: 'application/json',
            supports_ringback: false,
          },
        ],
      },
    } as CallflowEditor
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: { context, editor, saving: false, error: null, fieldErrors: {} },
      global: { stubs },
    })

    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Select at least one endpoint.')

    const strategy = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Ring strategy')
    const addMember = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Add Ring Group member')
    const ringback = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Ringback audio')
    expect(strategy?.props('options')).toHaveLength(3)
    expect(addMember?.props('options')).toHaveLength(3)
    expect(ringback?.props('options')).toHaveLength(2)
    expect(JSON.stringify(ringback?.props('options'))).toContain('Support ringback')
    expect(JSON.stringify(ringback?.props('options'))).not.toContain('Private document')
    expect(JSON.stringify(addMember?.props('options'))).toContain('Reception user')
    expect(JSON.stringify(addMember?.props('options'))).toContain('Support group')

    strategy!.vm.$emit('update:modelValue', 'weighted_random')
    addMember!.vm.$emit('update:modelValue', `device:${deviceId}`)
    ringback!.vm.$emit('update:modelValue', ringbackId)
    await wrapper.vm.$nextTick()
    expect(wrapper.get('input[aria-label="Member 1 delay"]').attributes('disabled')).toBeDefined()
    await wrapper.get('input[aria-label="Member 1 weight"]').setValue('75')
    await wrapper.get('input[aria-label="Attempts"]').setValue('2')
    expect(
      (wrapper.get('input[aria-label="Ignore device forwarding"]').element as HTMLInputElement)
        .checked,
    ).toBe(true)
    await wrapper.get('input[aria-label="Ignore device forwarding"]').setValue(false)
    await wrapper.get('input[aria-label="Stop when one device rejects"]').setValue(true)
    await wrapper.get('input[aria-label="Internal phone alert"]').setValue('internal-ring')
    await wrapper.get('input[aria-label="External phone alert"]').setValue('external-ring')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      module: 'ring_group',
      data: {
        strategy: 'weighted_random',
        endpoints: [{ device_id: deviceId, delay: 0, timeout: 20, weight: 75 }],
        repeats: 2,
        ignore_forward: false,
        fail_on_single_reject: true,
        ringback_media_id: ringbackId,
        ringtone_internal: 'internal-ring',
        ringtone_external: 'external-ring',
        skip_module: false,
      },
    })
    expect(JSON.stringify(wrapper.emitted('save'))).not.toContain('switch-ring-group-device')
  })

  it('edits a root Ring Group through the shared form contract', async () => {
    const deviceId = '44444444-4444-4444-8444-444444444444'
    const context: CallflowNodeEditorContext = {
      operation: 'update',
      path: [],
      module: 'ring_group',
      node: {
        module: 'ring_group',
        target: null,
        reference_status: 'resolved',
        settings: {
          supported_configuration: true,
          strategy: 'simultaneous',
          endpoints: [{ device_id: deviceId, delay: 0, timeout: 20, weight: null }],
          repeats: 1,
          ignore_forward: true,
          fail_on_single_reject: false,
          ringback_media_id: null,
          ringtone_internal: null,
          ringtone_external: null,
          skip_module: false,
        },
        children: {},
      },
    }
    const editor = {
      destinations: {
        device: [{ id: deviceId, label: 'Reception phone', detail: 'Front desk' }],
        media: [],
      },
    } as unknown as CallflowEditor
    const wrapper = mount(CallflowInlineNodeEditorPanel, {
      props: {
        context,
        editor,
        saving: false,
        error: null,
        fieldErrors: {},
        rootConfiguration: true,
      },
      global: {
        stubs: {
          CrudSlideOver: {
            props: ['title'],
            template: '<div><h2>{{ title }}</h2><slot /></div>',
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Edit Ring Group')
    expect(wrapper.text()).toContain('Save action')
    expect(wrapper.text()).not.toContain('Use action')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      node_path: [],
      module: 'ring_group',
      data: {
        strategy: 'simultaneous',
        endpoints: [{ device_id: deviceId, delay: 0, timeout: 20 }],
      },
    })
  })

  it('submits Conference Service without a conference resource identifier', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'conference',
      preset: { service_mode: true },
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

    expect(wrapper.text()).toContain('Conference Service')
    expect(wrapper.text()).toContain('does not store or expose a conference resource ID')
    await wrapper.get('form').trigger('submit')

    const input = wrapper.emitted('save')?.[0]?.[0] as { data: Record<string, unknown> }
    expect(input).toMatchObject({
      module: 'conference',
      data: { service_mode: true, skip_module: false },
    })
    expect(input.data).not.toHaveProperty('id')
    expect(input.data).not.toHaveProperty('action')
  })

  it('submits Check Voicemail without a mailbox identifier or auto-login flags', async () => {
    const context: CallflowNodeEditorContext = {
      operation: 'create',
      path: [],
      module: 'voicemail',
      preset: { action: 'check' },
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

    expect(wrapper.text()).toContain('Check Voicemail')
    expect(wrapper.text()).toContain('does not store or expose a voicemail box resource ID')
    await wrapper.get('form').trigger('submit')

    const input = wrapper.emitted('save')?.[0]?.[0] as { data: Record<string, unknown> }
    expect(input).toMatchObject({
      module: 'voicemail',
      data: { action: 'check', skip_module: false },
    })
    expect(input.data).not.toHaveProperty('id')
    expect(input.data).not.toHaveProperty('single_mailbox_login')
    expect(input.data).not.toHaveProperty('callerid_match_login')
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
