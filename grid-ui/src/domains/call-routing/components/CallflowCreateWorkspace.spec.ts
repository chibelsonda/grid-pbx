import { nextTick } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FormListbox from '@/shared/components/FormListbox.vue'
import CallflowCreateWorkspace from './CallflowCreateWorkspace.vue'
import type { CallflowEditor } from '../types/callRouting'

const extensionId = '16f95ac5-243c-476a-b238-9f51108f82e1'
const phoneNumberId = '1078f5f7-a8c4-4296-abf8-610612cac312'
const deviceId = '11111111-1111-4111-8111-111111111111'
const menuId = '22222222-2222-4222-8222-222222222222'
const voicemailId = '33333333-3333-4333-8333-333333333333'
const temporalRuleSetId = '44444444-4444-4444-8444-444444444444'
const temporalRuleId = '55555555-5555-4555-8555-555555555555'

function createEditor(): CallflowEditor {
  return {
    mode: 'create',
    editable: true,
    blocked_reason: null,
    fallback: { editable: true, blocked_reason: null, target: null },
    menu_branches: {
      editable: true,
      blocked_reason: null,
      branches: [
        {
          key: 'timeout',
          label: 'Timeout',
          editable: true,
          blocked_reason: null,
          target: null,
        },
        { key: '0', label: '0', editable: true, blocked_reason: null, target: null },
      ],
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
    temporal_rule_sets: {
      [temporalRuleSetId]: [{ id: temporalRuleId, label: 'Weekdays', position: 0, resolved: true }],
    },
    temporal_rules: [],
    caller_id_lists: [],
    destination_types: [
      { value: 'extension', label: 'Extension' },
      { value: 'menu', label: 'Menu / IVR' },
      { value: 'voicemail', label: 'Voicemail' },
      { value: 'temporal_rule_set', label: 'Business Hours / Schedule' },
    ],
    destinations: {
      extension: [{ id: extensionId, label: 'Reception', detail: '1001' }],
      device: [],
      voicemail: [{ id: voicemailId, label: 'Reception mailbox', detail: '1001' }],
      callflow: [],
      media: [],
      directory: [],
      group: [],
      queue: [],
      menu: [{ id: menuId, label: 'Main IVR', detail: 'Interactive voice menu' }],
      conference: [],
      fax_box: [],
      temporal_rule_set: [
        { id: temporalRuleSetId, label: 'Business hours', detail: '1 projected rule' },
      ],
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

  it('adds an internal extension from the callflow parent number cell', async () => {
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

    await wrapper.get('[aria-label="Add callflow entry number"]').trigger('click')
    await nextTick()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Extension')!
      .trigger('click')
    await wrapper.get('input[placeholder="e.g. 2999"]').setValue('2999')
    await wrapper.findAll('form')[1]!.trigger('submit')

    const entry = wrapper.get('[aria-label="Callflow entry: 2999"]')
    expect(entry.text()).toContain('2999')
    expect(entry.text()).toContain('Extension')
  })

  it('reopens the parent-number editor when the API rejects an extension number', async () => {
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

    await wrapper.setProps({
      fieldErrors: {
        extension_numbers: ['Extension 2999 already enters another callflow.'],
      },
    })

    expect(wrapper.get('input[placeholder="e.g. 2999"]').attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('Extension 2999 already enters another callflow.')
  })

  it('keeps compact create actions directly above the callflow parent node', () => {
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

    expect(
      canvas.element.querySelector(
        '[data-callflow-create-actions] + article[aria-label="Callflow entry"]',
      ),
    ).not.toBeNull()
    expect(cancel?.classes()).toContain('h-8')
    expect(create?.classes()).toContain('h-8')
  })

  it('lets the action palette float and return to its dock without affecting action dragging', async () => {
    const wrapper = mount(CallflowCreateWorkspace, {
      props: {
        editor: createEditor(),
        saving: false,
        error: null,
        fieldErrors: {},
      },
    })

    const paletteShell = wrapper.get('[aria-label="Callflow action catalog"]').element.parentElement
    const createWorkspace = wrapper.get('[data-callflow-create-workspace]')
    const canvasShell = wrapper.get('[data-callflow-canvas-shell]')
    const dockedRail = wrapper.get('[data-callflow-docked-rail]')
    const dockedRailContent = wrapper.get('[data-callflow-docked-rail-content]')
    const supportingCards = wrapper.get('[data-callflow-supporting-cards]')
    const routeStructureHeader = wrapper.get('[data-callflow-canvas-shell] > header')
    const canvas = wrapper.get('[aria-label="New callflow canvas"]')
    const texturedCanvas = wrapper.get('.callflow-create-canvas')
    const canvasOverlay = wrapper.get('[data-callflow-canvas-overlay]')
    expect(createWorkspace.classes()).toContain('grid')
    expect(canvas.classes()).toContain('w-full')
    expect(canvas.classes()).not.toContain('rounded-lg')
    expect(canvas.classes()).not.toContain('border')
    expect(texturedCanvas.classes()).toContain('pt-20')
    expect(texturedCanvas.element.contains(canvasOverlay.element)).toBe(true)
    expect(canvasOverlay.classes()).not.toContain('bg-white')
    expect(canvasOverlay.classes()).not.toContain('border-b')
    expect(routeStructureHeader.classes()).toContain('lg:px-8')
    expect(canvasOverlay.classes()).toContain('lg:px-8')
    expect(dockedRail.classes()).toContain('top-32')
    expect(dockedRail.classes()).toContain('overflow-x-hidden')
    expect(dockedRail.classes()).toContain('overflow-y-auto')
    expect(canvasShell.element.contains(paletteShell)).toBe(true)
    expect(dockedRailContent.element.contains(paletteShell)).toBe(true)
    expect(dockedRailContent.element.lastElementChild).toBe(supportingCards.element)
    expect(supportingCards.classes()).toContain('grid-cols-1')
    expect(supportingCards.element.children).toHaveLength(3)

    await wrapper.get('[aria-label="Collapse action catalog and route details"]').trigger('click')
    expect(wrapper.get('[data-callflow-docked-rail-content]').attributes('style')).toContain(
      'display: none',
    )
    expect(wrapper.find('[aria-label="Expand action catalog and route details"]').exists()).toBe(
      true,
    )

    await wrapper.get('[aria-label="Expand action catalog and route details"]').trigger('click')
    expect(wrapper.get('[data-callflow-docked-rail-content]').attributes('style')).not.toContain(
      'display: none',
    )

    await wrapper.get('[aria-label="Move action palette"]').trigger('pointerdown')

    expect(paletteShell?.classList.contains('fixed')).toBe(true)
    expect(wrapper.find('[aria-label="Dock action palette"]').exists()).toBe(true)
    expect(
      paletteShell?.contains(
        wrapper.get('[aria-label="Collapse action catalog and route details"]').element,
      ),
    ).toBe(true)
    expect(wrapper.get('[aria-label="Use User as root action"]').attributes('draggable')).toBe(
      'true',
    )

    await wrapper.get('[aria-label="Dock action palette"]').trigger('click')
    expect(paletteShell?.classList.contains('fixed')).toBe(false)
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

  it('submits a configured Ring Group root and canvas fallback with public UUIDs', async () => {
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
    await wrapper.get('[aria-label="Add fallback branch"]').trigger('click')
    wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Fallback type')!
      .vm.$emit('update:modelValue', 'voicemail')
    await wrapper.vm.$nextTick()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Use fallback')!
      .trigger('click')
    await wrapper.get('form').trigger('submit')

    const input = wrapper.emitted('save')?.[0]?.[0]
    expect(input).toMatchObject({
      name: 'Support ring group',
      destination_type: null,
      destination_id: null,
      phone_number_ids: [phoneNumberId],
      manage_fallback: true,
      fallback_destination_type: 'voicemail',
      fallback_destination_id: voicemailId,
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

  it('drops a guided palette action onto the empty wildcard branch and opens its public selector', async () => {
    const dataTransfer = {
      setData: vi.fn(),
      getData: vi.fn(() => 'voicemail'),
      types: ['application/x-gridpbx-callflow-action', 'text/plain'],
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

    await wrapper.get('[aria-label="Edit callflow name and numbers"]').trigger('click')
    await wrapper.get('input[required]').setValue('Voicemail fallback route')
    await wrapper.get('input[type="checkbox"]').setValue(true)
    const search = wrapper.get('input[type="search"]')
    await search.setValue('user')
    await wrapper.get('[aria-label="Use User as root action"]').trigger('click')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Use action')!
      .trigger('click')
    await search.setValue('voicemail')
    await wrapper.get('[aria-label="Use Voicemail as root action"]').trigger('dragstart', {
      dataTransfer,
    })

    const fallback = wrapper.get('[aria-label="Add fallback branch"]')
    await fallback.trigger('dragover', { dataTransfer })
    expect(fallback.text()).toBe('Drop Voicemail as fallback')
    expect(dataTransfer.dropEffect).toBe('copy')
    await fallback.trigger('drop', { dataTransfer })

    const fallbackType = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Fallback type')
    expect(fallbackType?.props('modelValue')).toBe('voicemail')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Use fallback')!
      .trigger('click')
    expect(wrapper.get('[aria-label="New callflow canvas"]').text()).toContain('Reception mailbox')

    await wrapper.get('form').trigger('submit')
    const input = wrapper.emitted('save')?.[0]?.[0]
    expect(input).toMatchObject({
      name: 'Voicemail fallback route',
      destination_type: 'extension',
      destination_id: extensionId,
      manage_fallback: true,
      fallback_destination_type: 'voicemail',
      fallback_destination_id: voicemailId,
      phone_number_ids: [phoneNumberId],
    })
    expect(JSON.stringify(input)).not.toContain('switch-')
  })

  it('rejects an inline palette action on the resource-backed wildcard draft', async () => {
    const dataTransfer = {
      setData: vi.fn(),
      getData: vi.fn(() => 'ring_group'),
      types: ['application/x-gridpbx-callflow-action', 'text/plain'],
      effectAllowed: 'none',
      dropEffect: 'copy',
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

    const search = wrapper.get('input[type="search"]')
    await search.setValue('user')
    await wrapper.get('[aria-label="Use User as root action"]').trigger('click')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Use action')!
      .trigger('click')
    await search.setValue('ring group')
    await wrapper.get('[aria-label="Use Ring Group as root action"]').trigger('dragstart', {
      dataTransfer,
    })

    const fallback = wrapper.get('[aria-label="Add fallback branch"]')
    await fallback.trigger('dragover', { dataTransfer })
    expect(dataTransfer.dropEffect).toBe('none')
    expect(fallback.text()).toBe('+ Add fallback')
    await fallback.trigger('drop', { dataTransfer })

    expect(wrapper.find('[aria-label="Fallback type"]').exists()).toBe(false)
    expect(wrapper.get('[aria-label="New callflow canvas"]').text()).toContain('User')
  })

  it('authors Menu key branches before creation and previews them on the canvas', async () => {
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

    await wrapper.get('[aria-label="Edit callflow name and numbers"]').trigger('click')
    await wrapper.get('input[required]').setValue('Main IVR route')
    await wrapper.get('input[type="checkbox"]').setValue(true)
    await wrapper.get('input[type="search"]').setValue('menu')
    await wrapper.get('[aria-label="Use Menu as root action"]').trigger('click')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Add key route')!
      .trigger('click')

    const canvas = wrapper.get('[aria-label="New callflow canvas"]')
    expect(canvas.text()).toContain('Main IVR')
    expect(canvas.get('[data-callflow-create-branch-label]').text()).toBe('timeout')
    expect(canvas.text()).toContain('User')
    expect(canvas.text()).toContain('Reception')

    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      name: 'Main IVR route',
      destination_type: 'menu',
      destination_id: menuId,
      manage_menu_branches: true,
      menu_branches: [
        {
          key: 'timeout',
          destination_type: 'extension',
          destination_id: extensionId,
        },
      ],
      phone_number_ids: [phoneNumberId],
    })
  })

  it('drops a guided palette action onto the next editable Menu key with a public UUID', async () => {
    const dataTransfer = {
      setData: vi.fn(),
      getData: vi.fn(() => 'voicemail'),
      types: ['application/x-gridpbx-callflow-action', 'text/plain'],
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

    await wrapper.get('[aria-label="Edit callflow name and numbers"]').trigger('click')
    await wrapper.get('input[required]').setValue('Menu drop route')
    await wrapper.get('input[type="checkbox"]').setValue(true)
    const search = wrapper.get('input[type="search"]')
    await search.setValue('menu')
    await wrapper.get('[aria-label="Use Menu as root action"]').trigger('click')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Use action')!
      .trigger('click')

    await search.setValue('voicemail')
    await wrapper.get('[aria-label="Use Voicemail as root action"]').trigger('dragstart', {
      dataTransfer,
    })
    const menuBranchDropZone = wrapper.get('[aria-label="Add menu key branch"]')
    await menuBranchDropZone.trigger('dragover', { dataTransfer })
    expect(menuBranchDropZone.text()).toBe('Drop Voicemail on Timeout')
    expect(dataTransfer.dropEffect).toBe('copy')
    await menuBranchDropZone.trigger('drop', { dataTransfer })

    const branchType = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Menu branch type 1')
    const branchDestination = wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Menu branch destination 1')
    expect(branchType?.props('modelValue')).toBe('voicemail')
    expect(branchDestination?.props('modelValue')).toBe(voicemailId)
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Use action')!
      .trigger('click')

    const canvas = wrapper.get('[aria-label="New callflow canvas"]')
    expect(canvas.get('[data-callflow-create-branch-label]').text()).toBe('timeout')
    expect(canvas.text()).toContain('Reception mailbox')
    await wrapper.get('form').trigger('submit')

    const input = wrapper.emitted('save')?.[0]?.[0]
    expect(input).toMatchObject({
      name: 'Menu drop route',
      destination_type: 'menu',
      destination_id: menuId,
      manage_menu_branches: true,
      menu_branches: [
        {
          key: 'timeout',
          destination_type: 'voicemail',
          destination_id: voicemailId,
        },
      ],
      phone_number_ids: [phoneNumberId],
    })
    expect(JSON.stringify(input)).not.toContain('switch-')
  })

  it('authors, reopens, and removes the wildcard fallback directly on the canvas', async () => {
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

    await wrapper.get('[aria-label="Edit callflow name and numbers"]').trigger('click')
    await wrapper.get('input[required]').setValue('Reception route')
    await wrapper.get('input[type="checkbox"]').setValue(true)
    await wrapper.get('input[type="search"]').setValue('user')
    await wrapper.get('[aria-label="Use User as root action"]').trigger('click')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Use action')!
      .trigger('click')
    await wrapper.get('[aria-label="Add fallback branch"]').trigger('click')
    expect(
      wrapper
        .get('[aria-label="New callflow canvas"]')
        .find('[data-callflow-create-branch-label]')
        .exists(),
    ).toBe(false)
    wrapper
      .findAllComponents(FormListbox)
      .find((listbox) => listbox.props('ariaLabel') === 'Fallback type')!
      .vm.$emit('update:modelValue', 'voicemail')
    await wrapper.vm.$nextTick()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Use fallback')!
      .trigger('click')

    const canvas = wrapper.get('[aria-label="New callflow canvas"]')
    expect(canvas.get('[data-callflow-create-branch-label]').text()).toBe('_')
    expect(canvas.text()).toContain('Reception mailbox')

    await wrapper.get('[aria-label="Configure fallback branch"]').trigger('click')
    expect(wrapper.text()).toContain(
      'The wildcard branch runs when the root action does not complete',
    )
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Use fallback')!
      .trigger('click')

    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      name: 'Reception route',
      destination_type: 'extension',
      destination_id: extensionId,
      manage_fallback: true,
      fallback_destination_type: 'voicemail',
      fallback_destination_id: voicemailId,
      phone_number_ids: [phoneNumberId],
    })

    await wrapper.get('[aria-label="Remove fallback branch"]').trigger('click')
    expect(canvas.find('[data-callflow-create-branch-label]').exists()).toBe(false)
    expect(wrapper.find('[aria-label="Add fallback branch"]').exists()).toBe(true)
  })

  it('authors the Temporal Rule Set match branch before creation', async () => {
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

    await wrapper.get('[aria-label="Edit callflow name and numbers"]').trigger('click')
    await wrapper.get('input[required]').setValue('Business hours route')
    await wrapper.get('input[type="checkbox"]').setValue(true)
    await wrapper.get('input[type="search"]').setValue('time of day')
    await wrapper.get('[aria-label="Use Time of Day as root action"]').trigger('click')

    expect(wrapper.text()).toContain('Weekdays')
    const canvas = wrapper.get('[aria-label="New callflow canvas"]')
    expect(canvas.get('[data-callflow-create-branch-label]').text()).toBe('rule_set')
    expect(canvas.text()).toContain('Reception')

    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      name: 'Business hours route',
      destination_type: 'temporal_rule_set',
      destination_id: temporalRuleSetId,
      manage_temporal_match: true,
      temporal_match_destination_type: 'extension',
      temporal_match_destination_id: extensionId,
      phone_number_ids: [phoneNumberId],
    })
  })

  it('confirms root replacement and clears configuration that belongs to the previous action', async () => {
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

    const search = wrapper.get('input[type="search"]')
    await search.setValue('menu')
    await wrapper.get('[aria-label="Use Menu as root action"]').trigger('click')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Add key route')!
      .trigger('click')
    await search.setValue('user')
    await wrapper.get('[aria-label="Use User as root action"]').trigger('click')

    expect(wrapper.text()).toContain('Replacing Menu with User')
    expect(wrapper.get('[aria-label="New callflow canvas"]').text()).toContain('Menu')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Replace root action')!
      .trigger('click')

    const canvas = wrapper.get('[aria-label="New callflow canvas"]')
    expect(canvas.text()).toContain('User')
    expect(canvas.find('[data-callflow-create-branch-label]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Replacing Menu with User')
  })
})
