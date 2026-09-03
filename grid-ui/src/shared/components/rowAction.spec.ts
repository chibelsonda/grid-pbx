import { describe, expect, it } from 'vitest'
import { crudRowActions } from './rowAction'

describe('crudRowActions', () => {
  it('provides view, edit, and safeguarded delete actions for manageable entities', () => {
    expect(crudRowActions(true)).toEqual([
      { id: 'view', label: 'View details', icon: 'view' },
      { id: 'edit', label: 'Edit', icon: 'edit' },
      { id: 'delete', label: 'Delete', icon: 'delete', destructive: true },
    ])
  })

  it('keeps read-only entities limited to view details', () => {
    expect(crudRowActions(false)).toEqual([{ id: 'view', label: 'View details', icon: 'view' }])
  })
})
