import type { RowAction } from '@/shared/components/rowAction'
import { crudRowActions } from '@/shared/components/rowAction'
import { supportsProvisioning } from './deviceForm'
import type { Device } from './types/device'

type DeviceActionState = {
  lineKeysBusy?: boolean
  provisioningBusy?: boolean
}

export function deviceRowActions(
  device: Pick<Device, 'device_type'>,
  canManage: boolean,
  state: DeviceActionState = {},
): RowAction[] {
  const actions = crudRowActions(canManage)

  if (!canManage || !device.device_type || !supportsProvisioning(device.device_type)) {
    return actions
  }

  const deleteIndex = actions.findIndex(({ id }) => id === 'delete')
  const provisioningActions: RowAction[] = [
    {
      id: 'line-keys',
      label: 'Line keys',
      icon: 'line-keys',
      disabled: state.lineKeysBusy,
    },
    {
      id: 'sync',
      label: 'Send check-sync',
      icon: 'sync',
      disabled: state.provisioningBusy,
    },
    {
      id: 'reprovision',
      label: 'Reprovision',
      icon: 'reprovision',
      disabled: state.provisioningBusy,
    },
  ]

  actions.splice(deleteIndex < 0 ? actions.length : deleteIndex, 0, ...provisioningActions)

  return actions
}
