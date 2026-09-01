<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  ArrowPathIcon,
  MicrophoneIcon,
  MusicalNoteIcon,
  NoSymbolIcon,
  SpeakerWaveIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { conferencePlaybackSchema } from '../schemas/conferencePlaybackSchema'
import { conferenceBulkControlSchema } from '../schemas/conferenceBulkControlSchema'
import type {
  ConferenceBulkParticipantAction,
  ConferenceBulkControlObservation,
  Conference,
  ConferenceOption,
  ConferenceParticipant,
  ConferenceParticipantAction,
} from '../types/conference'

const props = defineProps<{
  conference: Conference
  participants: ConferenceParticipant[]
  loading: boolean
  controllingId: string | null
  playableMedia: ConferenceOption[]
  playingMedia: boolean
  bulkControllingAction: ConferenceBulkParticipantAction | null
  bulkControlObservation: ConferenceBulkControlObservation | null
  error: string | null
  canManage: boolean
}>()
const emit = defineEmits<{
  close: []
  refresh: []
  control: [participant: ConferenceParticipant, action: ConferenceParticipantAction]
  bulkControl: [
    action: ConferenceBulkParticipantAction,
    expectedParticipantCount: number,
    expectedTargetCount: number,
  ]
  playMedia: [mediaId: string, participantId: string | null]
}>()
const confirmingKick = ref<string | null>(null)
const selectedMedia = ref<string | null>(null)
const selectedTarget = ref<string>('room')
const confirmingPlayback = ref(false)
const playbackValidationError = ref<string | null>(null)
const confirmingBulkAction = ref<ConferenceBulkParticipantAction | null>(null)
const bulkValidationError = ref<string | null>(null)
const targetOptions = computed<ListboxOptionValue[]>(() => [
  { value: 'room', label: 'Entire room', description: 'All active participants' },
  ...props.participants.map((participant) => ({
    value: participant.id,
    label: participant.display_name ?? participant.number ?? 'Anonymous participant',
    description: participant.number,
  })),
])
const mediaOptions = computed<ListboxOptionValue[]>(() =>
  props.playableMedia.map((media) => ({
    value: media.id,
    label: media.label,
    description: media.detail,
  })),
)
const bulkActions: Array<{
  action: ConferenceBulkParticipantAction
  label: string
  pendingLabel: string
}> = [
  { action: 'mute', label: 'Mute members', pendingLabel: 'Muting…' },
  { action: 'unmute', label: 'Unmute members', pendingLabel: 'Unmuting…' },
  { action: 'deaf', label: 'Deafen members', pendingLabel: 'Deafening…' },
  { action: 'undeaf', label: 'Restore hearing', pendingLabel: 'Restoring…' },
]

function bulkTargetCount(action: ConferenceBulkParticipantAction): number {
  return props.participants.filter((participant) => {
    if (participant.is_moderator) return false

    if (action === 'mute') return participant.can_speak
    if (action === 'unmute') return !participant.can_speak
    if (action === 'deaf') return participant.can_hear

    return !participant.can_hear
  }).length
}

function beginBulkConfirmation(action: ConferenceBulkParticipantAction): void {
  confirmingBulkAction.value = action
  bulkValidationError.value = null
}

function confirmBulkControl(): void {
  if (confirmingBulkAction.value === null) return
  const result = conferenceBulkControlSchema.safeParse({
    action: confirmingBulkAction.value,
    expected_participant_count: props.participants.length,
    expected_target_count: bulkTargetCount(confirmingBulkAction.value),
    confirmation: true,
  })

  if (!result.success) {
    bulkValidationError.value =
      result.error.issues[0]?.message ?? 'Refresh the room before issuing this command.'

    return
  }

  emit(
    'bulkControl',
    result.data.action,
    result.data.expected_participant_count,
    result.data.expected_target_count,
  )
  confirmingBulkAction.value = null
}

function selectMedia(value: ListboxValue): void {
  selectedMedia.value = typeof value === 'string' ? value : null
  confirmingPlayback.value = false
  playbackValidationError.value = null
}

function selectTarget(value: ListboxValue): void {
  selectedTarget.value = typeof value === 'string' ? value : 'room'
  confirmingPlayback.value = false
  playbackValidationError.value = null
}

function playMedia(): void {
  if (selectedMedia.value === null) return
  const result = conferencePlaybackSchema.safeParse({
    media_id: selectedMedia.value,
    participant_id: selectedTarget.value === 'room' ? null : selectedTarget.value,
    confirmation: true,
  })

  if (!result.success) {
    playbackValidationError.value = result.error.issues[0]?.message ?? 'Select valid audio.'

    return
  }

  emit('playMedia', result.data.media_id, result.data.participant_id)
  confirmingPlayback.value = false
}

function duration(seconds: number): string {
  const minutes = Math.floor(seconds / 60)
  const remainder = seconds % 60

  return `${minutes}:${remainder.toString().padStart(2, '0')}`
}
</script>

<template>
  <CrudSlideOver
    :title="conference.name"
    eyebrow="GridPBX / Conferences / Live room"
    description="Runtime participants are read directly from Switch and are never persisted in MySQL."
    width="medium"
    @close="emit('close')"
  >
    <div class="grid gap-5">
      <section v-if="canManage" class="card-surface p-4">
        <div class="flex items-start gap-3">
          <MusicalNoteIcon class="mt-0.5 size-5 shrink-0 text-violet-500" />
          <div>
            <p class="text-sm font-semibold text-slate-700">Play media</p>
            <p class="mt-1 text-[11px] text-slate-500">
              Choose projected streamable audio. Switch accepts the request asynchronously.
            </p>
          </div>
        </div>
        <div v-if="playableMedia.length > 0" class="mt-4 grid gap-3 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-slate-600">Audio</label>
            <FormListbox
              :model-value="selectedMedia"
              :options="mediaOptions"
              aria-label="Conference media"
              placeholder="Select audio…"
              :disabled="playingMedia"
              @update:model-value="selectMedia"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-slate-600">Play to</label>
            <FormListbox
              :model-value="selectedTarget"
              :options="targetOptions"
              aria-label="Conference playback target"
              :disabled="playingMedia"
              @update:model-value="selectTarget"
            />
          </div>
        </div>
        <p v-else class="mt-4 text-xs text-slate-500">
          No projected streamable audio is available for this account.
        </p>
        <p v-if="playbackValidationError" role="alert" class="mt-3 text-xs text-danger">
          {{ playbackValidationError }}
        </p>
        <div v-if="playableMedia.length > 0" class="mt-3 flex justify-end gap-2">
          <template v-if="confirmingPlayback">
            <button
              type="button"
              class="h-8 rounded-md border border-slate-200 px-3 text-[11px] font-semibold text-slate-600"
              :disabled="playingMedia"
              @click="confirmingPlayback = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="h-8 rounded-md bg-violet-600 px-3 text-[11px] font-semibold text-white disabled:opacity-50"
              :disabled="playingMedia"
              @click="playMedia"
            >
              {{ playingMedia ? 'Submitting…' : 'Confirm playback' }}
            </button>
          </template>
          <button
            v-else
            type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-md bg-violet-600 px-3 text-[11px] font-semibold text-white disabled:opacity-50"
            :disabled="selectedMedia === null || playingMedia"
            @click="confirmingPlayback = true"
          >
            <MusicalNoteIcon class="size-3.5" />Play audio
          </button>
        </div>
      </section>

      <section v-if="canManage && participants.length > 0" class="card-surface p-4">
        <div class="flex items-start gap-3">
          <UserGroupIcon class="mt-0.5 size-5 shrink-0 text-cyan-500" />
          <div>
            <p class="text-sm font-semibold text-slate-700">Room participant controls</p>
            <p class="mt-1 text-[11px] text-slate-500">
              Kazoo applies these commands only to eligible non-moderators. A fresh room preview is
              verified before submission.
            </p>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
          <button
            v-for="item in bulkActions"
            :key="item.action"
            type="button"
            class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 text-[11px] font-semibold text-slate-600 disabled:opacity-40"
            :disabled="
              loading ||
              controllingId !== null ||
              bulkControllingAction !== null ||
              bulkTargetCount(item.action) === 0
            "
            @click="beginBulkConfirmation(item.action)"
          >
            <MicrophoneIcon
              v-if="item.action === 'mute' || item.action === 'unmute'"
              class="size-3.5"
            />
            <SpeakerWaveIcon v-else class="size-3.5" />
            {{ bulkControllingAction === item.action ? item.pendingLabel : item.label }}
            <span class="text-slate-400">({{ bulkTargetCount(item.action) }})</span>
          </button>
        </div>

        <div
          v-if="confirmingBulkAction"
          class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3"
        >
          <p class="text-xs font-semibold text-amber-900">
            Confirm {{ confirmingBulkAction }} for
            {{ bulkTargetCount(confirmingBulkAction) }} eligible participant(s)?
          </p>
          <p class="mt-1 text-[11px] text-amber-800">
            Moderators and participants already in the requested state are skipped.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              type="button"
              class="h-8 rounded-md border border-amber-200 bg-white px-3 text-[11px] font-semibold text-slate-600"
              :disabled="bulkControllingAction !== null"
              @click="confirmingBulkAction = null"
            >
              Cancel
            </button>
            <button
              type="button"
              class="h-8 rounded-md bg-amber-600 px-3 text-[11px] font-semibold text-white disabled:opacity-50"
              :disabled="bulkControllingAction !== null"
              @click="confirmBulkControl"
            >
              Confirm room command
            </button>
          </div>
        </div>
        <p v-if="bulkValidationError" role="alert" class="mt-3 text-xs text-danger">
          {{ bulkValidationError }}
        </p>
        <div
          v-if="bulkControlObservation"
          role="status"
          class="mt-3 rounded-md border p-3 text-xs"
          :class="
            bulkControlObservation.status === 'observed'
              ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
              : 'border-amber-200 bg-amber-50 text-amber-800'
          "
        >
          <p class="font-semibold">
            {{
              bulkControlObservation.status === 'observed'
                ? 'State observed'
                : 'Verification pending'
            }}
          </p>
          <p class="mt-1">{{ bulkControlObservation.message }}</p>
        </div>
      </section>

      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="text-sm font-semibold text-slate-700">Active participants</p>
          <p class="mt-1 text-[11px] text-slate-500">
            Commands are asynchronous and the list refreshes from Switch afterward.
          </p>
        </div>
        <button
          type="button"
          :disabled="loading || controllingId !== null || bulkControllingAction !== null"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 disabled:opacity-50"
          @click="emit('refresh')"
        >
          <ArrowPathIcon class="size-4" :class="loading && 'animate-spin'" />Refresh
        </button>
      </div>

      <div
        v-if="error"
        role="alert"
        class="rounded-md border border-red-200 bg-red-50 p-4 text-xs text-danger"
      >
        {{ error }}
      </div>

      <article v-if="loading && participants.length === 0" class="card-surface p-8 text-center">
        <ArrowPathIcon class="mx-auto size-6 animate-spin text-brand-500" />
        <p class="mt-3 text-xs text-slate-500">Loading the live room…</p>
      </article>
      <article
        v-else-if="participants.length === 0"
        class="card-surface p-8 text-center text-slate-500"
      >
        <UserGroupIcon class="mx-auto size-7 text-slate-400" />
        <p class="mt-3 text-sm font-semibold text-slate-700">No active participants</p>
        <p class="mt-1 text-xs">Switch does not currently report anyone in this room.</p>
      </article>
      <div v-else class="grid gap-3">
        <article v-for="participant in participants" :key="participant.id" class="card-surface p-4">
          <div class="flex items-start gap-3">
            <UserGroupIcon class="mt-0.5 size-5 shrink-0 text-brand-500" />
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-slate-700">
                {{ participant.display_name ?? participant.number ?? 'Anonymous participant' }}
              </p>
              <p class="mt-0.5 text-[11px] text-slate-500">
                {{ participant.number ?? 'Number unavailable' }} ·
                {{ duration(participant.duration_seconds) }}
              </p>
              <div class="mt-2 flex flex-wrap gap-1.5">
                <span
                  v-if="participant.is_moderator"
                  class="rounded-full bg-violet-50 px-2 py-1 text-[10px] font-semibold text-violet-700"
                  >Moderator</span
                >
                <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] text-slate-600">
                  {{ participant.can_speak ? 'Speaking enabled' : 'Muted' }}
                </span>
                <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] text-slate-600">
                  {{ participant.can_hear ? 'Hearing enabled' : 'Deafened' }}
                </span>
              </div>
            </div>
          </div>

          <div v-if="canManage" class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
            <button
              type="button"
              :disabled="controllingId !== null || bulkControllingAction !== null"
              class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 px-3 text-[11px] font-semibold text-slate-600 disabled:opacity-50"
              @click="emit('control', participant, participant.can_speak ? 'mute' : 'unmute')"
            >
              <MicrophoneIcon class="size-3.5" />{{ participant.can_speak ? 'Mute' : 'Unmute' }}
            </button>
            <button
              type="button"
              :disabled="controllingId !== null || bulkControllingAction !== null"
              class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 px-3 text-[11px] font-semibold text-slate-600 disabled:opacity-50"
              @click="emit('control', participant, participant.can_hear ? 'deaf' : 'undeaf')"
            >
              <SpeakerWaveIcon class="size-3.5" />{{
                participant.can_hear ? 'Deafen' : 'Restore hearing'
              }}
            </button>
            <template v-if="confirmingKick === participant.id">
              <button
                type="button"
                :disabled="controllingId !== null || bulkControllingAction !== null"
                class="h-8 rounded-md border border-slate-200 px-3 text-[11px] font-semibold text-slate-600"
                @click="confirmingKick = null"
              >
                Cancel
              </button>
              <button
                type="button"
                :disabled="controllingId !== null || bulkControllingAction !== null"
                class="h-8 rounded-md bg-red-600 px-3 text-[11px] font-semibold text-white disabled:opacity-50"
                @click="emit('control', participant, 'kick')"
              >
                Confirm kick
              </button>
            </template>
            <button
              v-else
              type="button"
              :disabled="controllingId !== null || bulkControllingAction !== null"
              class="inline-flex h-8 items-center gap-1.5 rounded-md border border-red-200 px-3 text-[11px] font-semibold text-red-600 disabled:opacity-50"
              @click="confirmingKick = participant.id"
            >
              <NoSymbolIcon class="size-3.5" />Kick
            </button>
          </div>
        </article>
      </div>
    </div>
  </CrudSlideOver>
</template>
