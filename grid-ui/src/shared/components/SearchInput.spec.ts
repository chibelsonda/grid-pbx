import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SearchInput from './SearchInput.vue'

describe('SearchInput', () => {
  it('provides an accessible search input and emits string updates', async () => {
    const wrapper = mount(SearchInput, {
      props: { modelValue: '', label: 'Search queues', placeholder: 'Find a queue…' },
    })

    const input = wrapper.get('input[type="search"]')
    expect(input.attributes('aria-label')).toBe('Search queues')
    expect(input.attributes('placeholder')).toBe('Find a queue…')
    await input.setValue('support')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['support'])
  })
})
