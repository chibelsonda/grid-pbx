import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { nextTick } from 'vue'
import { describe, expect, it } from 'vitest'
import SidebarNavigation from './SidebarNavigation.vue'

const routes = [
  { path: '/', component: { template: '<div />' } },
  { path: '/devices', component: { template: '<div />' } },
  { path: '/phone-numbers', component: { template: '<div />' } },
  { path: '/billing', component: { template: '<div />' } },
  { path: '/:pathMatch(.*)*', component: { template: '<div />' } },
]

async function mountNavigation(
  path: string,
  props: { collapsed: boolean; mobile?: boolean; logoUrl?: string | null },
) {
  const router = createRouter({ history: createMemoryHistory(), routes })
  await router.push(path)
  await router.isReady()

  const wrapper = mount(SidebarNavigation, {
    props,
    global: {
      plugins: [router],
      stubs: {
        TransitionRoot: {
          props: ['show'],
          template: '<div v-if="show"><slot /></div>',
        },
      },
    },
  })

  return { router, wrapper }
}

describe('SidebarNavigation', () => {
  it('reveals the active group and keeps only one cloud-phone group open', async () => {
    const { router, wrapper } = await mountNavigation('/devices', { collapsed: false })
    const people = wrapper.get('button[aria-controls="sidebar-group-people-endpoints"]')
    const routing = wrapper.get('button[aria-controls="sidebar-group-numbers-routing"]')

    expect(people.attributes('aria-expanded')).toBe('true')
    expect(wrapper.text()).toContain('Devices')

    await routing.trigger('click')

    expect(people.attributes('aria-expanded')).toBe('false')
    expect(routing.attributes('aria-expanded')).toBe('true')
    expect(
      wrapper.findAll('button[aria-controls^="sidebar-group-"][aria-expanded="true"]'),
    ).toHaveLength(1)
    expect(wrapper.get('a[href="/phone-numbers"]')).toBeTruthy()

    await routing.trigger('click')
    expect(routing.attributes('aria-expanded')).toBe('false')

    await router.push('/billing')
    await router.push('/devices')
    await nextTick()
    expect(people.attributes('aria-expanded')).toBe('true')
  })

  it('uses labelled icon controls and direct links in collapsed mode', async () => {
    const { wrapper } = await mountNavigation('/billing', { collapsed: true })

    expect(wrapper.get('button[aria-label^="People & Endpoints"]')).toBeTruthy()
    expect(wrapper.get('a[aria-label="Billing"]').attributes('href')).toBe('/billing')
    expect(wrapper.text()).not.toContain('Cloud phone system')

    await wrapper.get('button[aria-label^="Activity."]').trigger('click')
    expect(wrapper.emitted('collapse')).toHaveLength(1)

    await wrapper.setProps({ collapsed: false })
    expect(
      wrapper.get('button[aria-controls="sidebar-group-activity"]').attributes('aria-expanded'),
    ).toBe('true')
  })

  it('closes the mobile navigation after selecting a route', async () => {
    const { wrapper } = await mountNavigation('/devices', { collapsed: false, mobile: true })

    await wrapper.get('a[href="/devices"]').trigger('click')

    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('uses an organization logo when one is available and keeps the default fallback', async () => {
    const { wrapper } = await mountNavigation('/', {
      collapsed: false,
      logoUrl: 'blob:organization-logo',
    })

    expect(wrapper.get('img[alt="Organization logo"]').attributes('src')).toBe(
      'blob:organization-logo',
    )
    expect(wrapper.find('span.sidebar-accent-bg').exists()).toBe(false)

    await wrapper.setProps({ logoUrl: null })
    expect(wrapper.find('img[alt="Organization logo"]').exists()).toBe(false)
    expect(wrapper.find('span.sidebar-accent-bg').exists()).toBe(true)
  })
})
