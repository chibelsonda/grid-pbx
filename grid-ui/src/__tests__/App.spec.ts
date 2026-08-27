import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StatCard from '@/components/StatCard.vue'
import { UserGroupIcon } from '@heroicons/vue/24/outline'

describe('StatCard', () => {
  it('renders its value and supporting detail', () => {
    const wrapper = mount(StatCard, {
      props: {
        label: 'Extensions',
        value: '12',
        detail: 'Two added this week',
        icon: UserGroupIcon,
        tone: 'primary',
      },
    })

    expect(wrapper.text()).toContain('Extensions')
    expect(wrapper.text()).toContain('12')
    expect(wrapper.text()).toContain('Two added this week')
  })
})
