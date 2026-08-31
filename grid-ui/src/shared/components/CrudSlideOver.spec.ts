import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CrudSlideOver from './CrudSlideOver.vue'

describe('CrudSlideOver', () => {
  it('renders reusable form content inline when embedded in a workspace', () => {
    const wrapper = mount(CrudSlideOver, {
      props: { title: 'Create call route', embedded: true },
      slots: { default: '<p>Draft route form</p>' },
    })

    expect(wrapper.get('[data-testid="embedded-crud-panel"]').text()).toContain('Create call route')
    expect(wrapper.get('[data-testid="embedded-crud-content"]').text()).toContain(
      'Draft route form',
    )
    expect(wrapper.find('[data-testid="slide-over-panel"]').exists()).toBe(false)
  })
})
