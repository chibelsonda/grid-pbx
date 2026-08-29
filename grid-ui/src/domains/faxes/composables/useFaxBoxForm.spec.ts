import { describe, expect, it } from 'vitest'
import { useFaxBoxForm } from './useFaxBoxForm'

describe('useFaxBoxForm', () => {
  it('normalizes nullable text and recipient lists', () => {
    const { form, validate } = useFaxBoxForm(null)
    form.name = '  Main fax  '
    form.custom_smtp_email_address = ''
    form.inboundEmailsText = 'ops@example.test, ops@example.test\nowner@example.test'

    expect(validate()).toEqual({
      success: true,
      data: {
        name: 'Main fax',
        owner_id: null,
        caller_id: null,
        caller_name: null,
        fax_header: null,
        fax_identity: null,
        fax_timezone: null,
        retries: 1,
        t38_enabled: false,
        custom_smtp_email_address: null,
        smtp_permission_list: [],
        inbound_notification_emails: ['ops@example.test', 'owner@example.test'],
        outbound_notification_emails: [],
      },
      errors: {},
    })
  })

  it('reports invalid name, retry count, and notification email', () => {
    const { form, validate } = useFaxBoxForm(null)
    form.retries = 5
    form.inboundEmailsText = 'not-an-email'

    const result = validate()

    expect(result.success).toBe(false)
    expect(Object.keys(result.errors)).toEqual(
      expect.arrayContaining(['name', 'retries', 'inbound_notification_emails.0']),
    )
  })
})
