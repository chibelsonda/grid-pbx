import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import CallflowNodeInfoDialog from './CallflowNodeInfoDialog.vue'

describe('CallflowNodeInfoDialog', () => {
  afterEach(() => {
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
  })

  it('presents node information in an accessible modal', async () => {
    vi.stubGlobal(
      'ResizeObserver',
      class {
        observe(): void {}
        unobserve(): void {}
        disconnect(): void {}
      },
    )
    mount(CallflowNodeInfoDialog, {
      attachTo: document.body,
      props: {
        open: true,
        title: 'User: Reception',
        breadcrumb: 'Root / Key 1',
      },
      slots: { default: '<p>Node controls</p>' },
    })
    await nextTick()

    expect(document.body.querySelector('[role="dialog"]')).not.toBeNull()
    expect(document.body.textContent).toContain('User: Reception')
    expect(document.body.textContent).toContain('Root / Key 1')
    expect(document.body.textContent).toContain('Node controls')
  })
})
