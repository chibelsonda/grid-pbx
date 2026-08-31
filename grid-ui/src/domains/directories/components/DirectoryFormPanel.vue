<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { BookOpenIcon, TrashIcon } from '@heroicons/vue/24/outline'
import BasicAdvancedFormTabs from '@/shared/components/BasicAdvancedFormTabs.vue'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { useDirectoryForm } from '../composables/useDirectoryForm'
import type { Directory, DirectoryInput, DirectoryOptions } from '../types/directory'

const props = defineProps<{
  record: Directory | null
  options: DirectoryOptions
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: DirectoryInput]; remove: [] }>()
const confirmDelete = ref(false)
const selectedTab = ref(0)
const sortOptions: ListboxOptionValue[] = [
  { value: 'last_name', label: 'Last name' },
  { value: 'first_name', label: 'First name' },
]
const { form, validate, validationErrors } = useDirectoryForm(props.record)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))

function fieldError(field: string): string | null {
  const direct = errors.value[field]?.[0]
  if (direct) return direct

  return (
    Object.entries(errors.value).find(
      ([key, messages]) => key.startsWith(`${field}.`) && Boolean(messages[0]),
    )?.[1][0] ?? null
  )
}

watch(
  () => props.fieldErrors,
  (fieldErrors) => {
    if (Object.keys(fieldErrors).length === 0) return
    selectedTab.value = fieldErrors.name?.length || fieldErrors.member_ids?.length ? 0 : 1
  },
  { deep: true },
)

function setSortBy(value: ListboxValue): void {
  if (value === 'first_name' || value === 'last_name') form.sort_by = value
}

function submit(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) {
    emit('save', result.data)

    return
  }

  selectedTab.value =
    validationErrors.value.name?.length || validationErrors.value.member_ids?.length ? 0 : 1
}
</script>

<template>
  <CrudSlideOver
    :title="!canManage ? 'View directory' : record ? 'Edit directory' : 'Create directory'"
    eyebrow="GridPBX / Directories"
    description="Directory settings live on the Switch directory; member routes are coordinated through user mappings."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <BasicAdvancedFormTabs v-model="selectedTab">
        <template #basic>
          <article v-show="selectedTab === 0" class="card-surface overflow-hidden">
            <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
              <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
                ><BookOpenIcon class="size-5"
              /></span>
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Directory identity</h2>
                <p class="text-[10px] text-slate-400">Name shown to account operators.</p>
              </div>
            </header>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
              <FormInput
                v-model="form.name"
                label="Name"
                class="sm:col-span-2"
                required
                maxlength="128"
                :error="fieldError('name')"
              />
            </div>
          </article>
        </template>
        <template #advanced>
          <article class="card-surface overflow-hidden">
            <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
              <BookOpenIcon class="size-5 text-brand-500" />
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Dial-by-name options</h2>
                <p class="text-[10px] text-slate-400">Caller search and confirmation behavior.</p>
              </div>
            </header>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
              <label class="grid gap-2"
                ><span class="text-xs font-semibold text-slate-600">Sort names by</span
                ><FormListbox
                  :model-value="form.sort_by"
                  :options="sortOptions"
                  aria-label="Sort names by"
                  :invalid="Boolean(fieldError('sort_by'))"
                  @update:model-value="setSortBy"
                /><span v-if="fieldError('sort_by')" class="text-[10px] text-danger">{{
                  fieldError('sort_by')
                }}</span></label
              >
              <ToggleSwitch
                v-model="form.confirm_match"
                label="Confirm a single match"
                class="self-end rounded-md border border-slate-200 p-3"
                :class="validationControlClass(fieldError('confirm_match'))"
                :invalid="Boolean(fieldError('confirm_match'))"
              />
              <FormInput
                v-model.number="form.min_dtmf"
                label="Minimum digits"
                type="number"
                min="1"
                max="20"
                required
                :error="fieldError('min_dtmf')"
              />
              <FormInput
                v-model.number="form.max_dtmf"
                label="Maximum digits"
                description="0 = unlimited"
                type="number"
                min="0"
                max="20"
                required
                :error="fieldError('max_dtmf')"
              />
            </div>
          </article>
        </template>
      </BasicAdvancedFormTabs>

      <article v-show="selectedTab === 0" class="card-surface overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Directory members</h2>
          <p class="mt-1 text-[10px] text-slate-400">
            Only extensions with a projected callflow are selectable.
          </p>
        </header>
        <div class="grid gap-2 p-5">
          <FormCheckbox
            v-for="option in options.extensions"
            :key="option.id"
            :model-value="form.member_ids"
            :value="option.id"
            :label="option.label"
            :description="option.detail"
            :error="fieldError('member_ids')"
            @update:model-value="form.member_ids = $event as string[]"
          />
          <p v-if="!options.extensions.length" class="text-xs text-slate-400">
            No eligible extensions are projected.
          </p>
          <p v-if="fieldError('member_ids')" class="text-[10px] text-danger">
            {{ fieldError('member_ids') }}
          </p>
        </div>
      </article>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="confirmDelete = true"
        >
          <TrashIcon class="size-4" />Delete directory
        </button>
      </div>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          {{ canManage ? 'Cancel' : 'Close' }}</button
        ><button
          v-if="canManage"
          type="submit"
          :disabled="saving"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : 'Save directory' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete directory"
    description="Delete this directory after checking its routing dependencies?"
    confirm-label="Delete directory"
    :busy="saving"
    @close="confirmDelete = false"
    @confirm="emit('remove')"
  />
</template>
