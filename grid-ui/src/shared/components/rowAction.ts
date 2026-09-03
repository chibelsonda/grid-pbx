export type RowActionIcon =
  | 'view'
  | 'edit'
  | 'delete'
  | 'download'
  | 'manage'
  | 'participants'
  | 'lock'
  | 'unlock'
  | 'enable'
  | 'disable'
  | 'reset'
  | 'copy'
  | 'route'
  | 'play'
  | 'line-keys'
  | 'sync'
  | 'reprovision'

export type RowAction = {
  id: string
  label: string
  icon: RowActionIcon
  disabled?: boolean
  destructive?: boolean
}

export function crudRowActions(canManage: boolean, canDelete = canManage): RowAction[] {
  const actions: RowAction[] = [{ id: 'view', label: 'View details', icon: 'view' }]

  if (canManage) {
    actions.push({ id: 'edit', label: 'Edit', icon: 'edit' })
  }

  if (canDelete) {
    actions.push({ id: 'delete', label: 'Delete', icon: 'delete', destructive: true })
  }

  return actions
}
