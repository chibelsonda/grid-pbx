<script setup lang="ts">
import { Squares2X2Icon } from '@heroicons/vue/24/outline'
</script>

<template>
  <main
    class="relative grid min-h-screen place-items-center overflow-hidden bg-[#f1f4f6] px-4 py-10"
  >
    <div
      class="absolute inset-y-0 left-0 w-[42%] -skew-x-6 bg-gradient-to-br from-[#3f6ad8] to-[#16aaff] opacity-95"
    />
    <section
      class="relative grid w-full max-w-4xl overflow-hidden rounded-lg bg-white shadow-[0_1rem_3rem_rgb(31_45_61/18%)] md:grid-cols-[0.9fr_1.1fr]"
    >
      <div
        class="hidden bg-gradient-to-br from-brand-700 to-brand-500 p-10 text-white md:flex md:flex-col md:justify-between"
      >
        <div class="flex items-center gap-3">
          <span class="grid size-11 place-items-center rounded-lg bg-white/15">
            <Squares2X2Icon class="size-6" />
          </span>
          <div>
            <p class="text-lg font-bold">GridPBX</p>
            <p class="text-xs text-white/60">Simpler phone administration</p>
          </div>
        </div>
        <div>
          <h1 class="text-3xl leading-tight font-semibold">
            Your Switch data,<br />made practical.
          </h1>
          <p class="mt-4 text-sm leading-6 text-white/65">
            A focused operations console backed by Laravel and a searchable Switch projection.
          </p>
        </div>
        <p class="text-[11px] text-white/45">Secure first-party session authentication</p>
      </div>

      <slot />
    </section>
  </main>
</template>
