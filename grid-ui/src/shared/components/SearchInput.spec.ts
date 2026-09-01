import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import SearchInput from './SearchInput.vue'

afterEach(() => vi.useRealTimers())

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

  it('emits one debounced live-search event for the latest input', async () => {
    vi.useFakeTimers()
    const wrapper = mount(SearchInput, {
      props: { modelValue: '', label: 'Search devices', live: true, debounceMs: 250 },
    })
    const input = wrapper.get('input[type="search"]')

    await input.setValue('desk')
    await input.setValue('desk phone')
    expect(wrapper.emitted('search')).toBeUndefined()

    await vi.advanceTimersByTimeAsync(249)
    expect(wrapper.emitted('search')).toBeUndefined()
    await vi.advanceTimersByTimeAsync(1)
    expect(wrapper.emitted('search')).toEqual([['desk phone']])
  })
})
