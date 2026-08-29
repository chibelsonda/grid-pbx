<script setup lang="ts">
import { computed } from 'vue'
import {
  BuildingOfficeIcon,
  CheckBadgeIcon,
  LinkIcon,
  MapPinIcon,
  PhoneIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { PhoneNumber, PhoneNumberOperationCapability } from '../types/phoneNumber'

const props = defineProps<{ record: PhoneNumber | null; loading: boolean; error: string | null }>()
defineEmits<{ close: [] }>()

const title = computed(() => props.record?.number ?? 'Phone number details')

const e911Address = computed(() => {
  const e911 = props.record?.e911
  if (!e911) return null

  return [
    [e911.street_address, e911.extended_address].filter(Boolean).join(', '),
    [e911.locality, e911.region, e911.postal_code].filter(Boolean).join(' '),
  ]
    .filter(Boolean)
    .join('\n')
})

const operationalCapabilities = computed<
  Array<{ key: string; label: string; capability: PhoneNumberOperationCapability }>
>(() => {
  if (!props.record) return []

  return [
    { key: 'cnam', label: 'Caller name (CNAM)', capability: props.record.capabilities.cnam },
    { key: 'e911', label: 'Emergency address (E911)', capability: props.record.capabilities.e911 },
    { key: 'porting', label: 'Number porting', capability: props.record.capabilities.porting },
    {
      key: 'purchasing',
      label: 'Number purchasing',
      capability: props.record.capabilities.purchasing,
    },
    { key: 'release', label: 'Number release', capability: props.record.capabilities.release },
  ]
})

function humanize(value: string | null): string {
  return value
    ? value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
    : 'Unknown'
}
</script>

<template>
  <CrudSlideOver
    :title="title"
    eyebrow="GridPBX / Phone Numbers"
    description="Projected carrier state, features, and current callflow assignment."
    width="medium"
    @close="$emit('close')"
  >
    <div v-if="loading" class="card-surface p-10 text-center text-xs text-slate-400">
      Loading phone number details…
    </div>
    <div
      v-else-if="error"
      class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ error }}
    </div>
    <div v-else-if="record" class="grid gap-5">
      <article class="card-surface p-5">
        <div class="flex items-center gap-3">
          <span class="grid size-11 place-items-center rounded-md bg-brand-50 text-brand-600"
            ><PhoneIcon class="size-5"
          /></span>
          <div>
            <p class="font-mono text-lg font-semibold text-slate-800">{{ record.number }}</p>
            <p class="mt-1 text-[11px] text-slate-400">
              Last synchronized
              {{
                record.last_synced_at ? new Date(record.last_synced_at).toLocaleString() : 'never'
              }}
            </p>
          </div>
          <span
            class="ml-auto rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold text-emerald-700"
            >{{ humanize(record.state) }}</span
          >
        </div>
      </article>

      <div class="grid gap-4 sm:grid-cols-2">
        <article class="card-surface p-5">
          <BuildingOfficeIcon class="size-5 text-violet-500" />
          <p class="mt-3 text-[10px] font-bold tracking-wide text-slate-400 uppercase">Carrier</p>
          <p class="mt-1 text-sm font-semibold text-slate-700">
            {{ record.carrier_name ?? 'Not reported' }}
          </p>
        </article>
        <article class="card-surface p-5">
          <LinkIcon class="size-5 text-blue-500" />
          <p class="mt-3 text-[10px] font-bold tracking-wide text-slate-400 uppercase">Used by</p>
          <p class="mt-1 text-sm font-semibold text-slate-700">{{ humanize(record.used_by) }}</p>
        </article>
      </div>

      <article class="card-surface p-5">
        <h2 class="text-sm font-semibold text-slate-700">Incoming route</h2>
        <div
          v-if="record.assigned_callflow"
          class="mt-4 rounded-md border border-blue-100 bg-blue-50 p-4"
        >
          <p class="text-sm font-semibold text-blue-800">
            {{ record.assigned_callflow.name ?? 'Unnamed callflow' }}
          </p>
          <p class="mt-1 text-[11px] text-blue-600">Callflow assignment projected from Switch</p>
        </div>
        <p v-else class="mt-4 text-xs text-slate-500">
          No projected callflow currently contains this number.
        </p>
      </article>

      <article class="card-surface p-5">
        <h2 class="text-sm font-semibold text-slate-700">Number features</h2>
        <div class="mt-4 flex flex-wrap gap-2">
          <span
            v-for="feature in record.features"
            :key="feature"
            class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold text-slate-600"
            >{{ humanize(feature) }}</span
          >
          <span v-if="record.features.length === 0" class="text-xs text-slate-400"
            >No features reported.</span
          >
        </div>
      </article>

      <div class="grid gap-4 sm:grid-cols-2">
        <article class="card-surface p-5">
          <CheckBadgeIcon class="size-5 text-emerald-500" />
          <p class="mt-3 text-[10px] font-bold tracking-wide text-slate-400 uppercase">
            Caller name
          </p>
          <p class="mt-1 text-sm font-semibold text-slate-700">
            {{ record.cnam.display_name ?? 'Not configured' }}
          </p>
          <p class="mt-1 text-[11px] text-slate-400">
            Inbound lookup {{ record.cnam.inbound_lookup ? 'enabled' : 'disabled' }}
          </p>
        </article>
        <article class="card-surface p-5">
          <MapPinIcon class="size-5 text-amber-500" />
          <p class="mt-3 text-[10px] font-bold tracking-wide text-slate-400 uppercase">
            E911 status
          </p>
          <p class="mt-1 text-sm font-semibold text-slate-700">
            {{ humanize(record.e911.status) }}
          </p>
          <p
            v-if="e911Address"
            class="mt-2 whitespace-pre-line text-[11px] leading-5 text-slate-500"
          >
            {{ e911Address }}
          </p>
          <p v-if="record.e911.caller_name" class="mt-2 text-[11px] text-slate-500">
            Emergency caller name: {{ record.e911.caller_name }}
          </p>
          <p
            v-if="record.e911.notification_contact_emails.length"
            class="mt-2 break-words text-[11px] text-slate-500"
          >
            Notifications: {{ record.e911.notification_contact_emails.join(', ') }}
          </p>
        </article>
      </div>

      <article v-if="record.porting.active" class="card-surface p-5">
        <h2 class="text-sm font-semibold text-slate-700">Porting status</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-[10px] font-bold tracking-wide text-slate-500 uppercase">Provider</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-700">
              {{ record.porting.service_provider ?? 'Not reported' }}
            </dd>
          </div>
          <div>
            <dt class="text-[10px] font-bold tracking-wide text-slate-500 uppercase">
              Requested date
            </dt>
            <dd class="mt-1 text-sm font-semibold text-slate-700">
              {{ record.porting.requested_port_date ?? 'Not reported' }}
            </dd>
          </div>
        </dl>
      </article>

      <article class="card-surface p-5">
        <h2 class="text-sm font-semibold text-slate-700">Operational capabilities</h2>
        <div class="mt-4 grid gap-3">
          <div
            v-for="item in operationalCapabilities"
            :key="item.key"
            class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3"
          >
            <div class="flex items-center justify-between gap-3">
              <p class="text-xs font-semibold text-slate-700">{{ item.label }}</p>
              <span
                class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                :class="
                  item.capability.writable
                    ? 'bg-emerald-100 text-emerald-700'
                    : item.capability.available
                      ? 'bg-amber-100 text-amber-700'
                      : 'bg-slate-200 text-slate-600'
                "
              >
                {{
                  item.capability.writable
                    ? 'Available'
                    : item.capability.available
                      ? 'Policy gated'
                      : 'Unavailable'
                }}
              </span>
            </div>
            <p class="mt-1 text-[11px] leading-5 text-slate-500">
              {{ item.capability.reason }}
            </p>
          </div>
        </div>
      </article>

      <aside
        class="flex gap-3 rounded-md border border-amber-100 bg-amber-50 p-4 text-xs leading-5 text-amber-800"
      >
        <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
        <p>
          Carrier acquisition, release, caller-name, and E911 changes remain unavailable until the
          server reports both runtime support and an approved billing/compliance confirmation
          policy. GridPBX does not infer mutation permission from a field merely appearing in the
          schema.
        </p>
      </aside>
    </div>
  </CrudSlideOver>
</template>
