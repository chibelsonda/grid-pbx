import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormErrorSummary from './FormErrorSummary.vue'

describe('FormErrorSummary', () => {
  it('renders all unique field errors in a persistent accessible summary', () => {
    const wrapper = mount(FormErrorSummary, {
      props: {
        fieldErrors: {
          name: ['Enter a name.'],
          extension: ['Enter an extension.', 'Enter a name.'],
        },
      },
    })

    expect(wrapper.get('[role="alert"]').text()).toContain('Please review the highlighted fields')
    expect(wrapper.findAll('li').map((item) => item.text())).toEqual([
      'Enter a name.',
      'Enter an extension.',
    ])
  })

  it('stays hidden when no error is present', () => {
    expect(mount(FormErrorSummary).find('[role="alert"]').exists()).toBe(false)
  })
})
