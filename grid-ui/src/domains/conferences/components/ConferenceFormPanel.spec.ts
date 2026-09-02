import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ConferenceFormPanel from './ConferenceFormPanel.vue'

describe('ConferenceFormPanel', () => {
  it('keeps validation inline and marks every invalid text control', async () => {
    const wrapper = mount(ConferenceFormPanel, {
      props: {
        record: null,
        options: { owners: [], media: [], playable_media: [] },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    await wrapper.get('input[aria-label="Member numbers"]').setValue('not-a-number')
    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[aria-label="Name"]')
    const memberNumbers = wrapper.get('input[aria-label="Member numbers"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(memberNumbers.attributes('aria-invalid')).toBe('true')
    expect(memberNumbers.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a conference name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })

  it('matches the Basic, Options, and Conference Server workflow', async () => {
    const wrapper = mount(ConferenceFormPanel, {
      props: {
        record: null,
        options: { owners: [], media: [], playable_media: [] },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    const viewTabs = wrapper.find('[aria-label="Form sections"]').findAll('[role="tab"]')
    const advancedTabList = wrapper.find('[aria-label="Conference advanced sections"]')
    const advancedTabs = advancedTabList.findAll('[role="tab"]')

    expect(viewTabs.map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(advancedTabs.map((tab) => tab.text())).toEqual(['Basic', 'Options', 'Conference Server'])
    expect(advancedTabList.attributes('style')).toContain('display: none')
    expect(wrapper.find('input[aria-label="Profile name"]').isVisible()).toBe(false)
    expect(wrapper.find('input[aria-label="General conference numbers"]').isVisible()).toBe(false)

    await viewTabs[1]!.trigger('click')

    expect(advancedTabList.attributes('style') ?? '').not.toContain('display: none')
    expect(advancedTabList.element.closest('article')?.classList).toContain('card-surface')
    expect(wrapper.find('input[aria-label="Name"]').isVisible()).toBe(true)
    expect(wrapper.find('input[aria-label="Profile name"]').isVisible()).toBe(false)

    await wrapper
      .find('[aria-label="Conference advanced sections"]')
      .findAll('[role="tab"]')[1]!
      .trigger('click')

    expect(wrapper.find('[aria-label="Participant entry tone"]').isVisible()).toBe(true)

    await wrapper
      .find('[aria-label="Conference advanced sections"]')
      .findAll('[role="tab"]')[2]!
      .trigger('click')
    await wrapper.vm.$nextTick()

    expect(
      wrapper
        .find('[aria-label="Conference advanced sections"]')
        .findAll('[role="tab"]')[2]!
        .attributes('aria-selected'),
    ).toBe('true')
    expect(wrapper.findAll('[role="tabpanel"]')[2]!.attributes('style')).toBe('')
    expect(
      wrapper.get('input[aria-label="Profile name"]').element.closest('[style*="display: none"]'),
    ).toBeNull()
    expect(
      wrapper
        .get('input[aria-label="General conference numbers"]')
        .element.closest('[style*="display: none"]'),
    ).toBeNull()
    expect(wrapper.text()).toContain('Named conference profiles and control sets')
  })

  it('routes server validation errors to the matching Advanced section', async () => {
    const wrapper = mount(ConferenceFormPanel, {
      props: {
        record: null,
        options: { owners: [], media: [], playable_media: [] },
        saving: false,
        error: null,
        fieldErrors: { profile_name: ['Choose an installed profile.'] },
        canManage: true,
      },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    const viewTabs = wrapper.find('[aria-label="Form sections"]').findAll('[role="tab"]')
    const advancedTabs = wrapper
      .find('[aria-label="Conference advanced sections"]')
      .findAll('[role="tab"]')

    expect(viewTabs[1]!.attributes('aria-selected')).toBe('true')
    expect(advancedTabs[2]!.attributes('aria-selected')).toBe('true')
    expect(wrapper.get('input[aria-label="Profile name"]').attributes('aria-invalid')).toBe('true')
  })
})
