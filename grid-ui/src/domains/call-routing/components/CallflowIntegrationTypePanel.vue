<script setup lang="ts">
import {
  ArrowsRightLeftIcon,
  BoltIcon,
  GlobeAltIcon,
  KeyIcon,
  SignalIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'

type AvailableIntegrationType = 'pivot' | 'webhook' | 'disa' | 'global_carrier' | 'account_carrier'

const emit = defineEmits<{
  close: []
  select: [type: AvailableIntegrationType]
}>()

const integrationTypes = [
  {
    type: 'disa' as const,
    label: 'DISA access policy',
    description: 'Authorize native DISA with a write-only PIN and fixed call-restriction controls.',
    icon: KeyIcon,
    available: true,
  },
  {
    type: 'pivot' as const,
    label: 'Pivot',
    description: 'Request call instructions from an administrator-approved HTTPS application.',
    icon: ArrowsRightLeftIcon,
    available: true,
  },
  {
    type: 'webhook' as const,
    label: 'Webhook',
    description: 'Send a bounded event request to an administrator-approved HTTPS endpoint.',
    icon: BoltIcon,
    available: true,
  },
  {
    type: 'global_carrier' as const,
    label: 'Global carrier',
    description: 'Use the Switch deployment carrier pool through a controlled routing policy.',
    icon: GlobeAltIcon,
    available: true,
  },
  {
    type: 'account_carrier' as const,
    label: 'Account carrier',
    description: 'Use account or reseller resources without exposing raw Switch account IDs.',
    icon: SignalIcon,
    available: true,
  },
] as const
</script>

<template>
  <CrudSlideOver
    title="Add Callflow integration"
    eyebrow="GridPBX / Settings / Callflow integrations"
    description="Configure private server-side profiles that authorize account-scoped Callflow actions."
    width="medium"
    @close="emit('close')"
  >
    <div class="grid gap-4 sm:grid-cols-2">
      <button
        v-for="integration in integrationTypes"
        :key="integration.type"
        type="button"
        class="group min-h-36 rounded-lg border bg-white p-5 text-left shadow-sm transition"
        :class="
          integration.available
            ? 'border-slate-200 hover:border-brand-300 hover:bg-brand-50/40 hover:shadow-md'
            : 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-65'
        "
        :disabled="!integration.available"
        :aria-label="integration.available ? `Add ${integration.label} profile` : undefined"
        @click="integration.available && emit('select', integration.type)"
      >
        <span class="flex items-start gap-3">
          <component
            :is="integration.icon"
            class="mt-0.5 size-6 shrink-0"
            :class="integration.available ? 'text-brand-500' : 'text-slate-400'"
          />
          <span class="min-w-0">
            <span class="flex flex-wrap items-center gap-2">
              <span class="text-sm font-semibold text-slate-800">{{ integration.label }}</span>
            </span>
            <span class="mt-2 block text-xs leading-5 text-slate-500">
              {{ integration.description }}
            </span>
          </span>
        </span>
      </button>
    </div>

    <p
      class="mt-4 rounded-md border border-slate-200 bg-white px-4 py-3 text-[10px] leading-4 text-slate-500"
    >
      An active, valid profile enables only its matching action. DISA additionally remains locked
      until the deployment guard reports every required operational safety control. Its credentials
      remain write-only and browser settings cannot weaken caller-ID or account restriction policy.
      Carrier profiles authorize routing scope only; they do not expose or override Switch carrier
      identifiers.
    </p>
  </CrudSlideOver>
</template>
