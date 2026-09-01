import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CallflowEntryNode from './CallflowEntryNode.vue'

describe('CallflowEntryNode', () => {
  it('keeps number addition separate from the shared callflow editor', async () => {
    const wrapper = mount(CallflowEntryNode, {
      props: {
        name: 'Main line',
        entries: [{ value: '2999', kind: 'Number' }],
        editable: true,
      },
    })

    await wrapper.get('[aria-label="2999. Edit callflow name and numbers"]').trigger('click')
    await wrapper.get('[aria-label="Add callflow entry number"]').trigger('click')
    await wrapper.get('[aria-label="Edit callflow name and numbers"]').trigger('click')

    expect(wrapper.emitted('edit')).toHaveLength(2)
    expect(wrapper.emitted('add-entry')).toEqual([[]])
  })

  it('keeps additional entry-point information compact', () => {
    const wrapper = mount(CallflowEntryNode, {
      props: {
        entries: [
          { value: '2999', kind: 'Number' },
          { value: '+15550001111', kind: 'Number' },
          { value: '^1800', kind: 'Pattern' },
        ],
      },
    })

    expect(wrapper.text()).toContain('2999')
    expect(wrapper.text()).toContain('+15550001111')
    expect(wrapper.text()).toContain('+1 more')
    expect(wrapper.find('button').exists()).toBe(false)
  })
})
