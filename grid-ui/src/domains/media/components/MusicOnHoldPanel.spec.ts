import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormListbox from '@/shared/components/FormListbox.vue'
import MusicOnHoldPanel from './MusicOnHoldPanel.vue'
import type { Media } from '../types/media'

function media(id: string, isMusicOnHold = false): Media {
  return {
    id,
    name: 'Lobby loop',
    description: null,
    language: null,
    media_source: 'upload',
    content_type: 'audio/mpeg',
    content_length: 100,
    prompt_id: null,
    streamable: true,
    is_music_on_hold: isMusicOnHold,
    last_synced_at: null,
    sync_status: 'healthy',
    created_at: null,
    updated_at: null,
  }
}

describe('MusicOnHoldPanel', () => {
  it('emits only a Zod-validated public media UUID', async () => {
    const validId = '84aaa521-b44b-4ac2-8c8c-f2fcaa82d746'
    const wrapper = mount(MusicOnHoldPanel, {
      props: {
        records: [media('raw-switch-media-id', true), media(validId)],
        saving: false,
        error: null,
      },
      global: { stubs: { CrudSlideOver: { template: '<div><slot /></div>' } } },
    })

    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Select valid account media.')
    expect(wrapper.emitted('save')).toBeUndefined()

    wrapper.findComponent(FormListbox).vm.$emit('update:modelValue', validId)
    await wrapper.vm.$nextTick()
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')).toEqual([[validId]])
  })
})
