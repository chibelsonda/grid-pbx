import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import type { Account } from '@/domains/accounts/types/account'
import { callflowApi } from '../api/callflowApi'
import CallflowEditorPanel from '../components/CallflowEditorPanel.vue'
import { useCallflowStore } from '../stores/callflowStore'
import type { Callflow, CallflowEditor } from '../types/callRouting'
import CallRoutingPage from './CallRoutingPage.vue'

vi.mock('../api/callflowApi', () => ({
  callflowApi: { list: vi.fn() },
}))

const permissions: Account['permissions'] = {
  can_manage_extensions: true,
  can_manage_devices: true,
  can_manage_voicemail: true,
  can_manage_call_routing: true,
  can_manage_media: true,
  can_sync_call_detail_records: true,
  can_view_services: true,
  can_manage_account_settings: true,
  can_onboard_descendants: false,
}

async function mountPage() {
  window.localStorage.clear()
  vi.mocked(callflowApi.list).mockResolvedValue({
    data: [],
    links: { prev: null, next: null },
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: 0,
      sync: { status: 'healthy', last_successful_at: null, error_message: null },
    },
  })

  const pinia = createPinia()
  setActivePinia(pinia)
  const accounts = useAccountStore()
  accounts.accounts = [
    {
      id: 'account-1',
      name: 'GridPBX',
      realm: 'gridpbx.example.test',
      timezone: 'America/New_York',
      enabled: true,
      organization: { id: 'organization-1', name: 'GridPBX' },
      organization_role: 'account_admin',
      permissions,
    },
  ]
  accounts.selectedId = 'account-1'

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/callflows', component: CallRoutingPage }],
  })
  await router.push('/callflows')
  await router.isReady()

  const wrapper = mount(CallRoutingPage, {
    global: {
      plugins: [pinia, router],
      stubs: {
        CallflowDetailPanel: true,
        CallflowEditorPanel: true,
        CallflowAddEntryNumberDialog: true,
        CallflowNodeEditorPanel: true,
        CallflowInlineNodeEditorPanel: true,
        ConfirmDialog: {
          props: ['open', 'title'],
          template: '<div v-if="open" data-confirm-dialog>{{ title }}</div>',
        },
      },
    },
  })

  return { callflows: useCallflowStore(), wrapper }
}

describe('CallRoutingPage layout', () => {
  it('keeps create actions in the sticky header and confirms before discarding a draft', async () => {
    const { callflows, wrapper } = await mountPage()
    callflows.editorOpen = true
    callflows.editor = { mode: 'create', editable: true } as CallflowEditor
    await wrapper.vm.$nextTick()

    const header = wrapper.get('[data-callflow-page-header]')
    const create = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Create callflow'))
    const cancel = wrapper.findAll('button').find((button) => button.text() === 'Cancel')

    expect(header.classes()).toContain('sticky')
    expect(create?.attributes('form')).toBe('callflow-create-form')

    wrapper.findComponent(CallflowEditorPanel).vm.$emit('dirty-change', true)
    await wrapper.vm.$nextTick()
    await cancel?.trigger('click')

    expect(wrapper.get('[data-confirm-dialog]').text()).toBe('Discard callflow draft?')
  })

  it('shows each callflow name in a dedicated table column', async () => {
    const { callflows, wrapper } = await mountPage()

    callflows.records = [
      {
        id: 'callflow-1',
        name: 'Main support route',
        route_type: 'phone_number',
        numbers: ['+15551234567'],
        patterns: [],
        flags: [],
        modules: ['user'],
        root_module: 'user',
        node_count: 1,
        max_depth: 1,
        feature_code: null,
        flow: null,
        linked_extension: null,
        phone_numbers: [{ id: 'number-1', number: '+15551234567', state: 'in_service' }],
        sync_status: 'healthy',
        last_synced_at: null,
      },
    ]
    await wrapper.vm.$nextTick()

    expect(wrapper.findAll('th').map((header) => header.text())).toContain('Name')
    expect(wrapper.get('[data-callflow-name="callflow-1"]').text()).toBe('Main support route')
  })

  it('aligns the list header with its content and retains full width for the workspace', async () => {
    const { callflows, wrapper } = await mountPage()
    const headerContent = wrapper.get('[data-callflow-page-header] > div')
    const pageContent = wrapper.get('[data-callflow-page-content]')

    expect(headerContent.classes()).toContain('page-container')
    expect(pageContent.classes()).toContain('page-container')

    callflows.detail = {
      id: 'callflow-1',
      name: 'Main support route',
      feature_code: null,
      numbers: ['+15551234567'],
    } as Callflow
    await wrapper.vm.$nextTick()

    expect(headerContent.classes()).not.toContain('page-container')
    expect(headerContent.classes()).toContain('lg:px-8')
    expect(pageContent.classes()).not.toContain('page-container')
    expect(pageContent.classes()).toContain('w-full')
  })
})
