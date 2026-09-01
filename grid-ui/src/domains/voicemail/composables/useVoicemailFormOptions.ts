import { computed, type ComputedRef } from 'vue'
import type { ListboxOptionValue } from '@/shared/components/FormListbox.vue'
import type { VoicemailFormOptions } from '../types/voicemail'

function retainCurrent(options: ListboxOptionValue[], value: string | null): ListboxOptionValue[] {
  if (!value || options.some((option) => option.value === value)) return options

  return [{ value, label: `${value} — Current projected value` }, ...options]
}

export function useVoicemailFormOptions(
  options: () => VoicemailFormOptions,
  timezone: () => string | null,
  extensionId: () => string | null,
): {
  timezoneOptions: ComputedRef<ListboxOptionValue[]>
  extensionOptions: ComputedRef<ListboxOptionValue[]>
} {
  return {
    timezoneOptions: computed(() =>
      retainCurrent(
        [
          {
            value: null,
            label: options().account_defaults.timezone
              ? `Account default (${options().account_defaults.timezone})`
              : 'Account default',
          },
          ...options().timezones.map((value) => ({ value, label: value })),
        ],
        timezone(),
      ),
    ),
    extensionOptions: computed(() =>
      retainCurrent(
        [
          { value: null, label: 'Unassigned' },
          ...options().extensions.map((extension) => ({
            value: extension.id,
            label: `${extension.display_name}${extension.extension ? ` · ${extension.extension}` : ''}`,
          })),
        ],
        extensionId(),
      ),
    ),
  }
}
