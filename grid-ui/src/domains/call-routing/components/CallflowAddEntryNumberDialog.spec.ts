import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { phoneNumberApi } from '@/domains/phone-numbers/api/phoneNumberApi'
import { callflowApi } from '../api/callflowApi'
import CallflowAddEntryNumberDialog from './CallflowAddEntryNumberDialog.vue'

vi.mock('@/domains/phone-numbers/api/phoneNumberApi', () => ({
  phoneNumberApi: {
    startSync: vi.fn(),
    syncStatus: vi.fn(),
  },
}))

vi.mock('../api/callflowApi', () => ({
  callflowApi: {
    extensionDirectory: vi.fn(),
    extensionAvailability: vi.fn(),
  },
}))

const dialogStub = {
  props: ['open'],
  template: '<div v-if="open"><slot /></div>',
}

describe('CallflowAddEntryNumberDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('validates and emits a new internal extension', async () => {
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: ['2001'],
        preservedNumbers: ['*97'],
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })

    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Use 2 to 15 digits')
    expect(wrapper.emitted('add')).toBeUndefined()

    await wrapper.get('input[placeholder="e.g. 2999"]').setValue('2999')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('add')).toEqual([[{ type: 'extension', value: '2999' }]])
  })

  it('prevents adding an entry already present on the callflow', async () => {
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: ['2999'],
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })

    await wrapper.get('input[placeholder="e.g. 2999"]').setValue('2999')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.text()).toContain('already configured')
    expect(wrapper.emitted('add')).toBeUndefined()
  })

  it('shows a server field error only beside the affected input', () => {
    const message = 'Extension 1234 is already assigned to another callflow.'
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: [],
        fieldErrors: { extension_numbers: [message] },
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })

    expect(wrapper.get('input[placeholder="e.g. 2999"]').attributes('aria-invalid')).toBe('true')
    expect(
      wrapper.text().match(/Extension 1234 is already assigned to another callflow\./g),
    ).toHaveLength(1)
    expect(wrapper.find('[data-testid="form-error-summary"]').exists()).toBe(false)
  })

  it('uses one dialog summary for a non-field failure', () => {
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: [],
        error: 'Unable to verify the latest assignments.',
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })

    expect(wrapper.findAll('[data-testid="form-error-summary"]')).toHaveLength(1)
    expect(wrapper.text().match(/Unable to verify the latest assignments\./g)).toHaveLength(1)
  })

  it('keeps spare-number discovery available when the projection is empty', async () => {
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        accountId: 'account-1',
        phoneNumbers: [],
        phoneNumberInventory: {
          status: 'stale',
          last_successful_at: null,
          error_message: null,
          total_count: 0,
          unassigned_count: 0,
        },
        phoneNumberIds: [],
        extensionNumbers: [],
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })
    const spareButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Spare number'))

    expect(spareButton?.attributes('disabled')).toBeUndefined()
    await spareButton?.trigger('click')
    expect(wrapper.text()).toContain('No spare numbers are projected')
    expect(wrapper.text()).toContain('Phone-number inventory has not been synchronized yet')
    expect(wrapper.text()).toContain('Number purchasing is unavailable')
  })

  it('refreshes the projected spare-number inventory through the existing sync workflow', async () => {
    vi.mocked(phoneNumberApi.startSync).mockResolvedValue({
      id: 'sync-run-1',
      resource_type: 'phone_numbers',
      status: 'queued',
      error_message: null,
    })
    vi.mocked(phoneNumberApi.syncStatus).mockResolvedValue({
      id: 'sync-run-1',
      resource_type: 'phone_numbers',
      status: 'succeeded',
      error_message: null,
    })
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        accountId: 'account-1',
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: [],
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })
    const spareButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Spare number'))
    await spareButton?.trigger('click')
    const refreshButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Refresh inventory'))
    await refreshButton?.trigger('click')

    expect(phoneNumberApi.startSync).toHaveBeenCalledWith('account-1', false)
    expect(phoneNumberApi.syncStatus).toHaveBeenCalledWith('account-1', 'sync-run-1')
    expect(wrapper.emitted('inventory-refreshed')).toHaveLength(1)

    await wrapper.setProps({
      phoneNumbers: [
        {
          id: 'number-1',
          number: '+15559876543',
          state: 'in_service',
          selected: false,
          available: true,
          assigned_callflow: null,
        },
      ],
    })
    expect(wrapper.text()).toContain('+15559876543')

    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('add')).toEqual([[{ type: 'phone_number', id: 'number-1' }]])
  })

  it('blocks an occupied extension using the server availability result', async () => {
    vi.mocked(callflowApi.extensionAvailability).mockResolvedValue({
      number: '3000',
      available: false,
      reason: 'Extension 3000 is already used by Support route.',
      conflict: {
        source: 'callflow',
        label: 'Support route',
        callflow: { id: 'callflow-2', name: 'Support route' },
      },
      suggested_extension: '3001',
    })
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        accountId: 'account-1',
        callflowId: 'callflow-1',
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: [],
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })

    await wrapper.get('input[placeholder="e.g. 2999"]').setValue('3000')
    await wrapper.get('form').trigger('submit')

    expect(callflowApi.extensionAvailability).toHaveBeenCalledWith(
      'account-1',
      '3000',
      'callflow-1',
    )
    expect(wrapper.text()).toContain('already used by Support route')
    expect(wrapper.emitted('add')).toBeUndefined()
  })

  it('does not add an extension from a superseded availability request', async () => {
    let resolveAvailability!: (
      result: Awaited<ReturnType<typeof callflowApi.extensionAvailability>>,
    ) => void
    vi.mocked(callflowApi.extensionAvailability).mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveAvailability = resolve
        }),
    )
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        accountId: 'account-1',
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: [],
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })

    const extensionInput = wrapper.get('input[placeholder="e.g. 2999"]')
    await extensionInput.setValue('3000')
    await wrapper.get('form').trigger('submit')
    await extensionInput.setValue('3001')
    resolveAvailability({
      number: '3000',
      available: true,
      reason: null,
      conflict: null,
      suggested_extension: '3001',
    })
    await flushPromises()

    expect(callflowApi.extensionAvailability).toHaveBeenCalledWith('account-1', '3000', null)

    expect(wrapper.emitted('add')).toBeUndefined()
  })
})
