import { computed, reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { faxBoxFormSchema } from '../schemas/faxBoxFormSchema'
import type { FaxBox, FaxBoxInput } from '../types/fax'

type FaxBoxFormState = Omit<
  FaxBoxInput,
  'smtp_permission_list' | 'inbound_notification_emails' | 'outbound_notification_emails'
> & {
  smtpPermissionsText: string
  inboundEmailsText: string
  outboundEmailsText: string
}

function split(value: string): string[] {
  return [
    ...new Set(
      value
        .split(/[\n,]+/)
        .map((item) => item.trim())
        .filter(Boolean),
    ),
  ]
}

function nullable(value: string | null): string | null {
  const normalized = value?.trim() ?? ''
  return normalized === '' ? null : normalized
}

export function useFaxBoxForm(record: FaxBox | null) {
  const form = reactive<FaxBoxFormState>({
    name: record?.name ?? '',
    owner_id: record?.owner?.id ?? null,
    caller_id: record?.caller_id ?? null,
    caller_name: record?.caller_name ?? null,
    fax_header: record?.fax_header ?? null,
    fax_identity: record?.fax_identity ?? null,
    fax_timezone: record?.fax_timezone ?? null,
    retries: record?.retries ?? 1,
    t38_enabled: record?.t38_enabled ?? false,
    custom_smtp_email_address: record?.custom_smtp_email_address ?? null,
    smtpPermissionsText: record?.smtp_permission_list.join('\n') ?? '',
    inboundEmailsText: record?.inbound_notification_emails.join(', ') ?? '',
    outboundEmailsText: record?.outbound_notification_emails.join(', ') ?? '',
  })
  const validationErrors = ref<FormErrors>({})
  const smtpPermissionList = computed(() => split(form.smtpPermissionsText))
  const inboundNotificationEmails = computed(() => split(form.inboundEmailsText))
  const outboundNotificationEmails = computed(() => split(form.outboundEmailsText))

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<FaxBoxInput> {
    const result = validateForm(faxBoxFormSchema, {
      name: form.name.trim(),
      owner_id: form.owner_id,
      caller_id: nullable(form.caller_id),
      caller_name: nullable(form.caller_name),
      fax_header: nullable(form.fax_header),
      fax_identity: nullable(form.fax_identity),
      fax_timezone: nullable(form.fax_timezone),
      retries: form.retries,
      t38_enabled: form.t38_enabled,
      custom_smtp_email_address: nullable(form.custom_smtp_email_address),
      smtp_permission_list: smtpPermissionList.value,
      inbound_notification_emails: inboundNotificationEmails.value,
      outbound_notification_emails: outboundNotificationEmails.value,
    })
    validationErrors.value = result.errors

    return result
  }

  return { form, validate, validationErrors }
}
