<script setup lang="ts">
import {
  ArrowTrendingUpIcon,
  CheckCircleIcon,
  ClockIcon,
  DevicePhoneMobileIcon,
  HashtagIcon,
  PhoneIcon,
  PlusIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import StatCard from '@/components/StatCard.vue'

const stats = [
  {
    label: 'Extensions',
    value: '—',
    detail: 'Live counts in the next slice',
    icon: UserGroupIcon,
    tone: 'primary' as const,
  },
  {
    label: 'Registered devices',
    value: '—',
    detail: 'Registration status pending',
    icon: DevicePhoneMobileIcon,
    tone: 'success' as const,
  },
  {
    label: 'Phone numbers',
    value: '—',
    detail: 'Inventory connection pending',
    icon: HashtagIcon,
    tone: 'warning' as const,
  },
  {
    label: 'Calls today',
    value: '—',
    detail: 'CDR connection pending',
    icon: PhoneIcon,
    tone: 'info' as const,
  },
]

const recentActivity = [
  { label: 'Kazoo API environment prepared', time: 'Foundation', tone: 'success' },
  { label: 'Laravel API project created', time: 'Foundation', tone: 'primary' },
  { label: 'Vue and Tailwind design system created', time: 'Foundation', tone: 'info' },
]
</script>

<template>
  <div>
    <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
      <div class="mx-auto flex max-w-[1500px] flex-col gap-4 sm:flex-row sm:items-center">
        <div>
          <div class="mb-1 flex items-center gap-2 text-[11px] font-medium text-slate-400">
            <span>GridPBX</span>
            <span>/</span>
            <span class="text-slate-600">Dashboard</span>
          </div>
          <h1 class="text-xl font-semibold tracking-tight text-slate-800">Good morning, Admin</h1>
          <p class="mt-1 text-xs text-slate-500">
            Your simpler cloud phone system workspace is taking shape.
          </p>
        </div>
        <div class="flex items-center gap-2 sm:ml-auto">
          <span
            class="rounded-full border border-brand-100 bg-brand-50 px-3 py-1.5 text-[10px] font-bold tracking-wide text-brand-600 uppercase"
          >
            Foundation preview
          </span>
          <button
            type="button"
            class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-600"
          >
            <PlusIcon class="size-4" /> New extension
          </button>
        </div>
      </div>
    </section>

    <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Account statistics">
        <StatCard v-for="stat in stats" :key="stat.label" v-bind="stat" />
      </section>

      <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,0.8fr)]">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center border-b border-slate-100 px-5 py-4">
            <div>
              <p class="eyebrow">Call activity</p>
              <h2 class="mt-1 text-sm font-semibold text-slate-800">Weekly traffic</h2>
            </div>
            <div class="ml-auto flex items-center gap-2">
              <span class="size-2 rounded-full bg-brand-500" />
              <span class="text-[11px] text-slate-500">Incoming calls</span>
            </div>
          </header>
          <div class="p-5">
            <div class="relative h-64 overflow-hidden rounded-md bg-slate-50/70 p-4">
              <div class="absolute inset-4 flex flex-col justify-between" aria-hidden="true">
                <span
                  v-for="line in 5"
                  :key="line"
                  class="block border-t border-dashed border-slate-200"
                />
              </div>
              <div class="absolute inset-x-4 bottom-5 flex h-[78%] items-end justify-around gap-3">
                <div
                  v-for="(height, index) in [42, 61, 48, 75, 66, 88, 58]"
                  :key="index"
                  class="group flex h-full flex-1 items-end justify-center"
                >
                  <div
                    class="w-full max-w-9 rounded-t bg-gradient-to-t from-brand-600 to-brand-500 opacity-90 transition group-hover:opacity-100"
                    :style="{ height: `${height}%` }"
                  />
                </div>
              </div>
              <div
                class="absolute inset-x-4 bottom-1 flex justify-around text-[9px] font-semibold text-slate-400 uppercase"
              >
                <span v-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="day">
                  {{ day }}
                </span>
              </div>
            </div>
            <p class="mt-3 text-center text-[11px] text-slate-400">
              Preview visualization — live CDR metrics arrive in the secure vertical slice.
            </p>
          </div>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="border-b border-slate-100 px-5 py-4">
            <p class="eyebrow">Foundation progress</p>
            <h2 class="mt-1 text-sm font-semibold text-slate-800">Recent activity</h2>
          </header>
          <div class="p-5">
            <ol class="space-y-5">
              <li v-for="item in recentActivity" :key="item.label" class="flex gap-3">
                <span
                  class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full"
                  :class="{
                    'bg-emerald-50 text-success': item.tone === 'success',
                    'bg-brand-50 text-brand-500': item.tone === 'primary',
                    'bg-sky-50 text-info': item.tone === 'info',
                  }"
                >
                  <CheckCircleIcon class="size-4" />
                </span>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-slate-700">{{ item.label }}</p>
                  <p class="mt-1 flex items-center gap-1 text-[10px] text-slate-400">
                    <ClockIcon class="size-3" /> {{ item.time }}
                  </p>
                </div>
              </li>
            </ol>
            <div class="mt-6 rounded-md border border-brand-100 bg-brand-50 p-4">
              <div class="flex gap-3">
                <ArrowTrendingUpIcon class="size-5 shrink-0 text-brand-500" />
                <div>
                  <p class="text-xs font-semibold text-brand-700">Next milestone</p>
                  <p class="mt-1 text-[11px] leading-5 text-brand-600/80">
                    Secure login, account mapping, and live extension data through Laravel.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </article>
      </section>
    </div>
  </div>
</template>
