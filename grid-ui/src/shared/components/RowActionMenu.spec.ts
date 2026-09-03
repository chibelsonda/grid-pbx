import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import RowActionMenu from './RowActionMenu.vue'

describe('RowActionMenu', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('opens an accessible icon menu and emits the selected entity action', async () => {
    const wrapper = mount(RowActionMenu, {
      attachTo: document.body,
      props: {
        label: 'Actions for Reception',
        actions: [
          { id: 'view', label: 'View details', icon: 'view' },
          { id: 'edit', label: 'Edit', icon: 'edit' },
          { id: 'delete', label: 'Delete', icon: 'delete', destructive: true },
        ],
      },
    })

    const trigger = wrapper.get('button[aria-label="Actions for Reception"]')
    expect(trigger.get('svg').attributes('aria-hidden')).toBe('true')

    await trigger.trigger('click')

    const menu = document.body.querySelector('[data-testid="row-action-menu"]')
    expect(menu).not.toBeNull()
    expect(menu?.querySelectorAll('button')).toHaveLength(3)
    expect(menu?.querySelectorAll('svg')).toHaveLength(3)

    const deleteButton = Array.from(menu?.querySelectorAll('button') ?? []).find(
      (item) => item.textContent?.trim() === 'Delete',
    )
    expect(deleteButton).toBeDefined()
    deleteButton?.click()
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('select')).toEqual([['delete']])
    expect(document.body.querySelector('[data-testid="row-action-menu"]')).toBeNull()
  })
})
