import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CallflowBranchConnector from './CallflowBranchConnector.vue'

describe('CallflowBranchConnector', () => {
  it('renders the parent stem with the same SVG shaft proportions as the arrow', () => {
    const wrapper = mount(CallflowBranchConnector, { props: { kind: 'parent-stem' } })

    expect(wrapper.element.tagName).toBe('svg')
    expect(wrapper.attributes('data-callflow-parent-stem')).toBe('')
    expect(wrapper.classes()).toContain('w-5')
    expect(wrapper.classes()).toContain('h-3')
    expect(wrapper.get('line').attributes('stroke-width')).toBe('6')
  })

  it.each([
    ['first', '50', '101'],
    ['middle', '-1', '101'],
    ['last', '-1', '50'],
  ] as const)('renders the %s horizontal bus segment as a thick SVG', (kind, start, end) => {
    const wrapper = mount(CallflowBranchConnector, { props: { kind } })

    expect(wrapper.element.tagName).toBe('svg')
    expect(wrapper.attributes('data-callflow-branch-bus')).toBe('')
    expect(wrapper.attributes('data-callflow-branch-position')).toBe(kind)
    expect(wrapper.classes()).toContain('h-1.5')
    expect(wrapper.get('line').attributes('x1')).toBe(start)
    expect(wrapper.get('line').attributes('x2')).toBe(end)
    expect(wrapper.get('line').attributes('stroke-width')).toBe('6')
  })
})
