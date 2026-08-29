import { computed, type ComputedRef } from 'vue'
import type { ListboxOptionValue } from '@/shared/components/FormListbox.vue'
import type { FaxBoxOptions } from '../types/fax'

function retainCurrent(options: ListboxOptionValue[], value: string | null): ListboxOptionValue[] {
  if (!value || options.some((option) => option.value === value)) return options

  return [{ value, label: `${value} — Current projected value` }, ...options]
}

export function useFaxBoxFormOptions(
  options: () => FaxBoxOptions,
  ownerId: () => string | null,
  callerId: () => string | null,
  timezone: () => string | null,
): {
  ownerOptions: ComputedRef<ListboxOptionValue[]>
  callerIdOptions: ComputedRef<ListboxOptionValue[]>
  timezoneOptions: ComputedRef<ListboxOptionValue[]>
} {
  return {
    ownerOptions: computed(() =>
      retainCurrent(
        [
          { value: null, label: 'No owner' },
          ...options().owners.map((owner) => ({
            value: owner.id,
            label: owner.label,
            description: owner.detail,
          })),
        ],
        ownerId(),
      ),
    ),
    callerIdOptions: computed(() =>
      retainCurrent(
        [
          { value: null, label: 'No caller ID number' },
          ...options().caller_id_numbers.map((number) => ({ value: number, label: number })),
        ],
        callerId(),
      ),
    ),
    timezoneOptions: computed(() =>
      retainCurrent(
        [
          {
            value: null,
            label: options().account_defaults.timezone
              ? `Account default (${options().account_defaults.timezone})`
              : 'Account default',
          },
          ...options().timezones.map((timezoneOption) => ({
            value: timezoneOption,
            label: timezoneOption,
          })),
        ],
        timezone(),
      ),
    ),
  }
}
