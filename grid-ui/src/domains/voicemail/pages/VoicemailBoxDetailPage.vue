<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowDownTrayIcon,
  EnvelopeIcon,
  KeyIcon,
  LinkIcon,
  MicrophoneIcon,
  PencilSquareIcon,
  SparklesIcon,
  TrashIcon,
  CloudArrowUpIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormFileInput from '@/shared/components/FormFileInput.vue'
import FormInput from '@/shared/components/FormInput.vue'
import PageBackLink from '@/shared/components/PageBackLink.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { voicemailApi } from '../api/voicemailApi'
import { voicemailGreetingFormSchema } from '../schemas/voicemailGreetingFormSchema'
import { useVoicemailStore } from '../stores/voicemailStore'
import type { VoicemailMessage, VoicemailMessageFolder } from '../types/voicemail'

const route = useRoute()
const router = useRouter()
const accounts = useAccountStore()
const voicemail = useVoicemailStore()
const voicemailBoxId = computed(() => String(route.params.voicemailBoxId))
const canManage = computed(() => accounts.selected?.permissions.can_manage_voicemail ?? false)
const allMessagesSelected = computed(
  () =>
    voicemail.messages.length > 0 &&
    voicemail.messages.every((message) => voicemail.selectedMessageIds.includes(message.id)),
)
const greetingPanelOpen = ref(false)
const greetingName = ref('')
const greetingAudio = ref<File | null>(null)
const greetingValidationErrors = ref<FormErrors>({})

watch(greetingName, () => (greetingValidationErrors.value = {}))

watch(
  [() => accounts.selectedId, voicemailBoxId],
  ([accountId, selectedId]) => {
    if (accountId && selectedId) {
      void voicemail.loadDetail(accountId, selectedId)
      void voicemail.loadMessages(accountId, selectedId, 1)
    }
  },
  { immediate: true },
)

watch([() => voicemail.detail?.id, () => route.query.action], async ([detailId, action]) => {
  if (detailId !== voicemailBoxId.value || action !== 'delete' || !canManage.value) return

  const query = { ...route.query }
  delete query.action
  await router.replace({ query })
  await remove()
})

async function remove(): Promise<void> {
  if (!accounts.selectedId || !voicemail.detail) return
  if (!window.confirm(`Delete voicemail box ${voicemail.detail.mailbox ?? voicemail.detail.name}?`))
    return
  if (await voicemail.remove(accounts.selectedId, voicemail.detail.id))
    await router.push({ name: 'voicemail' })
}

function loadMessages(page = 1): void {
  if (accounts.selectedId)
    void voicemail.loadMessages(accounts.selectedId, voicemailBoxId.value, page)
}

function formatDuration(length: number | null): string {
  if (length === null) return 'Unknown duration'
  const seconds = Math.max(0, Math.round(length / 1000))
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}

function formatDate(value: string | null): string {
  return value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(
        new Date(value),
      )
    : 'Unknown time'
}

function audioUrl(messageId: string, download = false): string {
  return accounts.selectedId
    ? voicemailApi.audioUrl(accounts.selectedId, voicemailBoxId.value, messageId, download)
    : ''
}

function greetingAudioUrl(): string {
  return accounts.selectedId
    ? voicemailApi.greetingAudioUrl(accounts.selectedId, voicemailBoxId.value)
    : ''
}

function openGreetingPanel(): void {
  greetingName.value = voicemail.detail?.unavailable_greeting?.name ?? ''
  greetingAudio.value = null
  greetingValidationErrors.value = {}
  voicemail.greetingMutationError = null
  greetingPanelOpen.value = true
}

function selectGreetingAudio(file: File | null): void {
  greetingAudio.value = file
  greetingValidationErrors.value = {}
}

async function uploadGreeting(): Promise<void> {
  if (!accounts.selectedId) return

  const result = validateForm(voicemailGreetingFormSchema, {
    name: greetingName.value,
    audio: greetingAudio.value,
  })
  greetingValidationErrors.value = result.errors
  if (!result.success) return

  const succeeded = await voicemail.uploadGreeting(
    accounts.selectedId,
    voicemailBoxId.value,
    result.data.name,
    result.data.audio,
  )
  if (succeeded) greetingPanelOpen.value = false
}

async function removeGreeting(): Promise<void> {
  if (!accounts.selectedId || !voicemail.detail?.unavailable_greeting) return
  if (
    !window.confirm(
      'Remove this greeting from the voicemail box? The Switch media file will be retained.',
    )
  )
    return
  await voicemail.removeGreeting(accounts.selectedId, voicemailBoxId.value)
}

function formatBytes(value: number | null): string {
  if (value === null) return 'Unknown size'
  if (value < 1024) return `${value} B`
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`
  return `${(value / (1024 * 1024)).toFixed(1)} MB`
}

function toggleAllMessages(selected: boolean | string[]): void {
  voicemail.selectedMessageIds =
    selected === true ? voicemail.messages.map((message) => message.id) : []
}

async function changeMessageFolder(
  message: VoicemailMessage,
  folder: VoicemailMessageFolder,
): Promise<void> {
  if (!accounts.selectedId) return
  const succeeded = await voicemail.changeMessageFolder(
    accounts.selectedId,
    voicemailBoxId.value,
    message.id,
    folder,
  )
  if (succeeded)
    await voicemail.loadMessages(accounts.selectedId, voicemailBoxId.value, voicemail.messagePage)
}

async function bulkChangeMessageFolder(folder: VoicemailMessageFolder): Promise<void> {
  if (!accounts.selectedId) return
  const succeeded = await voicemail.bulkChangeMessageFolder(
    accounts.selectedId,
    voicemailBoxId.value,
    folder,
  )
  if (succeeded)
    await voicemail.loadMessages(accounts.selectedId, voicemailBoxId.value, voicemail.messagePage)
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container-narrow flex items-center gap-4">
      <div class="min-w-0">
        <p class="mb-1 flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
          <PageBackLink label="Back to voicemail" to="/voicemail" />
          <span aria-hidden="true">/</span>
          <span>Detail</span>
        </p>
        <h1 class="truncate text-xl font-semibold tracking-tight text-slate-800">
          {{ voicemail.detail?.name ?? 'Voicemail box' }}
        </h1>
        <p class="mt-1 font-mono text-xs text-slate-500">
          Mailbox {{ voicemail.detail?.mailbox ?? '—' }}
        </p>
      </div>
      <div v-if="voicemail.detail && canManage" class="ml-auto flex gap-2">
        <RouterLink
          :to="{ name: 'voicemail-edit', params: { voicemailBoxId } }"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm hover:bg-brand-50 hover:text-brand-600"
          ><PencilSquareIcon class="size-4" /> Edit</RouterLink
        >
        <button
          type="button"
          :disabled="voicemail.mutationLoading"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-red-100 bg-red-50 px-4 text-xs font-semibold text-danger hover:bg-red-100 disabled:opacity-50"
          @click="remove"
        >
          <TrashIcon class="size-4" /> Delete
        </button>
      </div>
    </div>
  </section>

  <div class="page-container-narrow py-4 sm:py-6 lg:py-8">
    <div
      v-if="voicemail.detailLoading"
      class="card-surface grid min-h-72 place-items-center text-xs text-slate-400"
    >
      Loading voicemail configuration…
    </div>
    <div v-else-if="voicemail.detailError" class="card-surface p-8 text-center text-xs text-danger">
      {{ voicemail.detailError }}
    </div>
    <template v-else-if="voicemail.detail">
      <div
        v-if="voicemail.mutationError"
        class="mb-5 rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ voicemail.mutationError }}
      </div>
      <div class="mb-5 grid gap-4 sm:grid-cols-4">
        <article
          v-for="count in [
            {
              label: 'All messages',
              value: voicemail.detail.message_counts.total,
              tone: 'text-slate-700',
            },
            { label: 'New', value: voicemail.detail.message_counts.new, tone: 'text-brand-600' },
            {
              label: 'Saved',
              value: voicemail.detail.message_counts.saved,
              tone: 'text-emerald-600',
            },
            {
              label: 'Deleted',
              value: voicemail.detail.message_counts.deleted,
              tone: 'text-slate-400',
            },
          ]"
          :key="count.label"
          class="card-surface p-4"
        >
          <p class="text-xl font-semibold" :class="count.tone">{{ count.value }}</p>
          <p class="mt-1 text-[10px] font-bold tracking-wide text-slate-400 uppercase">
            {{ count.label }}
          </p>
        </article>
      </div>
      <div class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
        <div class="grid content-start gap-5">
          <article class="card-surface overflow-hidden">
            <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
              <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600"
                ><MicrophoneIcon class="size-5"
              /></span>
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Mailbox configuration</h2>
                <p class="text-[10px] text-slate-400">Normalized Switch projection</p>
              </div>
            </header>
            <dl class="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2">
              <div>
                <dt class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">Name</dt>
                <dd class="mt-1 text-xs font-semibold text-slate-700">
                  {{ voicemail.detail.name ?? '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                  Mailbox
                </dt>
                <dd class="mt-1 font-mono text-xs text-slate-700">
                  {{ voicemail.detail.mailbox ?? '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                  Timezone
                </dt>
                <dd class="mt-1 text-xs text-slate-700">
                  {{ voicemail.detail.timezone ?? 'Account default' }}
                </dd>
              </div>
              <div>
                <dt class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                  Initial setup
                </dt>
                <dd class="mt-1">
                  <span
                    class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                    :class="
                      voicemail.detail.is_setup
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-amber-50 text-amber-700'
                    "
                    >{{ voicemail.detail.is_setup ? 'Complete' : 'Pending' }}</span
                  >
                </dd>
              </div>
            </dl>
          </article>

          <article class="card-surface overflow-hidden">
            <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
              <EnvelopeIcon class="size-5 text-emerald-500" />
              <h2 class="text-sm font-semibold text-slate-700">Email notifications</h2>
            </header>
            <div class="p-5">
              <div v-if="voicemail.detail.notification_emails.length" class="flex flex-wrap gap-2">
                <span
                  v-for="email in voicemail.detail.notification_emails"
                  :key="email"
                  class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600"
                  >{{ email }}</span
                >
              </div>
              <p v-else class="text-xs text-slate-400">
                No additional notification addresses configured.
              </p>
            </div>
          </article>

          <article class="card-surface overflow-hidden">
            <header
              class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center"
            >
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Messages</h2>
                <p class="text-[10px] text-slate-400">
                  Metadata is projected; audio streams from Switch on demand.
                </p>
              </div>
              <form class="relative sm:ml-auto sm:w-64" @submit.prevent="loadMessages(1)">
                <SearchInput
                  v-model="voicemail.messageSearch"
                  label="Search voicemail messages"
                  placeholder="Search caller or transcript…"
                  input-class="h-9 text-[11px]"
                  live
                  @search="loadMessages(1)"
                />
              </form>
              <FormSelect
                v-model="voicemail.messageFolder"
                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-[11px] outline-none"
                @change="loadMessages(1)"
              >
                <option value="">All folders</option>
                <option value="new">New</option>
                <option value="saved">Saved</option>
                <option value="deleted">Deleted</option>
              </FormSelect>
            </header>
            <div
              v-if="voicemail.messagesError"
              class="border-b border-red-100 bg-red-50 px-5 py-3 text-xs text-danger"
            >
              {{ voicemail.messagesError }}
            </div>
            <div
              v-if="voicemail.messageMutationError"
              class="border-b border-red-100 bg-red-50 px-5 py-3 text-xs text-danger"
            >
              {{ voicemail.messageMutationError }}
            </div>
            <div
              v-if="canManage && voicemail.messages.length > 0"
              class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/70 px-5 py-3"
            >
              <FormCheckbox
                class="mr-2"
                :model-value="allMessagesSelected"
                :label="
                  voicemail.selectedMessageIds.length
                    ? `${voicemail.selectedMessageIds.length} selected`
                    : 'Select page'
                "
                variant="inline"
                @update:model-value="toggleAllMessages"
              />
              <button
                type="button"
                :disabled="
                  voicemail.selectedMessageIds.length === 0 || voicemail.messageMutationLoading
                "
                class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-semibold text-slate-600 shadow-sm hover:bg-brand-50 hover:text-brand-600 disabled:opacity-40"
                @click="bulkChangeMessageFolder('new')"
              >
                Mark new
              </button>
              <button
                type="button"
                :disabled="
                  voicemail.selectedMessageIds.length === 0 || voicemail.messageMutationLoading
                "
                class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-semibold text-slate-600 shadow-sm hover:bg-emerald-50 hover:text-emerald-700 disabled:opacity-40"
                @click="bulkChangeMessageFolder('saved')"
              >
                Save
              </button>
              <button
                type="button"
                :disabled="
                  voicemail.selectedMessageIds.length === 0 || voicemail.messageMutationLoading
                "
                class="rounded-md border border-red-100 bg-white px-3 py-1.5 text-[10px] font-semibold text-danger shadow-sm hover:bg-red-50 disabled:opacity-40"
                @click="bulkChangeMessageFolder('deleted')"
              >
                Delete
              </button>
            </div>
            <div
              v-if="voicemail.messagesLoading"
              class="px-5 py-12 text-center text-xs text-slate-400"
            >
              Loading message metadata…
            </div>
            <div
              v-else-if="voicemail.messages.length === 0"
              class="px-5 py-12 text-center text-xs text-slate-400"
            >
              No projected messages match this filter.
            </div>
            <div v-else class="divide-y divide-slate-100">
              <article
                v-for="message in voicemail.messages"
                :key="message.id"
                class="grid gap-4 p-5"
              >
                <div class="flex flex-wrap items-start gap-3">
                  <FormCheckbox
                    v-if="canManage"
                    :model-value="voicemail.selectedMessageIds"
                    :value="message.id"
                    :label="`Select message from ${message.caller_id_name || message.caller_id_number || 'unknown caller'}`"
                    hide-label
                    variant="inline"
                    @update:model-value="voicemail.selectedMessageIds = $event as string[]"
                  />
                  <span
                    class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                    :class="
                      message.folder === 'new'
                        ? 'bg-brand-50 text-brand-600'
                        : message.folder === 'saved'
                          ? 'bg-emerald-50 text-emerald-700'
                          : 'bg-slate-100 text-slate-500'
                    "
                    >{{ message.folder ?? 'Unknown' }}</span
                  >
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-700">
                      {{ message.caller_id_name || message.caller_id_number || 'Unknown caller' }}
                    </p>
                    <p class="mt-1 text-[10px] text-slate-400">
                      {{ message.caller_id_number ?? 'Private number' }} ·
                      {{ formatDate(message.occurred_at) }} · {{ formatDuration(message.length) }}
                    </p>
                  </div>
                  <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                    <template v-if="canManage">
                      <button
                        v-if="message.folder !== 'new'"
                        type="button"
                        :disabled="voicemail.messageMutationLoading"
                        class="h-8 rounded-md border border-slate-200 px-3 text-[10px] font-semibold text-slate-500 hover:bg-brand-50 hover:text-brand-600 disabled:opacity-40"
                        @click="changeMessageFolder(message, 'new')"
                      >
                        Mark new
                      </button>
                      <button
                        v-if="message.folder !== 'saved'"
                        type="button"
                        :disabled="voicemail.messageMutationLoading"
                        class="h-8 rounded-md border border-slate-200 px-3 text-[10px] font-semibold text-slate-500 hover:bg-emerald-50 hover:text-emerald-700 disabled:opacity-40"
                        @click="changeMessageFolder(message, 'saved')"
                      >
                        {{ message.folder === 'deleted' ? 'Restore' : 'Save' }}
                      </button>
                      <button
                        v-if="message.folder !== 'deleted'"
                        type="button"
                        :disabled="voicemail.messageMutationLoading"
                        class="h-8 rounded-md border border-red-100 px-3 text-[10px] font-semibold text-danger hover:bg-red-50 disabled:opacity-40"
                        @click="changeMessageFolder(message, 'deleted')"
                      >
                        Delete
                      </button>
                    </template>
                    <a
                      :href="audioUrl(message.id, true)"
                      class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 px-3 text-[10px] font-semibold text-slate-500 hover:bg-brand-50 hover:text-brand-600"
                      ><ArrowDownTrayIcon class="size-3.5" /> Download</a
                    >
                  </div>
                </div>
                <p
                  v-if="message.transcription_text"
                  class="rounded-md bg-slate-50 px-4 py-3 text-[11px] leading-5 text-slate-600"
                >
                  {{ message.transcription_text }}
                </p>
                <audio class="h-9 w-full" controls preload="none" :src="audioUrl(message.id)">
                  Your browser does not support voicemail audio playback.
                </audio>
              </article>
            </div>
            <footer
              class="flex items-center border-t border-slate-100 px-5 py-3 text-[11px] text-slate-500"
            >
              <span>{{ voicemail.messageTotal }} messages</span>
              <div class="ml-auto flex items-center gap-2">
                <button
                  type="button"
                  :disabled="voicemail.messagePage <= 1"
                  class="rounded border border-slate-200 px-3 py-1.5 disabled:opacity-40"
                  @click="loadMessages(voicemail.messagePage - 1)"
                >
                  Previous</button
                ><span>Page {{ voicemail.messagePage }} of {{ voicemail.messageLastPage }}</span
                ><button
                  type="button"
                  :disabled="voicemail.messagePage >= voicemail.messageLastPage"
                  class="rounded border border-slate-200 px-3 py-1.5 disabled:opacity-40"
                  @click="loadMessages(voicemail.messagePage + 1)"
                >
                  Next
                </button>
              </div>
            </footer>
          </article>
        </div>

        <div class="grid content-start gap-5">
          <article class="card-surface overflow-hidden">
            <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
              <LinkIcon class="size-5 text-blue-500" />
              <h2 class="text-sm font-semibold text-slate-700">Assignment</h2>
            </header>
            <div class="p-5">
              <RouterLink
                v-if="voicemail.detail.assigned_extension"
                :to="{
                  name: 'extension-detail',
                  params: { extensionId: voicemail.detail.assigned_extension.id },
                }"
                class="text-xs font-semibold text-brand-600"
                >{{ voicemail.detail.assigned_extension.display_name }}
                <span class="ml-1 font-mono text-slate-400">{{
                  voicemail.detail.assigned_extension.extension
                }}</span></RouterLink
              >
              <p v-else class="text-xs text-slate-400">Unassigned mailbox</p>
            </div>
          </article>
          <article class="card-surface overflow-hidden">
            <header class="border-b border-slate-100 px-5 py-4">
              <h2 class="text-sm font-semibold text-slate-700">Features</h2>
            </header>
            <div class="grid gap-3 p-5">
              <div class="flex items-center gap-3">
                <SparklesIcon class="size-5 text-violet-500" />
                <div>
                  <p class="text-xs font-semibold text-slate-600">Transcription</p>
                  <p class="text-[10px] text-slate-400">
                    {{ voicemail.detail.transcribe ? 'Enabled' : 'Disabled' }}
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <KeyIcon class="size-5 text-amber-500" />
                <div>
                  <p class="text-xs font-semibold text-slate-600">PIN required</p>
                  <p class="text-[10px] text-slate-400">
                    {{
                      voicemail.detail.require_pin ? 'Required from owner devices' : 'Not required'
                    }}
                  </p>
                </div>
              </div>
            </div>
          </article>
          <article class="card-surface overflow-hidden">
            <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
              <MicrophoneIcon class="size-5 text-brand-500" />
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Unavailable greeting</h2>
                <p class="text-[10px] text-slate-400">Played before callers leave a message</p>
              </div>
            </header>
            <div class="grid gap-4 p-5">
              <template v-if="voicemail.detail.unavailable_greeting">
                <div>
                  <p class="text-xs font-semibold text-slate-700">
                    {{ voicemail.detail.unavailable_greeting.name ?? 'Custom greeting' }}
                  </p>
                  <p class="mt-1 text-[10px] text-slate-400">
                    {{
                      voicemail.detail.unavailable_greeting.description ??
                      voicemail.detail.unavailable_greeting.content_type ??
                      'Switch media'
                    }}
                    · {{ formatBytes(voicemail.detail.unavailable_greeting.content_length) }}
                  </p>
                </div>
                <audio class="h-9 w-full" controls preload="none" :src="greetingAudioUrl()">
                  Your browser does not support greeting playback.
                </audio>
                <div v-if="canManage" class="flex gap-2">
                  <button
                    type="button"
                    class="h-8 flex-1 rounded-md bg-brand-500 px-3 text-[10px] font-semibold text-white hover:bg-brand-600"
                    @click="openGreetingPanel"
                  >
                    Replace</button
                  ><button
                    type="button"
                    :disabled="voicemail.greetingMutationLoading"
                    class="h-8 rounded-md border border-red-100 px-3 text-[10px] font-semibold text-danger hover:bg-red-50 disabled:opacity-40"
                    @click="removeGreeting"
                  >
                    Remove
                  </button>
                </div>
              </template>
              <template v-else>
                <p class="text-xs leading-5 text-slate-400">
                  No custom greeting is assigned. Switch will use its default voicemail prompt.
                </p>
                <button
                  v-if="canManage"
                  type="button"
                  class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white hover:bg-brand-600"
                  @click="openGreetingPanel"
                >
                  <CloudArrowUpIcon class="size-4" /> Upload greeting
                </button>
              </template>
              <p v-if="voicemail.greetingMutationError" class="text-[11px] text-danger">
                {{ voicemail.greetingMutationError }}
              </p>
            </div>
          </article>
          <article class="card-surface p-5">
            <p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">Projection</p>
            <p class="mt-2 text-xs font-semibold text-slate-600">
              {{ voicemail.detail.sync_status }}
            </p>
            <p class="mt-1 text-[10px] text-slate-400">
              {{
                voicemail.detail.last_synced_at
                  ? new Date(voicemail.detail.last_synced_at).toLocaleString()
                  : 'Never synchronized'
              }}
            </p>
          </article>
        </div>
      </div>
    </template>
  </div>

  <CrudSlideOver
    v-if="greetingPanelOpen"
    title="Upload unavailable greeting"
    eyebrow="GridPBX / Voicemail / Greeting"
    description="The audio is uploaded to Switch; MySQL stores only its metadata projection."
    width="medium"
    @close="greetingPanelOpen = false"
  >
    <form class="grid gap-5" novalidate @submit.prevent="uploadGreeting">
      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <CloudArrowUpIcon class="size-5 text-brand-500" />
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Greeting audio</h2>
            <p class="text-[10px] text-slate-400">MP3, WAV, or OGG · maximum 10 MB</p>
          </div>
        </header>
        <div class="grid gap-5 p-5">
          <FormInput
            v-model="greetingName"
            label="Display name"
            maxlength="128"
            placeholder="Reception unavailable greeting"
            :error="greetingValidationErrors.name"
          />
          <FormFileInput
            v-model="greetingAudio"
            label="Audio file"
            required
            accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg"
            :error="greetingValidationErrors.audio"
            @change="selectGreetingAudio"
          />
          <div
            class="rounded-md border border-blue-100 bg-blue-50 px-4 py-3 text-[11px] leading-5 text-blue-700"
          >
            Replacing a greeting changes the mailbox reference only after the new upload succeeds.
            Removing it later retains the media file in Switch to avoid deleting shared audio.
          </div>
        </div>
      </article>
      <div
        v-if="voicemail.greetingMutationError"
        class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ voicemail.greetingMutationError }}
      </div>
      <div class="slide-over-actions flex justify-end">
        <button
          type="submit"
          :disabled="voicemail.greetingMutationLoading"
          class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
        >
          <CloudArrowUpIcon class="size-4" />{{
            voicemail.greetingMutationLoading ? 'Uploading…' : 'Upload and assign'
          }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
