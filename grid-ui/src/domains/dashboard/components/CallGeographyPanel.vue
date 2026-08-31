<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  GlobeAmericasIcon,
  InformationCircleIcon,
  LockClosedIcon,
  MapPinIcon,
} from '@heroicons/vue/24/outline'
import type { CallGeography, CallGeographyLocation } from '../schemas/callGeographySchema'

const props = defineProps<{
  geography: CallGeography | null
  loading: boolean
  error: string | null
  rangeLabel: string
}>()

const selectedKey = ref<string | null>(null)
const locations = computed(() => props.geography?.locations ?? [])
const selectedLocation = computed(
  () =>
    locations.value.find((location) => location.key === selectedKey.value) ??
    locations.value[0] ??
    null,
)
const markerMaximum = computed(() =>
  Math.max(...locations.value.map((location) => location.total), 1),
)

watch(
  () => props.geography,
  () => {
    selectedKey.value = null
  },
)

function point(location: CallGeographyLocation): { x: number; y: number; radius: number } {
  return {
    x: ((location.longitude + 180) / 360) * 1000,
    y: ((90 - location.latitude) / 180) * 500,
    radius: 7 + Math.sqrt(location.total / markerMaximum.value) * 11,
  }
}

function select(location: CallGeographyLocation): void {
  selectedKey.value = location.key
}
</script>

<template>
  <section class="card-surface mt-6 overflow-hidden" aria-labelledby="call-geography-title">
    <header
      class="flex flex-col gap-3 border-b border-slate-200/80 px-5 py-4 sm:flex-row sm:items-center"
    >
      <div class="flex items-center gap-3">
        <span class="grid size-9 place-items-center rounded-md bg-cyan-50 text-cyan-600">
          <GlobeAmericasIcon class="size-4.5" />
        </span>
        <div>
          <h2 id="call-geography-title" class="text-sm font-semibold text-slate-800">
            Call geography
          </h2>
          <p class="text-[11px] text-slate-500">
            Estimated numbering-plan distribution · {{ rangeLabel }}
          </p>
        </div>
      </div>
      <span
        v-if="geography?.status === 'ready'"
        class="rounded-full border border-cyan-100 bg-cyan-50 px-3 py-1 text-[10px] font-semibold text-cyan-700 sm:ml-auto"
      >
        {{ geography.coverage.percentage }}% location coverage
      </span>
    </header>

    <div v-if="error" class="border-b border-red-100 bg-red-50 px-5 py-3 text-xs text-red-700">
      {{ error }}
    </div>

    <div v-if="loading && !geography" class="grid min-h-72 place-items-center p-8 text-center">
      <div>
        <span class="mx-auto block size-8 animate-pulse rounded-full bg-cyan-100" />
        <p class="mt-3 text-xs text-slate-500">Loading approved geography aggregates…</p>
      </div>
    </div>

    <div
      v-else-if="geography?.status === 'unavailable'"
      class="grid min-h-64 place-items-center p-8 text-center"
    >
      <div class="max-w-lg">
        <span
          class="mx-auto grid size-11 place-items-center rounded-full bg-slate-100 text-slate-500"
        >
          <LockClosedIcon class="size-5" />
        </span>
        <h3 class="mt-4 text-sm font-semibold text-slate-700">Geography analytics not enabled</h3>
        <p class="mt-2 text-xs leading-5 text-slate-500">
          {{ geography.capability.reason }} GridPBX will not geocode phone numbers or contact a map
          provider from this page.
        </p>
      </div>
    </div>

    <div
      v-else-if="geography?.status === 'empty'"
      class="grid min-h-64 place-items-center p-8 text-center"
    >
      <div class="max-w-lg">
        <MapPinIcon class="mx-auto size-9 text-slate-400" />
        <h3 class="mt-3 text-sm font-semibold text-slate-700">No estimated locations</h3>
        <p class="mt-2 text-xs leading-5 text-slate-500">{{ geography.capability.reason }}</p>
      </div>
    </div>

    <div v-else-if="geography?.status === 'ready'" class="grid xl:grid-cols-[minmax(0,2fr)_360px]">
      <div class="relative min-h-[360px] overflow-hidden bg-slate-950 p-3 sm:p-5">
        <svg
          class="h-full min-h-[330px] w-full"
          viewBox="0 0 1000 500"
          role="img"
          aria-label="Estimated call geography map"
        >
          <defs>
            <pattern id="geography-grid" width="25" height="25" patternUnits="userSpaceOnUse">
              <circle cx="1" cy="1" r="1" fill="#334155" opacity="0.5" />
            </pattern>
            <filter id="marker-shadow" x="-50%" y="-50%" width="200%" height="200%">
              <feDropShadow
                dx="0"
                dy="2"
                stdDeviation="3"
                flood-color="#020617"
                flood-opacity="0.55"
              />
            </filter>
          </defs>
          <rect width="1000" height="500" rx="16" fill="#0f172a" />
          <rect width="1000" height="500" rx="16" fill="url(#geography-grid)" />
          <g fill="#1e293b" stroke="#334155" stroke-width="2" opacity="0.95">
            <path
              d="M70 125 120 77 210 68 282 106 257 150 219 165 198 217 151 233 112 197 82 173Z"
            />
            <path d="m226 244 52 23 31 58-20 83-35 70-31-38 9-76-24-62Z" />
            <path d="m430 94 79-29 93 15 51 39-40 35-71-6-30 35-53-10-45-37Z" />
            <path d="m469 178 74-11 64 42-10 93-49 102-47-36-19-91-43-43Z" />
            <path d="m610 102 103-27 117 24 109 64-54 48-93-13-45 43-81-30-59-50Z" />
            <path d="m786 334 72-23 61 33-11 57-76 13-58-39Z" />
            <path d="m906 220 22-10 19 25-20 18-23-12Z" />
          </g>
          <g v-for="location in locations" :key="location.key">
            <g
              role="button"
              tabindex="0"
              :aria-label="`${location.label}: ${location.total} estimated calls`"
              class="cursor-pointer outline-none"
              @click="select(location)"
              @keydown.enter="select(location)"
              @keydown.space.prevent="select(location)"
            >
              <circle
                :cx="point(location).x"
                :cy="point(location).y"
                :r="point(location).radius + (selectedLocation?.key === location.key ? 6 : 3)"
                fill="#22d3ee"
                :opacity="selectedLocation?.key === location.key ? 0.28 : 0.14"
              />
              <circle
                :cx="point(location).x"
                :cy="point(location).y"
                :r="point(location).radius"
                fill="#0ea5e9"
                stroke="#a5f3fc"
                :stroke-width="selectedLocation?.key === location.key ? 4 : 2"
                filter="url(#marker-shadow)"
              />
              <text
                :x="point(location).x"
                :y="point(location).y + 4"
                text-anchor="middle"
                fill="white"
                font-size="12"
                font-weight="700"
              >
                {{ location.total }}
              </text>
              <title>{{ location.label }}: {{ location.total }} estimated calls</title>
            </g>
          </g>
        </svg>

        <div
          v-if="selectedLocation"
          class="absolute bottom-5 left-5 rounded-md border border-white/10 bg-slate-900/90 px-4 py-3 text-white shadow-xl backdrop-blur"
          aria-live="polite"
        >
          <p class="text-xs font-semibold">{{ selectedLocation.label }}</p>
          <p class="mt-1 text-[10px] text-slate-300">
            {{ selectedLocation.inbound }} inbound · {{ selectedLocation.outbound }} outbound
          </p>
        </div>
      </div>

      <div class="border-t border-slate-200/80 xl:border-t-0 xl:border-l">
        <div class="border-b border-slate-200/80 px-4 py-3">
          <h3 class="text-xs font-semibold text-slate-700">Location summary</h3>
          <p class="mt-0.5 text-[10px] text-slate-500">Accessible non-map view of the same data</p>
        </div>
        <div class="max-h-[360px] overflow-y-auto">
          <button
            v-for="location in locations"
            :key="`${location.key}-row`"
            type="button"
            class="flex w-full items-center gap-3 border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50"
            :class="selectedLocation?.key === location.key && 'bg-cyan-50/70'"
            @click="select(location)"
          >
            <span class="size-2.5 shrink-0 rounded-full bg-cyan-500" />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-xs font-semibold text-slate-700">{{
                location.label
              }}</span>
              <span class="mt-0.5 block text-[10px] text-slate-500">
                {{ location.inbound }} inbound · {{ location.outbound }} outbound
              </span>
            </span>
            <span class="text-sm font-semibold text-slate-700">{{ location.total }}</span>
          </button>
        </div>
      </div>
    </div>

    <footer
      v-if="geography"
      class="flex flex-col gap-2 border-t border-slate-200/80 bg-slate-50/60 px-5 py-3 sm:flex-row sm:items-center"
    >
      <p class="inline-flex items-start gap-2 text-[10px] leading-4 text-slate-500">
        <InformationCircleIcon class="mt-0.5 size-3.5 shrink-0" />
        {{ geography.disclosure }}
      </p>
      <p v-if="geography.capability.available" class="text-[10px] text-slate-400 sm:ml-auto">
        {{ geography.coverage.located_calls }} of {{ geography.coverage.total_calls }} calls located
      </p>
    </footer>
  </section>
</template>
