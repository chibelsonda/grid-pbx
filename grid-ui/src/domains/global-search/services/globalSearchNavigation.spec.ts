import { describe, expect, it } from 'vitest'
import type { GlobalSearchResult, GlobalSearchType } from '../types/globalSearch'
import { globalSearchDestination } from './globalSearchNavigation'

const id = 'c6eb1509-a90b-451a-ab11-99f9d70d87af'

function result(type: GlobalSearchType): GlobalSearchResult {
  return { id, type, title: 'Support', subtitle: '', matched_field: 'name' }
}

describe('global search navigation', () => {
  it('uses direct detail routes where the UI has one', () => {
    expect(globalSearchDestination(result('extension'))).toEqual({
      name: 'extension-detail',
      params: { extensionId: id },
    })
    expect(globalSearchDestination(result('device'))).toEqual({
      name: 'device-detail',
      params: { deviceId: id },
    })
    expect(globalSearchDestination(result('voicemail_box'))).toEqual({
      name: 'voicemail-detail',
      params: { voicemailBoxId: id },
    })
    expect(globalSearchDestination(result('media'))).toEqual({
      name: 'media',
      query: { media: id },
    })
    expect(globalSearchDestination(result('recording'))).toEqual({
      name: 'recordings',
      query: { recording: id },
    })
    expect(globalSearchDestination(result('fax_box'))).toEqual({
      name: 'faxes',
      query: { fax_box: id },
    })
    expect(globalSearchDestination(result('blacklist'))).toEqual({
      name: 'blacklists',
      query: { blacklist: id },
    })
    expect(globalSearchDestination(result('caller_id_list'))).toEqual({
      name: 'caller-id-lists',
      query: { caller_id_list: id },
    })
  })

  it('uses safe list filters for resources without detail routes', () => {
    expect(globalSearchDestination(result('phone_number'))).toEqual({
      name: 'phone-numbers',
      query: { search: 'Support' },
    })
    expect(globalSearchDestination(result('callflow'))).toEqual({
      name: 'call-routing',
      query: { callflow: id },
    })
  })
})
