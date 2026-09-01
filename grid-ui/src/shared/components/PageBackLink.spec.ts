import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PageBackLink from './PageBackLink.vue'

describe('PageBackLink', () => {
  it('renders route navigation as a contextual text link', () => {
    const wrapper = mount(PageBackLink, {
      props: { label: 'Back to devices', to: '/devices' },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="to"><slot /></a>',
          },
        },
      },
    })

    expect(wrapper.get('a').attributes('href')).toBe('/devices')
    expect(wrapper.text()).toBe('Back to devices')
  })

  it('emits local workspace navigation from button mode', async () => {
    const wrapper = mount(PageBackLink, { props: { label: 'Back to callflows' } })

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('click')).toEqual([[]])
  })
})
