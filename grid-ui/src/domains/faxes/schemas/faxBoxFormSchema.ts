import { z } from 'zod'

const nullableText = (maximum: number) => z.string().trim().max(maximum).nullable()
const email = z.email('Enter a valid email address.').max(255)

export const faxBoxFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a fax-box name.').max(128),
    owner_id: z.uuid('Select a valid owner.').nullable(),
    caller_id: nullableText(64),
    caller_name: nullableText(128),
    fax_header: nullableText(128),
    fax_identity: nullableText(64),
    fax_timezone: nullableText(255),
    retries: z.number().int().min(0).max(4),
    t38_enabled: z.boolean(),
    custom_smtp_email_address: email.nullable(),
    smtp_permission_list: z.array(z.string().trim().min(1).max(255)).max(50),
    inbound_notification_emails: z.array(email).max(20),
    outbound_notification_emails: z.array(email).max(20),
  })
  .strict()
