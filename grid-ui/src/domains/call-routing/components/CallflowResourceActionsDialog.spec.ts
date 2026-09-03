import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import CallflowResourceActionsDialog from './CallflowResourceActionsDialog.vue'

describe('CallflowResourceActionsDialog', () => {
  afterEach(() => {
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
  })

  it('opens selected and create actions in new tabs without exposing private identifiers', async () => {
    vi.stubGlobal(
      'ResizeObserver',
      class {
        observe(): void {}
        unobserve(): void {}
        disconnect(): void {}
      },
    )
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'root', component: { template: '<div />' } },
        { path: '/devices/new', name: 'device-create', component: { template: '<div />' } },
        {
          path: '/devices/:deviceId/edit',
          name: 'device-edit',
          component: { template: '<div />' },
        },
      ],
    })
    await router.push('/')
    await router.isReady()
    mount(CallflowResourceActionsDialog, {
      attachTo: document.body,
      props: {
        open: true,
        type: 'device',
        selectedId: '54d9431a-f090-413b-a17e-88e02f0c0b44',
        selectedLabel: 'Reception phone',
      },
      global: { plugins: [router] },
    })
    await nextTick()

    const links = Array.from(document.body.querySelectorAll('a'))
    expect(links).toHaveLength(2)
    expect(links[0]?.getAttribute('href')).toBe(
      '/devices/54d9431a-f090-413b-a17e-88e02f0c0b44/edit',
    )
    expect(links[0]?.getAttribute('target')).toBe('_blank')
    expect(links[0]?.getAttribute('rel')).toBe('noopener noreferrer')
    expect(links[1]?.getAttribute('href')).toBe('/devices/new')
    expect(document.body.textContent).toContain('Reception phone')
    expect(document.body.textContent).not.toContain('switch-')
  })
})
