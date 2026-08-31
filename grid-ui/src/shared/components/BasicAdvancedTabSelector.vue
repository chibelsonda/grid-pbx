<script setup lang="ts">
const selectedIndex = defineModel<number>({ default: 0 })
</script>

<template>
  <div
    role="tablist"
    aria-label="Form sections"
    class="mb-5 grid grid-cols-2 rounded-md border border-slate-200 bg-slate-50 p-1"
  >
    <button
      v-for="(label, index) in ['Basic', 'Advanced']"
      :key="label"
      type="button"
      role="tab"
      :aria-selected="selectedIndex === index"
      class="rounded px-4 py-2 text-xs font-semibold transition"
      :class="
        selectedIndex === index
          ? 'bg-white text-brand-600 shadow-sm'
          : 'text-slate-500 hover:text-slate-700'
      "
      @click="selectedIndex = index"
    >
      {{ label }}
    </button>
  </div>
</template>
