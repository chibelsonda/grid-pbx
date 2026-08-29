import { reactive } from 'vue'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ExtensionCredentialsProfile from './ExtensionCredentialsProfile.vue'

describe('ExtensionCredentialsProfile', () => {
  it('removes and restores configured login credentials without displaying a secret', async () => {
    const credentials = reactive({
      username: 'alice.operator' as string | null,
      password: null as string | null,
      password_confirmation: null as string | null,
      require_password_update: false,
      clear_credentials: false,
    })
    const wrapper = mount(ExtensionCredentialsProfile, {
      props: {
        modelValue: credentials,
        fieldErrors: {},
        editing: true,
        originalUsername: 'alice.operator',
        passwordConfigured: true,
      },
    })

    expect(wrapper.text()).toContain('Configured')
    expect(wrapper.text()).toContain('never returned to GridPBX')

    await wrapper.get('button').trigger('click')
    expect(credentials.username).toBeNull()
    expect(credentials.clear_credentials).toBe(true)
    expect(wrapper.text()).toContain('Login credentials will be removed')

    await wrapper.get('button').trigger('click')
    expect(credentials.username).toBe('alice.operator')
    expect(credentials.clear_credentials).toBe(false)
  })

  it('marks credential controls with validation errors', () => {
    const wrapper = mount(ExtensionCredentialsProfile, {
      props: {
        modelValue: {
          username: 'alice.changed',
          password: null,
          password_confirmation: null,
          require_password_update: false,
          clear_credentials: false,
        },
        fieldErrors: {
          password: ['Enter a password.'],
          password_confirmation: ['Passwords do not match.'],
        },
      },
    })

    expect(wrapper.get('input[type="password"]').classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a password.')
    expect(wrapper.text()).toContain('Passwords do not match.')
  })
})
