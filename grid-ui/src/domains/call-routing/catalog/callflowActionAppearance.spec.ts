import { describe, expect, it } from 'vitest'
import { callflowActionAppearance } from './callflowActionAppearance'

describe('callflowActionAppearance', () => {
  it.each([
    ['device', 'border-blue-300', 'text-blue-300'],
    ['play', 'border-violet-300', 'text-violet-300'],
    ['voicemail', 'border-cyan-300', 'text-cyan-300'],
    ['check_cid', 'border-amber-300', 'text-amber-300'],
    ['temporal_route', 'border-emerald-300', 'text-emerald-300'],
    ['hangup', 'border-slate-400', 'text-slate-200'],
  ])('uses stable semantic accents for %s', (module, borderClass, iconClass) => {
    expect(callflowActionAppearance(module).nodeBorder).toContain(borderClass)
    expect(callflowActionAppearance(module).nodeIcon).toContain(iconClass)
  })

  it('reserves rose for unresolved Switch references', () => {
    expect(callflowActionAppearance('device', true).nodeBorder).toContain('border-rose-300')
    expect(callflowActionAppearance('device', true).nodeIcon).toContain('text-rose-300')
  })
})
