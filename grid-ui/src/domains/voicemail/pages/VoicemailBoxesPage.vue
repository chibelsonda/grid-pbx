<script setup lang="ts">
import { computed, watch } from 'vue'
import {
  ArrowPathIcon,
  ChevronRightIcon,
  ChatBubbleLeftEllipsisIcon,
  LinkIcon,
  MagnifyingGlassIcon,
  MicrophoneIcon,
  PlusIcon,
  SparklesIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useVoicemailStore } from '../stores/voicemailStore'

const accounts = useAccountStore()
const voicemail = useVoicemailStore()
const assignedOnPage = computed(
  () => voicemail.records.filter((record) => record.assigned_extension !== null).length,
)
const transcribedOnPage = computed(
  () => voicemail.records.filter((record) => record.transcribe).length,
)
const messagesOnPage = computed(() =>
  voicemail.records.reduce((total, record) => total + record.message_counts.total, 0),
)
const freshnessLabel = computed(() => {
  if (!voicemail.sync.last_successful_at) return 'Not synchronized yet'
  return `Last synchronized ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(voicemail.sync.last_successful_at))}`
})

watch(
  () => accounts.selectedId,
  (accountId) => {
    voicemail.reset()
    if (accountId) void voicemail.load(accountId, 1)
  },
  { immediate: true },
)

function loadFirstPage(): void {
  if (accounts.selectedId) void voicemail.load(accounts.selectedId, 1)
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-[1500px] flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Voicemail</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">Voicemail boxes</h1>
        <p class="mt-1 text-xs text-slate-500">
          Mailbox ownership, notifications, and transcription settings projected from Switch.
        </p>
      </div>
      <div class="flex gap-2 sm:ml-auto">
        <button
          type="button"
          :disabled="!accounts.selectedId || voicemail.loading"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm hover:bg-brand-50 hover:text-brand-600 disabled:opacity-50"
          @click="accounts.selectedId && voicemail.load(accounts.selectedId, voicemail.page)"
        >
          <ArrowPathIcon class="size-4" :class="voicemail.loading && 'animate-spin'" /> Reload
          projection
        </button>
        <RouterLink
          v-if="accounts.selectedId && accounts.selected?.permissions.can_manage_voicemail"
          :to="{ name: 'voicemail-create' }"
          class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600"
        >
          <PlusIcon class="size-4" /> Add mailbox
        </RouterLink>
      </div>
    </div>
  </section>

  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
    <div
      v-if="!accounts.loading && accounts.accounts.length === 0"
      class="card-surface grid min-h-72 place-items-center p-8 text-center"
    >
      <div>
        <MicrophoneIcon class="mx-auto size-10 text-slate-400" />
        <h2 class="mt-4 text-sm font-semibold text-slate-700">No Switch account is mapped</h2>
        <p class="mt-2 text-xs text-slate-500">
          Map and synchronize an account before viewing voicemail boxes.
        </p>
      </div>
    </div>

    <template v-else>
      <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="summary in [
            {
              label: 'Projected mailboxes',
              value: voicemail.total,
              icon: MicrophoneIcon,
              tone: 'bg-brand-50 text-brand-600',
            },
            {
              label: 'Assigned on page',
              value: assignedOnPage,
              icon: LinkIcon,
              tone: 'bg-blue-50 text-info',
            },
            {
              label: 'Messages on page',
              value: messagesOnPage,
              icon: ChatBubbleLeftEllipsisIcon,
              tone: 'bg-emerald-50 text-emerald-600',
            },
            {
              label: 'Transcription enabled',
              value: transcribedOnPage,
              icon: SparklesIcon,
              tone: 'bg-violet-50 text-violet-600',
            },
          ]"
          :key="summary.label"
          class="card-surface flex items-center gap-4 p-4"
        >
          <span class="grid size-10 place-items-center rounded-md" :class="summary.tone"
            ><component :is="summary.icon" class="size-5"
          /></span>
          <div>
            <p class="text-lg font-semibold text-slate-700">{{ summary.value }}</p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              {{ summary.label }}
            </p>
          </div>
        </article>
      </div>

      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form class="relative w-full max-w-sm" @submit.prevent="loadFirstPage">
          <MagnifyingGlassIcon
            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
          />
          <input
            v-model="voicemail.search"
            type="search"
            placeholder="Search mailbox, name, owner, timezone…"
            class="h-10 w-full rounded-md border border-slate-200 bg-white pr-3 pl-9 text-xs shadow-sm outline-none focus:border-brand-500"
          />
        </form>
        <span
          class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold sm:ml-auto"
          :class="
            voicemail.sync.status === 'healthy'
              ? 'border-emerald-100 bg-emerald-50 text-emerald-700'
              : voicemail.sync.status === 'error'
                ? 'border-red-100 bg-red-50 text-danger'
                : 'border-amber-100 bg-amber-50 text-amber-700'
          "
        >
          <span class="size-2 rounded-full bg-current" /> {{ freshnessLabel }}
        </span>
      </div>

      <div
        v-if="voicemail.error"
        class="mb-4 rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ voicemail.error }}
      </div>

      <div class="card-surface overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[850px] text-left">
            <thead
              class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
            >
              <tr>
                <th class="px-5 py-3.5">Mailbox</th>
                <th class="px-5 py-3.5">Assigned extension</th>
                <th class="px-5 py-3.5">Timezone</th>
                <th class="px-5 py-3.5">Notifications</th>
                <th class="px-5 py-3.5">Status</th>
                <th class="w-12 px-5 py-3.5"><span class="sr-only">View</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr v-if="voicemail.loading">
                <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                  Loading projected voicemail boxes…
                </td>
              </tr>
              <tr v-else-if="voicemail.records.length === 0">
                <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                  No projected voicemail boxes found.
                </td>
              </tr>
              <tr
                v-for="record in voicemail.records"
                v-else
                :key="record.id"
                class="hover:bg-slate-50/60"
              >
                <td class="px-5 py-3.5">
                  <RouterLink
                    :to="{ name: 'voicemail-detail', params: { voicemailBoxId: record.id } }"
                    class="font-semibold text-slate-700 hover:text-brand-600"
                    >{{ record.name ?? 'Unnamed mailbox' }}</RouterLink
                  >
                  <div class="mt-1 font-mono text-[10px] text-slate-400">
                    {{ record.mailbox ?? '—' }}
                  </div>
                </td>
                <td class="px-5 py-3.5">
                  <RouterLink
                    v-if="record.assigned_extension"
                    :to="{
                      name: 'extension-detail',
                      params: { extensionId: record.assigned_extension.id },
                    }"
                    class="font-semibold text-brand-600"
                    >{{ record.assigned_extension.display_name }}
                    <span class="ml-1 font-mono text-[10px] text-slate-400">{{
                      record.assigned_extension.extension
                    }}</span></RouterLink
                  ><span v-else class="text-slate-400">Unassigned</span>
                </td>
                <td class="px-5 py-3.5 text-slate-500">
                  {{ record.timezone ?? 'Account default' }}
                </td>
                <td class="px-5 py-3.5 text-slate-500">
                  {{
                    record.notification_emails.length
                      ? `${record.notification_emails.length} email${record.notification_emails.length === 1 ? '' : 's'}`
                      : 'None'
                  }}
                </td>
                <td class="px-5 py-3.5">
                  <div class="flex gap-1.5">
                    <span
                      class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                      :class="
                        record.is_setup
                          ? 'bg-emerald-50 text-emerald-700'
                          : 'bg-amber-50 text-amber-700'
                      "
                      >{{ record.is_setup ? 'Set up' : 'Pending setup' }}</span
                    ><span
                      v-if="record.transcribe"
                      class="rounded-full bg-violet-50 px-2.5 py-1 text-[10px] font-bold text-violet-700"
                      >Transcribe</span
                    >
                  </div>
                </td>
                <td class="px-5 py-3.5">
                  <RouterLink
                    :to="{ name: 'voicemail-detail', params: { voicemailBoxId: record.id } }"
                    class="grid size-8 place-items-center rounded text-slate-400 hover:bg-brand-50 hover:text-brand-600"
                    ><ChevronRightIcon class="size-4"
                  /></RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer
          class="flex items-center border-t border-slate-100 px-5 py-3 text-[11px] text-slate-500"
        >
          <span>{{ voicemail.total }} mailboxes</span>
          <div class="ml-auto flex items-center gap-2">
            <button
              type="button"
              :disabled="voicemail.page <= 1"
              class="rounded border border-slate-200 px-3 py-1.5 disabled:opacity-40"
              @click="
                accounts.selectedId && voicemail.load(accounts.selectedId, voicemail.page - 1)
              "
            >
              Previous</button
            ><span>Page {{ voicemail.page }} of {{ voicemail.lastPage }}</span
            ><button
              type="button"
              :disabled="voicemail.page >= voicemail.lastPage"
              class="rounded border border-slate-200 px-3 py-1.5 disabled:opacity-40"
              @click="
                accounts.selectedId && voicemail.load(accounts.selectedId, voicemail.page + 1)
              "
            >
              Next
            </button>
          </div>
        </footer>
      </div>
    </template>
  </div>
</template>
