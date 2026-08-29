<script setup lang="ts">
import { CloudArrowUpIcon, LinkSlashIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'
import type { DeviceProvisioningEnrollment } from '../types/device'

const props = defineProps<{
  enrollment: DeviceProvisioningEnrollment
  loading: boolean
  busy: boolean
  canManage: boolean
}>()
const emit = defineEmits<{ enroll: []; detach: [] }>()

function formatDate(value: string | null): string {
  if (!value) return 'Never'

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}
</script>

<template>
  <article class="card-surface mt-5 overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-cyan-50 text-cyan-600">
        <ShieldCheckIcon class="size-5" />
      </span>
      <div class="min-w-0 flex-1">
        <h2 class="text-sm font-semibold text-slate-700">Manufacturer provisioning enrollment</h2>
        <p class="text-[10px] text-slate-500">
          Enrollment state only—provider credentials and access tokens are never stored here.
        </p>
      </div>
      <span
        class="rounded-full px-2.5 py-1 text-[10px] font-bold"
        :class="
          enrollment.status === 'enrolled'
            ? 'bg-emerald-50 text-emerald-700'
            : 'bg-slate-100 text-slate-600'
        "
      >
        {{ enrollment.status === 'enrolled' ? 'Enrolled' : 'Not enrolled' }}
      </span>
    </header>

    <div v-if="loading" class="p-5 text-xs text-slate-500">Loading enrollment state…</div>
    <div v-else class="grid gap-4 p-5">
      <dl class="grid gap-4 text-xs sm:grid-cols-3">
        <div>
          <dt class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Provider</dt>
          <dd class="mt-1.5 font-medium text-slate-700">{{ enrollment.provider ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
            Enrolled at
          </dt>
          <dd class="mt-1.5 font-medium text-slate-700">
            {{ formatDate(enrollment.enrolled_at) }}
          </dd>
        </div>
        <div>
          <dt class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
            Last detached
          </dt>
          <dd class="mt-1.5 font-medium text-slate-700">
            {{ formatDate(enrollment.detached_at) }}
          </dd>
        </div>
      </dl>

      <p
        v-if="enrollment.reason"
        class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-[11px] leading-5 text-amber-800"
      >
        {{ enrollment.reason }}
      </p>

      <div v-if="canManage" class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-4">
        <button
          v-if="enrollment.status === 'not_enrolled'"
          type="button"
          :disabled="busy || !enrollment.can_enroll"
          :title="enrollment.can_enroll ? 'Enroll this device' : (enrollment.reason ?? undefined)"
          class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-45"
          @click="emit('enroll')"
        >
          <CloudArrowUpIcon class="size-4" /> Enroll device
        </button>
        <button
          v-else
          type="button"
          :disabled="busy || !enrollment.can_detach"
          :title="enrollment.can_detach ? 'Detach this device' : (enrollment.reason ?? undefined)"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-red-200 px-4 text-xs font-semibold text-danger hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-45"
          @click="emit('detach')"
        >
          <LinkSlashIcon class="size-4" /> Detach enrollment
        </button>
      </div>
    </div>
  </article>
</template>
