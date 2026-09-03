<script setup lang="ts">
import { computed, ref } from 'vue'
import { ArrowDownIcon, ArrowUpIcon, TrashIcon, UserGroupIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { useGroupForm } from '../composables/useGroupForm'
import type { Group, GroupInput, GroupMemberType, GroupOptions } from '../types/group'

const props = defineProps<{
  record: Group | null
  options: GroupOptions
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: GroupInput]; remove: [] }>()
const selectedType = ref<GroupMemberType>('user')
const selectedId = ref('')
const { form, validate, validationErrors } = useGroupForm(props.record)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const memberTypeOptions: ListboxOptionValue[] = [
  { value: 'user', label: 'User' },
  { value: 'device', label: 'Device' },
  { value: 'group', label: 'Group' },
]
const musicOnHoldOptions = computed<ListboxOptionValue[]>(() => [
  { value: null, label: 'Account default' },
  ...props.options.media.map(({ id, label: optionLabel, detail }) => ({
    value: id,
    label: optionLabel,
    description: detail,
  })),
])
const choices = computed(() =>
  ({
    user: props.options.users,
    device: props.options.devices,
    group: props.options.groups.filter(({ id }) => id !== props.record?.id),
  })[selectedType.value].filter(
    ({ id }) =>
      !form.members.some((member) => member.type === selectedType.value && member.id === id),
  ),
)
const targetOptions = computed<ListboxOptionValue[]>(() => [
  { value: '', label: 'Select target…' },
  ...choices.value.map(({ id, label: optionLabel, detail }) => ({
    value: id,
    label: optionLabel,
    description: detail,
  })),
])

function fieldError(field: string): string | null {
  const direct = errors.value[field]?.[0]
  if (direct) return direct

  return (
    Object.entries(errors.value).find(
      ([key, messages]) => key.startsWith(`${field}.`) && Boolean(messages[0]),
    )?.[1][0] ?? null
  )
}

function setMusicOnHold(value: ListboxValue): void {
  if (value === null || typeof value === 'string') form.music_on_hold_media_id = value
}

function setSelectedType(value: ListboxValue): void {
  if (value === 'user' || value === 'device' || value === 'group') {
    selectedType.value = value
    selectedId.value = ''
  }
}

function setSelectedId(value: ListboxValue): void {
  selectedId.value = typeof value === 'string' ? value : ''
}
function label(type: GroupMemberType, id: string): string {
  const list =
    type === 'user'
      ? props.options.users
      : type === 'device'
        ? props.options.devices
        : props.options.groups
  return list.find((item) => item.id === id)?.label ?? 'Unavailable target'
}
function add(): void {
  if (!selectedId.value) return
  form.members.push({
    type: selectedType.value,
    id: selectedId.value,
    weight: form.members.length + 1,
  })
  selectedId.value = ''
}
function removeAt(index: number): void {
  form.members.splice(index, 1)
  normalize()
}
function move(index: number, direction: -1 | 1): void {
  const target = index + direction
  if (target < 0 || target >= form.members.length) return
  const [member] = form.members.splice(index, 1)
  if (member) form.members.splice(target, 0, member)
  normalize()
}
function normalize(): void {
  form.members.forEach((member, index) => {
    member.weight = index + 1
  })
}

function submit(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) {
    emit('save', result.data)
  }
}
</script>

<template>
  <CrudSlideOver
    :title="!canManage ? 'View group' : record ? 'Edit group' : 'Create group'"
    eyebrow="GridPBX / Groups"
    description="Reusable membership for call routing, ring groups, pickup, and nested group destinations."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to save the group"
      />
      <fieldset :disabled="!canManage || saving" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
              ><UserGroupIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Group settings</h2>
              <p class="text-[10px] text-heading-description">
                The order becomes the Switch endpoint weight.
              </p>
            </div>
          </header>
          <div class="grid gap-4 p-5">
            <FormInput
              v-model="form.name"
              label="Name"
              required
              maxlength="128"
              :error="fieldError('name')"
            /><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Music on hold</span
              ><FormListbox
                :model-value="form.music_on_hold_media_id"
                :options="musicOnHoldOptions"
                aria-label="Music on hold"
                :invalid="Boolean(fieldError('music_on_hold_media_id'))"
                @update:model-value="setMusicOnHold"
              /><span v-if="fieldError('music_on_hold_media_id')" class="text-[10px] text-danger">{{
                fieldError('music_on_hold_media_id')
              }}</span></label
            >
          </div>
        </article>
        <article class="card-surface overflow-hidden">
          <header class="border-b border-slate-100 px-5 py-4">
            <h2 id="group-members-heading" class="text-sm font-semibold text-slate-700">Members</h2>
            <p class="mt-1 text-[10px] text-heading-description">
              Users, devices, and nested groups are resolved server-side.
            </p>
          </header>
          <div class="grid gap-4 p-5">
            <div class="grid gap-2 sm:grid-cols-[120px_1fr_auto]">
              <FormListbox
                :model-value="selectedType"
                :options="memberTypeOptions"
                aria-label="Member type"
                @update:model-value="setSelectedType"
              /><FormListbox
                :model-value="selectedId"
                :options="targetOptions"
                aria-label="Member target"
                @update:model-value="setSelectedId"
              /><button
                type="button"
                :disabled="!selectedId"
                class="h-10 rounded-md bg-slate-700 px-4 text-xs font-semibold text-white disabled:opacity-40"
                @click="add"
              >
                Add
              </button>
            </div>
            <div
              class="divide-y divide-slate-100 rounded-md border border-slate-200"
              :class="validationControlClass(fieldError('members'))"
              :aria-invalid="Boolean(fieldError('members'))"
              role="group"
              aria-labelledby="group-members-heading"
            >
              <div
                v-for="(member, index) in form.members"
                :key="`${member.type}-${member.id}`"
                class="flex items-center gap-3 px-4 py-3"
                :class="validationControlClass(fieldError(`members.${index}`))"
              >
                <span
                  class="grid size-7 place-items-center rounded bg-slate-100 text-[10px] font-bold text-slate-500"
                  >{{ index + 1 }}</span
                >
                <div class="min-w-0 flex-1">
                  <p class="truncate text-xs font-semibold text-slate-700">
                    {{ label(member.type, member.id) }}
                  </p>
                  <p class="text-[10px] font-semibold text-slate-400 uppercase">
                    {{ member.type }}
                  </p>
                </div>
                <button
                  type="button"
                  :disabled="index === 0"
                  :aria-label="`Move member ${index + 1} up`"
                  class="text-slate-400 disabled:opacity-20"
                  @click="move(index, -1)"
                >
                  <ArrowUpIcon class="size-4" /></button
                ><button
                  type="button"
                  :disabled="index === form.members.length - 1"
                  :aria-label="`Move member ${index + 1} down`"
                  class="text-slate-400 disabled:opacity-20"
                  @click="move(index, 1)"
                >
                  <ArrowDownIcon class="size-4" /></button
                ><button
                  type="button"
                  :aria-label="`Remove member ${index + 1}`"
                  class="text-danger"
                  @click="removeAt(index)"
                >
                  <TrashIcon class="size-4" />
                </button>
              </div>
              <p v-if="!form.members.length" class="p-5 text-center text-xs text-slate-400">
                No members selected.
              </p>
            </div>
            <p v-if="fieldError('members')" class="text-[10px] text-danger">
              {{ fieldError('members') }}
            </p>
          </div>
        </article>
      </fieldset>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="emit('remove')"
        >
          <TrashIcon class="size-4" />Delete group
        </button>
      </div>
      <div class="slide-over-actions flex justify-end gap-3 pt-5">
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
          {{ saving ? 'Saving…' : 'Save group' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
