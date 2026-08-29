import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { accountSettingsSchema } from '../schemas/accountSettingsSchema'
import type { AccountDetail, AccountSettingsInput } from '../types/account'

export function useAccountSettingsForm(account: AccountDetail) {
  const form = reactive({
    name: account.name,
    organization_name: account.configuration.organization_name ?? '',
    timezone: account.timezone ?? '',
    language: account.configuration.language ?? '',
    call_waiting_enabled: account.configuration.call_waiting_enabled,
    do_not_disturb_enabled: account.configuration.do_not_disturb_enabled,
    outbound_privacy: account.configuration.outbound_privacy,
    show_rate: account.configuration.show_rate,
    ringtone_internal: account.configuration.ringtone_internal ?? '',
    ringtone_external: account.configuration.ringtone_external ?? '',
    caller_id: {
      internal: {
        name: account.configuration.caller_id.internal.name ?? '',
        number: account.configuration.caller_id.internal.number ?? '',
      },
      external: {
        name: account.configuration.caller_id.external.name ?? '',
        phone_number_id: account.configuration.caller_id.external.phone_number_id,
        preserve_number: account.configuration.caller_id.external.unresolved,
      },
      emergency: {
        name: account.configuration.caller_id.emergency.name ?? '',
        phone_number_id: account.configuration.caller_id.emergency.phone_number_id,
        preserve_number: account.configuration.caller_id.emergency.unresolved,
      },
    },
  })
  const validationErrors = ref<FormErrors>({})

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate() {
    const result = validateForm<AccountSettingsInput>(accountSettingsSchema, form)
    validationErrors.value = result.errors
    return result
  }

  return { form, validate, validationErrors }
}
