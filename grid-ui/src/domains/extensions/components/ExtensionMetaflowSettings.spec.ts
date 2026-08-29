import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ExtensionMetaflowSettings from './ExtensionMetaflowSettings.vue'

describe('ExtensionMetaflowSettings', () => {
  it('renders editable and locked User metaflow trees through the shared editor', () => {
    const wrapper = mount(ExtensionMetaflowSettings, {
      props: {
        modelValue: {
          binding_digit: '*',
          digit_timeout: 2000,
          listen_on: 'both',
          actions: [
            {
              trigger_type: 'number',
              trigger: '4',
              module: 'hangup',
              data: {},
              children: [],
            },
          ],
        },
        current: {
          binding_digit: '*',
          digit_timeout: 2000,
          listen_on: 'both',
          number_flow_count: 2,
          pattern_flow_count: 0,
          actions: [],
          locked_action_count: 1,
        },
        resources: { media: [], callflows: [], devices: [], extensions: [] },
        fieldErrors: {},
      },
    })

    expect(wrapper.text()).toContain('In-call metaflows')
    expect(wrapper.text()).toContain('Guided action trees')
    expect(wrapper.text()).toContain('1 unsupported or unprojected action tree(s)')
    expect((wrapper.get('input[placeholder="1"]').element as HTMLInputElement).value).toBe('4')
  })
})
