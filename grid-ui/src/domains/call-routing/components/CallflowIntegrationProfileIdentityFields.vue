<script setup lang="ts">
import FormInput from '@/shared/components/FormInput.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'

defineProps<{
  nameError?: string | null
  actionLabel: string
}>()

const name = defineModel<string>('name', { required: true })
const isActive = defineModel<boolean>('isActive', { required: true })
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="border-b border-slate-200 px-5 py-4">
      <h2 class="text-sm font-semibold text-slate-700">Profile identity</h2>
      <p class="mt-1 text-[10px] leading-4 text-slate-500">
        Callflow editors use the public profile name; private endpoint values stay server-side.
      </p>
    </header>
    <div class="grid gap-4 p-5">
      <FormInput
        v-model="name"
        label="Profile name"
        required
        maxlength="100"
        :placeholder="`${actionLabel} endpoint`"
        :error="nameError"
      />
      <ToggleSwitch
        v-model="isActive"
        label="Available to callflow editors"
        :description="`Disable this profile to prevent new ${actionLabel} mutations while preserving existing Switch data.`"
      />
    </div>
  </article>
</template>
