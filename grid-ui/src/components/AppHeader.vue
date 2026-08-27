<script setup lang="ts">
import {
  Bars3Icon,
  BellIcon,
  ChevronDownIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

defineEmits<{
  toggleMobile: []
}>()
</script>

<template>
  <header class="fixed inset-x-0 top-0 z-30 h-[60px] bg-white shadow-shell lg:left-auto">
    <div class="flex h-full items-center gap-3 px-4 sm:px-6">
      <button
        type="button"
        class="grid size-9 place-items-center rounded-full text-brand-500 hover:bg-brand-50 lg:hidden"
        aria-label="Open navigation"
        @click="$emit('toggleMobile')"
      >
        <Bars3Icon class="size-5" />
      </button>

      <div class="relative hidden w-full max-w-sm sm:block">
        <MagnifyingGlassIcon
          class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
        />
        <input
          type="search"
          placeholder="Search extensions, devices, numbers..."
          class="h-9 w-full rounded-full border border-transparent bg-slate-100 pr-4 pl-10 text-xs text-slate-700 transition placeholder:text-slate-400 hover:bg-slate-50 focus:border-brand-100 focus:bg-white focus:outline-none"
        />
      </div>

      <div class="ml-auto flex items-center gap-1 sm:gap-3">
        <button
          type="button"
          class="hidden h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-brand-100 hover:text-brand-500 md:flex"
        >
          <span class="size-2 rounded-full bg-success" />
          GridPBX
          <ChevronDownIcon class="size-3.5 text-slate-400" />
        </button>

        <button
          type="button"
          class="relative grid size-9 place-items-center rounded-full text-slate-500 transition hover:bg-brand-50 hover:text-brand-500"
          aria-label="Notifications"
        >
          <BellIcon class="size-[19px]" />
          <span
            class="absolute top-1.5 right-1.5 size-2 rounded-full border-2 border-white bg-danger"
          />
        </button>

        <div class="mx-1 hidden h-8 w-px bg-slate-200 sm:block" />

        <button type="button" class="flex items-center gap-2 rounded-full p-1 hover:bg-slate-50">
          <span
            class="grid size-8 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-info text-xs font-bold text-white shadow-sm"
          >
            GA
          </span>
          <span class="hidden text-left sm:block">
            <span class="block text-xs font-semibold text-slate-700">Grid Admin</span>
            <span class="block text-[10px] text-slate-400">Administrator</span>
          </span>
          <ChevronDownIcon class="mr-1 hidden size-3.5 text-slate-400 sm:block" />
        </button>
      </div>
    </div>
  </header>
</template>
