<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ArrowRightStartOnRectangleIcon, UserPlusIcon } from '@heroicons/vue/24/outline'
import FormListbox, { type ListboxValue } from '@/shared/components/FormListbox.vue'
import type { DeviceHotdeskMemberships, ExtensionOption } from '../types/device'

const props = defineProps<{
  candidates: ExtensionOption[]
  memberships: DeviceHotdeskMemberships
  loading: boolean
  canManage: boolean
}>()
const emit = defineEmits<{
  signIn: [extensionId: string]
  signOut: [extensionId: string]
}>()
const selectedExtensionId = ref<string | null>(null)
const activeIds = computed(() => new Set(props.memberships.users.map((user) => user.id)))
const available = computed(() =>
  props.candidates.filter((candidate) => !activeIds.value.has(candidate.id)),
)

watch(available, (options) => {
  if (!options.some((option) => option.id === selectedExtensionId.value)) {
    selectedExtensionId.value = null
  }
})

function select(value: ListboxValue): void {
  selectedExtensionId.value = typeof value === 'string' ? value : null
}

function signIn(): void {
  if (selectedExtensionId.value) emit('signIn', selectedExtensionId.value)
}
</script>

<template>
  <article class="card-surface mt-5 overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-violet-50 text-violet-600">
        <UserPlusIcon class="size-5" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-700">Active hotdesk users</h2>
        <p class="text-[10px] text-slate-400">
          Sign projected extensions in or out of this endpoint without exposing Switch IDs.
        </p>
      </div>
    </header>

    <div class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_auto]">
      <FormListbox
        :model-value="selectedExtensionId"
        :options="
          available.map((extension) => ({
            value: extension.id,
            label: extension.display_name,
            description: extension.extension ? `Extension ${extension.extension}` : null,
          }))
        "
        :disabled="loading || !canManage || available.length === 0"
        placeholder="Select an extension to sign in"
        aria-label="Hotdesk extension"
        @update:model-value="select"
      />
      <button
        type="button"
        :disabled="loading || !canManage || !selectedExtensionId"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white hover:bg-brand-600 disabled:opacity-50"
        @click="signIn"
      >
        <UserPlusIcon class="size-4" /> Sign in
      </button>
    </div>

    <div
      v-if="memberships.users.length"
      class="divide-y divide-slate-100 border-t border-slate-100"
    >
      <div
        v-for="extension in memberships.users"
        :key="extension.id"
        class="flex items-center gap-3 px-5 py-3"
      >
        <div class="min-w-0 flex-1">
          <p class="truncate text-xs font-semibold text-slate-700">{{ extension.display_name }}</p>
          <p class="mt-0.5 font-mono text-[10px] text-slate-400">
            {{ extension.extension ? `Extension ${extension.extension}` : 'No extension number' }}
          </p>
        </div>
        <button
          v-if="canManage"
          type="button"
          data-testid="hotdesk-sign-out"
          :disabled="loading"
          class="inline-flex h-8 items-center gap-1.5 rounded-md border border-red-100 px-3 text-[11px] font-semibold text-danger hover:bg-red-50 disabled:opacity-50"
          @click="emit('signOut', extension.id)"
        >
          <ArrowRightStartOnRectangleIcon class="size-4" /> Sign out
        </button>
      </div>
    </div>
    <p v-else class="border-t border-slate-100 px-5 py-4 text-xs text-slate-500">
      No projected extension is currently signed in on this device.
    </p>
    <p
      v-if="memberships.unresolved_count"
      class="border-t border-amber-100 bg-amber-50 px-5 py-3 text-[11px] text-amber-800"
    >
      {{ memberships.unresolved_count }} additional Switch user(s) are active but not projected into
      GridPBX; their identifiers remain hidden.
    </p>
  </article>
</template>
