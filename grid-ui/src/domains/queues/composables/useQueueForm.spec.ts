import { describe, expect, it } from 'vitest'
import { useQueueForm } from './useQueueForm'

describe('useQueueForm', () => {
  it('validates and normalizes the safe Queue schema fields', () => {
    const { form, validate } = useQueueForm(null)
    form.name = '  Support  '
    form.max_priority = 12

    const result = validate()

    expect(result.success).toBe(true)
    if (!result.success) return
    expect(result.data.name).toBe('Support')
    expect(result.data.max_priority).toBe(12)
  })

  it('requires all custom announcement prompts as one schema object', () => {
    const { form, validate } = useQueueForm(null)
    form.name = 'Support'
    form.announcements_enabled = true
    form.announcement_in_the_queue_media_id = 'b0db1bc1-91ab-4940-b530-9515ec018712'

    const result = validate()

    expect(result.success).toBe(false)
    expect(result.errors.announcement_media).toEqual([
      'Select all four custom announcement prompts or leave all four on the Switch defaults.',
    ])
  })
})
