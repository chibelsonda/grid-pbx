import { computed, type ComputedRef } from 'vue'
import type { ListboxOptionValue } from '@/shared/components/FormListbox.vue'
import { deviceTypes } from '@/domains/devices/deviceForm'
import type { DeviceType } from '@/domains/devices/types/device'
import type { ExtensionFormOptions } from '../types/extension'

function retainCurrent(
  options: ListboxOptionValue[],
  value: string | null,
  label = 'Current Switch value',
): ListboxOptionValue[] {
  if (!value || options.some((option) => option.value === value)) return options

  return [{ value, label: `${value} — ${label}` }, ...options]
}

export function useExtensionFormOptions(
  options: () => ExtensionFormOptions,
  current: () => { timezone: string | null; language: string | null; presenceId: string | null },
  extensionNumber: () => string,
): {
  timezoneOptions: ComputedRef<ListboxOptionValue[]>
  languageOptions: ComputedRef<ListboxOptionValue[]>
  presenceOptions: ComputedRef<ListboxOptionValue[]>
  starterDeviceTypes: ComputedRef<typeof deviceTypes>
  provisionableTypes: ComputedRef<Set<string>>
  sipCredentialTypes: ComputedRef<Set<string>>
} {
  const timezoneOptions = computed(() =>
    retainCurrent(
      [
        {
          value: null,
          label: options().account_defaults.timezone
            ? `Account default (${options().account_defaults.timezone})`
            : 'Account default',
        },
        ...options().timezones.map((timezone) => ({ value: timezone, label: timezone })),
      ],
      current().timezone,
    ),
  )
  const languageOptions = computed(() =>
    retainCurrent(
      [{ value: null, label: 'Account default' }, ...options().languages],
      current().language,
    ),
  )
  const presenceOptions = computed(() =>
    retainCurrent(
      [
        {
          value: null,
          label: extensionNumber()
            ? `Managed extension (${extensionNumber()})`
            : 'Managed extension number',
        },
        ...options().presence_ids,
      ],
      current().presenceId,
    ),
  )
  const starterDeviceTypes = computed(() => {
    const supported = new Set(options().starter_device.supported_types)

    return deviceTypes.filter((deviceType) => supported.has(deviceType.value))
  })

  return {
    timezoneOptions,
    languageOptions,
    presenceOptions,
    starterDeviceTypes,
    provisionableTypes: computed(() => new Set(options().starter_device.provisionable_types)),
    sipCredentialTypes: computed(() => new Set(options().starter_device.sip_credential_types)),
  }
}
