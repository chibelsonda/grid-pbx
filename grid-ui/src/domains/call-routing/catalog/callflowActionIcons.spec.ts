import { describe, expect, it } from 'vitest'
import {
  ArrowLeftStartOnRectangleIcon,
  ArrowPathIcon,
  ArrowRightStartOnRectangleIcon,
  BackspaceIcon,
  BookOpenIcon,
  CloudArrowUpIcon,
  DocumentMagnifyingGlassIcon,
  EnvelopeIcon,
  EnvelopeOpenIcon,
  EyeSlashIcon,
  LightBulbIcon,
  PhoneXMarkIcon,
  PlusCircleIcon,
  PrinterIcon,
  QuestionMarkCircleIcon,
  ShareIcon,
  Squares2X2Icon,
  StopCircleIcon,
  UserMinusIcon,
  UserPlusIcon,
} from '@heroicons/vue/24/outline'
import { callflowActionCatalog, searchableCallflowActions } from './callflowActionCatalog'
import { callflowActionIcon } from './callflowActionIcons'

describe('callflowActionIcon', () => {
  it('assigns a deliberate icon to every palette and compatibility module', () => {
    const actions = [
      ...callflowActionCatalog.flatMap((category) => category.actions),
      ...searchableCallflowActions,
    ]

    for (const action of actions) {
      expect(
        callflowActionIcon(action.module, { action: action.action }),
        `${action.id} should not use the generic fallback icon`,
      ).not.toBe(Squares2X2Icon)
    }
  })

  it('uses Switch-aligned modern icons for clear module functions', () => {
    expect(callflowActionIcon('callflow')).toBe(ShareIcon)
    expect(callflowActionIcon('directory')).toBe(BookOpenIcon)
    expect(callflowActionIcon('voicemail')).toBe(EnvelopeIcon)
    expect(callflowActionIcon('faxbox')).toBe(PrinterIcon)
    expect(callflowActionIcon('receive_fax')).toBe(PrinterIcon)
    expect(callflowActionIcon('fax_detect')).toBe(DocumentMagnifyingGlassIcon)
    expect(callflowActionIcon('manual_presence')).toBe(LightBulbIcon)
    expect(callflowActionIcon('flush_dtmf')).toBe(BackspaceIcon)
    expect(callflowActionIcon('privacy')).toBe(EyeSlashIcon)
    expect(callflowActionIcon('webhook')).toBe(CloudArrowUpIcon)
    expect(callflowActionIcon('hangup')).toBe(PhoneXMarkIcon)
  })

  it('distinguishes operation variants whose labels have different meanings', () => {
    expect(callflowActionIcon('hotdesk', { action: 'login' })).toBe(ArrowRightStartOnRectangleIcon)
    expect(callflowActionIcon('hotdesk', { action: 'logout' })).toBe(ArrowLeftStartOnRectangleIcon)
    expect(callflowActionIcon('hotdesk', { action: 'toggle' })).toBe(ArrowPathIcon)
    expect(callflowActionIcon('prepend_cid', { action: 'prepend' })).toBe(PlusCircleIcon)
    expect(callflowActionIcon('prepend_cid', { action: 'reset' })).toBe(ArrowPathIcon)
    expect(callflowActionIcon('record_call', { action: 'stop' })).toBe(StopCircleIcon)
    expect(callflowActionIcon('ring_group_toggle', { action: 'login' })).toBe(UserPlusIcon)
    expect(callflowActionIcon('ring_group_toggle', { action: 'logout' })).toBe(UserMinusIcon)
    expect(callflowActionIcon('acdc_queue', { action: 'login' })).toBe(UserPlusIcon)
    expect(callflowActionIcon('acdc_queue', { action: 'logout' })).toBe(UserMinusIcon)
    expect(callflowActionIcon('voicemail', { action: 'check' })).toBe(EnvelopeOpenIcon)
  })

  it('reserves fallback icons for unknown modules', () => {
    expect(callflowActionIcon('unknown')).toBe(Squares2X2Icon)
    expect(callflowActionIcon('unknown', { unresolved: true })).toBe(QuestionMarkCircleIcon)
  })
})
