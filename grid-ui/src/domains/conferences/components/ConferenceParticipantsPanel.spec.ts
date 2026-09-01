import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ConferenceParticipantsPanel from './ConferenceParticipantsPanel.vue'
import type { Conference, ConferenceParticipant } from '../types/conference'

const conference = {
  id: 'conference-1',
  name: 'Daily standup',
  runtime: { members: 1, moderators: 0, duration_seconds: 30, is_locked: false },
} as Conference
const participant: ConferenceParticipant = {
  id: 'opaque-handle',
  display_name: 'Ada Lovelace',
  number: '1001',
  is_moderator: false,
  can_speak: true,
  can_hear: false,
  duration_seconds: 30,
}
const mediaId = '11111111-1111-4111-8111-111111111111'

function wrapper() {
  return mount(ConferenceParticipantsPanel, {
    props: {
      conference,
      participants: [participant],
      loading: false,
      controllingId: null,
      playableMedia: [{ id: mediaId, label: 'Welcome message', detail: 'Audio prompt' }],
      playingMedia: false,
      bulkControllingAction: null,
      bulkControlObservation: null,
      error: null,
      canManage: true,
    },
    global: {
      stubs: {
        CrudSlideOver: { template: '<section><slot /></section>' },
      },
    },
  })
}

describe('ConferenceParticipantsPanel', () => {
  it('derives mute and restore-hearing actions from current Switch state', async () => {
    const view = wrapper()
    const mute = view.findAll('button').find((button) => button.text() === 'Mute')
    const restoreHearing = view
      .findAll('button')
      .find((button) => button.text() === 'Restore hearing')

    expect(mute).toBeDefined()
    expect(restoreHearing).toBeDefined()
    await mute!.trigger('click')
    await restoreHearing!.trigger('click')

    expect(view.emitted('control')).toEqual([
      [participant, 'mute'],
      [participant, 'undeaf'],
    ])
  })

  it('requires an inline confirmation before emitting kick', async () => {
    const view = wrapper()
    const kick = view.findAll('button').find((button) => button.text() === 'Kick')

    expect(kick).toBeDefined()
    await kick!.trigger('click')
    expect(view.emitted('control')).toBeUndefined()

    const confirm = view.findAll('button').find((button) => button.text() === 'Confirm kick')
    await confirm!.trigger('click')
    expect(view.emitted('control')).toEqual([[participant, 'kick']])
  })

  it('previews and confirms only eligible non-moderators for room-wide controls', async () => {
    const view = wrapper()
    const muteMembers = view
      .findAll('button')
      .find((button) => button.text().includes('Mute members'))

    expect(muteMembers?.text()).toContain('(1)')
    await muteMembers!.trigger('click')
    expect(view.emitted('bulkControl')).toBeUndefined()
    expect(view.text()).toContain('Confirm mute for 1 eligible participant(s)?')

    await view
      .findAll('button')
      .find((button) => button.text() === 'Confirm room command')!
      .trigger('click')

    expect(view.emitted('bulkControl')).toEqual([['mute', 1, 1]])
  })

  it('shows an honest observed or pending reconciliation result', async () => {
    const view = wrapper()

    await view.setProps({
      bulkControlObservation: {
        action: 'mute',
        status: 'pending',
        targeted_participants: 2,
        observed_participants: 1,
        message:
          'Switch accepted the command; 1 of 2 targeted participant(s) are currently observed in the requested state.',
      },
    })

    expect(view.get('[role="status"]').text()).toContain('Verification pending')
    expect(view.get('[role="status"]').text()).toContain('1 of 2 targeted participant(s)')
  })

  it('requires confirmation and emits only public media and opaque participant identifiers', async () => {
    const view = wrapper()
    const listboxes = view.findAllComponents({ name: 'FormListbox' })

    await listboxes[0]!.vm.$emit('update:modelValue', mediaId)
    await listboxes[1]!.vm.$emit('update:modelValue', participant.id)
    await view
      .findAll('button')
      .find((button) => button.text() === 'Play audio')!
      .trigger('click')

    expect(view.emitted('playMedia')).toBeUndefined()

    await view
      .findAll('button')
      .find((button) => button.text() === 'Confirm playback')!
      .trigger('click')
    expect(view.emitted('playMedia')).toEqual([[mediaId, 'opaque-handle']])
  })

  it('rejects a raw media reference before emitting playback', async () => {
    const view = wrapper()
    const media = view.findAllComponents({ name: 'FormListbox' })[0]!

    await media.vm.$emit('update:modelValue', 'https://example.invalid/audio.mp3')
    await view
      .findAll('button')
      .find((button) => button.text() === 'Play audio')!
      .trigger('click')
    await view
      .findAll('button')
      .find((button) => button.text() === 'Confirm playback')!
      .trigger('click')

    expect(view.emitted('playMedia')).toBeUndefined()
    expect(view.get('[role="alert"]').text()).toBe('Select projected account audio.')
  })
})
