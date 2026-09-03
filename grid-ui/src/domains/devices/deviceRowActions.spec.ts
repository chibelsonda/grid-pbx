import { describe, expect, it } from 'vitest'
import { deviceRowActions } from './deviceRowActions'

describe('deviceRowActions', () => {
  it('adds provisioning actions for manageable device types that support them', () => {
    expect(deviceRowActions({ device_type: 'sip_device' }, true)).toEqual([
      { id: 'view', label: 'View details', icon: 'view' },
      { id: 'edit', label: 'Edit', icon: 'edit' },
      { id: 'line-keys', label: 'Line keys', icon: 'line-keys', disabled: undefined },
      { id: 'sync', label: 'Send check-sync', icon: 'sync', disabled: undefined },
      { id: 'reprovision', label: 'Reprovision', icon: 'reprovision', disabled: undefined },
      { id: 'delete', label: 'Delete', icon: 'delete', destructive: true },
    ])
  })

  it.each(['sip_device', 'fax', 'ata'] as const)(
    'supports provisioning actions for %s devices',
    (deviceType) => {
      expect(deviceRowActions({ device_type: deviceType }, true).map(({ id }) => id)).toContain(
        'reprovision',
      )
    },
  )

  it('keeps unsupported and read-only devices free of provisioning actions', () => {
    expect(deviceRowActions({ device_type: 'softphone' }, true).map(({ id }) => id)).toEqual([
      'view',
      'edit',
      'delete',
    ])
    expect(deviceRowActions({ device_type: 'sip_device' }, false).map(({ id }) => id)).toEqual([
      'view',
    ])
  })

  it('disables only the provisioning action group that is currently busy', () => {
    const actions = deviceRowActions({ device_type: 'ata' }, true, {
      lineKeysBusy: true,
      provisioningBusy: false,
    })

    expect(actions.find(({ id }) => id === 'line-keys')?.disabled).toBe(true)
    expect(actions.find(({ id }) => id === 'sync')?.disabled).toBe(false)
    expect(actions.find(({ id }) => id === 'reprovision')?.disabled).toBe(false)
  })
})
