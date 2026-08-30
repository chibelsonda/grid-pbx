import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { UserIcon } from '@heroicons/vue/24/outline'
import CallflowNodeCard from './CallflowNodeCard.vue'

describe('CallflowNodeCard', () => {
  it('renders the uniform Switch-style editor surface with SVG texture and semantic accents', () => {
    const wrapper = mount(CallflowNodeCard, {
      props: {
        label: 'User',
        module: 'user',
        icon: UserIcon,
        borderClass: 'border-blue-300',
        iconClass: 'text-blue-300',
        detail: 'Reception',
        movable: true,
      },
    })

    expect(wrapper.classes()).toContain('bg-callflow-node')
    expect(wrapper.classes()).toContain('border-blue-300')
    expect(wrapper.find('svg').exists()).toBe(true)
    expect(wrapper.findAll('circle')).toHaveLength(18)
    expect(wrapper.get('.absolute.inset-x-1 > svg').classes()).toContain('size-6')
    expect(wrapper.find('.absolute.inset-x-1 > span > svg').exists()).toBe(false)
    expect(wrapper.text()).toContain('User')
    expect(wrapper.text()).toContain('Reception')
    expect(wrapper.find('footer').exists()).toBe(true)
  })

  it('uses the same surface without an editor footer in the palette variant', () => {
    const wrapper = mount(CallflowNodeCard, {
      props: {
        variant: 'palette',
        label: 'User',
        module: 'user',
        icon: UserIcon,
        borderClass: 'border-blue-300',
        iconClass: 'text-blue-300',
      },
    })

    expect(wrapper.classes()).toContain('bg-callflow-node')
    expect(wrapper.find('footer').exists()).toBe(false)
  })
})
