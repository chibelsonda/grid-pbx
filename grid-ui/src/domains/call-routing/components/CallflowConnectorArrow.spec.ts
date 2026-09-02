import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CallflowConnectorArrow from './CallflowConnectorArrow.vue'

describe('CallflowConnectorArrow', () => {
  it('renders a large SVG connector using the uniform node background color', () => {
    const wrapper = mount(CallflowConnectorArrow)

    expect(wrapper.element.tagName).toBe('svg')
    expect(wrapper.classes()).toContain('h-9')
    expect(wrapper.classes()).toContain('text-callflow-node')
    expect(wrapper.attributes('viewBox')).toBe('0 0 20 44')
    expect(wrapper.get('line').attributes('stroke-width')).toBe('6')
    expect(wrapper.findAll('path')).toHaveLength(1)
  })
})
