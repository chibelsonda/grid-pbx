import type { RouteLocationRaw } from 'vue-router'
import type { GlobalSearchResult } from '../types/globalSearch'

export function globalSearchDestination(result: GlobalSearchResult): RouteLocationRaw {
  switch (result.type) {
    case 'extension':
      return { name: 'extension-detail', params: { extensionId: result.id } }
    case 'device':
      return { name: 'device-detail', params: { deviceId: result.id } }
    case 'voicemail_box':
      return { name: 'voicemail-detail', params: { voicemailBoxId: result.id } }
    case 'callflow':
      return { name: 'call-routing', query: { callflow: result.id } }
    case 'phone_number':
      return { name: 'phone-numbers', query: { search: result.title } }
    case 'queue':
      return { name: 'queues', query: { search: result.title } }
    case 'menu':
      return { name: 'menus', query: { search: result.title } }
    case 'conference':
      return { name: 'conferences', query: { search: result.title } }
    case 'directory':
      return { name: 'directories', query: { search: result.title } }
    case 'group':
      return { name: 'groups', query: { search: result.title } }
    case 'media':
      return { name: 'media', query: { media: result.id } }
    case 'recording':
      return { name: 'recordings', query: { recording: result.id } }
    case 'fax_box':
      return { name: 'faxes', query: { fax_box: result.id } }
    case 'blacklist':
      return { name: 'blacklists', query: { blacklist: result.id } }
    case 'caller_id_list':
      return { name: 'caller-id-lists', query: { caller_id_list: result.id } }
  }
}
