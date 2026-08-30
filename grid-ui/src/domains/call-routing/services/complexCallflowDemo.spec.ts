import { describe, expect, it } from 'vitest'
import { createComplexCallflowDemo } from './complexCallflowDemo'

describe('complex callflow demo', () => {
  it('provides a deep, branched, UI-only route without upstream identifiers', () => {
    const demo = createComplexCallflowDemo()
    const menu = demo.flow?.children.rule_set
    const support = menu?.children['1']

    expect(demo.id).toBe('ui-only-complex-callflow-demo')
    expect(demo.flags).toContain('ui_only')
    expect(demo.node_count).toBe(20)
    expect(demo.max_depth).toBe(6)
    expect(menu?.module).toBe('menu')
    expect(support?.module).toBe('tts')
    expect(menu?.children['2']?.module).toBe('conference')
    expect(menu?.children.timeout?.module).toBe('missed_call_alert')
    expect(menu?.children['*']?.module).toBe('call_forward')
    expect(demo.modules).not.toContain('set_variable')
    expect(demo.modules).not.toContain('check_cid')
    expect(JSON.stringify(demo)).not.toContain('switch-')
  })
})
