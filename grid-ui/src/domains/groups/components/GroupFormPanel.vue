<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ArrowDownIcon, ArrowUpIcon, TrashIcon, UserGroupIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
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
const confirmDelete = ref(false)
const selectedType = ref<GroupMemberType>('user')
const selectedId = ref('')
const form = reactive<GroupInput>({
  name: props.record?.name ?? '',
  music_on_hold_media_id: props.record?.music_on_hold_media?.id ?? null,
  members:
    props.record?.members?.flatMap((member) =>
      member.target ? [{ type: member.type, id: member.target.id, weight: member.weight }] : [],
    ) ?? [],
})
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
</script>

<template>
  <CrudSlideOver
    :title="!canManage ? 'View group' : record ? 'Edit group' : 'Create group'"
    eyebrow="GridPBX / Groups"
    description="Reusable membership for call routing, ring groups, pickup, and nested group destinations."
    width="medium"
    @close="emit('close')"
  >
    <form
      class="grid gap-5"
      @submit.prevent="
        canManage &&
        emit('save', { ...form, members: form.members.map((member) => ({ ...member })) })
      "
    >
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
            ><UserGroupIcon class="size-5"
          /></span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Group settings</h2>
            <p class="text-[10px] text-slate-400">The order becomes the Switch endpoint weight.</p>
          </div>
        </header>
        <div class="grid gap-4 p-5">
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Name</span
            ><input
              v-model="form.name"
              required
              maxlength="128"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label
          ><label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Music on hold</span
            ><FormSelect
              v-model="form.music_on_hold_media_id"
              class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
              ><option :value="null">Account default</option>
              <option v-for="media in options.media" :key="media.id" :value="media.id">
                {{ media.label }}
              </option></FormSelect
            ></label
          >
        </div>
      </article>
      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Members</h2>
          <p class="mt-1 text-[10px] text-slate-400">
            Users, devices, and nested groups are resolved server-side.
          </p>
        </header>
        <div class="grid gap-4 p-5">
          <div class="grid gap-2 sm:grid-cols-[120px_1fr_auto]">
            <FormSelect
              v-model="selectedType"
              class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
              @change="selectedId = ''"
              ><option value="user">User</option>
              <option value="device">Device</option>
              <option value="group">Group</option></FormSelect
            ><FormSelect
              v-model="selectedId"
              class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
              ><option value="">Select target…</option>
              <option v-for="choice in choices" :key="choice.id" :value="choice.id">
                {{ choice.label }}
              </option></FormSelect
            ><button
              type="button"
              :disabled="!selectedId"
              class="h-10 rounded-md bg-slate-700 px-4 text-xs font-semibold text-white disabled:opacity-40"
              @click="add"
            >
              Add
            </button>
          </div>
          <div class="divide-y divide-slate-100 rounded-md border border-slate-100">
            <div
              v-for="(member, index) in form.members"
              :key="`${member.type}-${member.id}`"
              class="flex items-center gap-3 px-4 py-3"
            >
              <span
                class="grid size-7 place-items-center rounded bg-slate-100 text-[10px] font-bold text-slate-500"
                >{{ index + 1 }}</span
              >
              <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-semibold text-slate-700">
                  {{ label(member.type, member.id) }}
                </p>
                <p class="text-[10px] font-semibold text-slate-400 uppercase">{{ member.type }}</p>
              </div>
              <button
                type="button"
                :disabled="index === 0"
                class="text-slate-400 disabled:opacity-20"
                @click="move(index, -1)"
              >
                <ArrowUpIcon class="size-4" /></button
              ><button
                type="button"
                :disabled="index === form.members.length - 1"
                class="text-slate-400 disabled:opacity-20"
                @click="move(index, 1)"
              >
                <ArrowDownIcon class="size-4" /></button
              ><button type="button" class="text-danger" @click="removeAt(index)">
                <TrashIcon class="size-4" />
              </button>
            </div>
            <p v-if="!form.members.length" class="p-5 text-center text-xs text-slate-400">
              No members selected.
            </p>
          </div>
        </div>
      </article>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="confirmDelete = true"
        >
          <TrashIcon class="size-4" />Delete group
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
          :disabled="saving || !form.name.trim()"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : 'Save group' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete group"
    description="Delete this group after checking all nested and call-routing references?"
    confirm-label="Delete group"
    :busy="saving"
    @close="confirmDelete = false"
    @confirm="emit('remove')"
  />
</template>
