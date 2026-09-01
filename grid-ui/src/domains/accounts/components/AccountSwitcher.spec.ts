import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { describe, expect, it } from 'vitest'
import type { Account } from '@/domains/accounts/types/account'
import AccountSwitcher from './AccountSwitcher.vue'

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

function account(
  id: string,
  name: string,
  realm: string,
  organization: string,
  enabled = true,
): Account {
  return {
    id,
    name,
    realm,
    timezone: 'Asia/Manila',
    enabled,
    organization: { id: `organization-${id}`, name: organization },
    organization_role: 'account_administrator',
    permissions,
  }
}

const accounts = [
  account('account-one', 'GridPBX', 'gridpbx.example.test', 'Grid Organization'),
  account('account-two', 'Roanna Leonard', 'roanna.example.test', 'Leonard Group'),
  account('account-three', 'Disabled Branch', 'disabled.example.test', 'Grid Organization', false),
]

describe('AccountSwitcher', () => {
  it('filters supplied authorized accounts immediately by account name or realm', async () => {
    const wrapper = mount(AccountSwitcher, {
      props: { accounts, selectedId: 'account-one' },
    })

    await wrapper.get('[aria-label^="Current account: GridPBX"]').trigger('click')
    await nextTick()
    const search = wrapper.get('input[aria-label="Search accounts"]')

    expect(wrapper.findAll('[data-account-option]')).toHaveLength(3)
    await search.setValue('roanna.example')
    expect(wrapper.findAll('[data-account-option]')).toHaveLength(1)
    expect(wrapper.get('[data-account-option]').attributes('aria-label')).toBe(
      'Switch to Roanna Leonard',
    )

    await search.setValue('grid')
    expect(wrapper.findAll('[data-account-option]')).toHaveLength(1)
    expect(wrapper.get('[data-account-option]').attributes('aria-label')).toBe('Switch to GridPBX')

    await search.setValue('leonard group')
    expect(wrapper.findAll('[data-account-option]')).toHaveLength(0)

    await search.setValue('raw-switch-id')
    expect(wrapper.findAll('[data-account-option]')).toHaveLength(0)
    expect(wrapper.text()).toContain('No matching accounts')
  })

  it('emits only the selected public account UUID and prevents disabled selection', async () => {
    const wrapper = mount(AccountSwitcher, {
      props: { accounts, selectedId: 'account-one' },
    })

    await wrapper.get('[aria-label^="Current account: GridPBX"]').trigger('click')
    await nextTick()
    await wrapper.get('[aria-label="Switch to Roanna Leonard"]').trigger('click')

    expect(wrapper.emitted('select')).toEqual([['account-two']])

    await wrapper.get('[aria-label^="Current account: GridPBX"]').trigger('click')
    await nextTick()
    const disabled = wrapper.get('[aria-label="Switch to Disabled Branch"]')
    expect(disabled.attributes('disabled')).toBeDefined()
    await disabled.trigger('click')
    expect(wrapper.emitted('select')).toEqual([['account-two']])
  })
})
