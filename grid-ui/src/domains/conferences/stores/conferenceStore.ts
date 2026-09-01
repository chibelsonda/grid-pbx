import axios from 'axios'
import { defineStore } from 'pinia'
import { conferenceApi } from '../api/conferenceApi'
import type {
  Conference,
  ConferenceBulkControlObservation,
  ConferenceBulkParticipantAction,
  ConferenceControlAction,
  ConferenceInput,
  ConferenceOptions,
  ConferenceParticipant,
  ConferenceParticipantAction,
} from '../types/conference'

const emptyOptions: ConferenceOptions = { owners: [], media: [], playable_media: [] }
const bulkReconciliationAttempts = 4
const bulkReconciliationDelayMs = 250

function message(error: unknown, fallback: string): string {
  return axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback
}

function bulkTargetCount(
  participants: ConferenceParticipant[],
  action: ConferenceBulkParticipantAction,
): number {
  return participants.filter((participant) => {
    if (participant.is_moderator) return false

    if (action === 'mute') return participant.can_speak
    if (action === 'unmute') return !participant.can_speak
    if (action === 'deaf') return participant.can_hear

    return !participant.can_hear
  }).length
}

function wait(milliseconds: number): Promise<void> {
  return new Promise((resolve) => window.setTimeout(resolve, milliseconds))
}

export const useConferenceStore = defineStore('conferences', {
  state: () => ({
    records: [] as Conference[],
    detail: null as Conference | null,
    options: { ...emptyOptions },
    search: '',
    status: '',
    page: 1,
    lastPage: 1,
    total: 0,
    loading: false,
    saving: false,
    synchronizing: false,
    controllingId: null as string | null,
    participants: [] as ConferenceParticipant[],
    participantsLoading: false,
    participantControlId: null as string | null,
    bulkControllingAction: null as ConferenceBulkParticipantAction | null,
    bulkControlObservation: null as ConferenceBulkControlObservation | null,
    playingMedia: false,
    participantError: null as string | null,
    error: null as string | null,
    mutationError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
  }),
  actions: {
    reset(): void {
      this.records = []
      this.detail = null
      this.options = { ...emptyOptions }
      this.total = 0
      this.participants = []
      this.bulkControllingAction = null
      this.bulkControlObservation = null
      this.participantError = null
      this.error = null
      this.clearMutationError()
    },
    clearMutationError(): void {
      this.mutationError = null
      this.fieldErrors = {}
    },
    capture(error: unknown, fallback: string): void {
      this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
      this.mutationError =
        Object.keys(this.fieldErrors).length > 0 ? null : message(error, fallback)
    },
    async load(accountId: string, page = 1): Promise<void> {
      this.loading = true
      this.error = null
      try {
        const response = await conferenceApi.list(accountId, this.search, this.status, page)
        this.records = response.data
        this.page = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
      } catch (error) {
        this.error = message(error, 'Unable to load conferences.')
      } finally {
        this.loading = false
      }
    },
    async prepare(accountId: string, id?: string): Promise<void> {
      this.loading = true
      this.clearMutationError()
      try {
        const [options, detail] = await Promise.all([
          conferenceApi.options(accountId),
          id ? conferenceApi.detail(accountId, id) : Promise.resolve(null),
        ])
        this.options = options
        this.detail = detail
      } catch (error) {
        this.error = message(error, 'Unable to prepare the conference panel.')
      } finally {
        this.loading = false
      }
    },
    async loadOptions(accountId: string): Promise<void> {
      try {
        this.options = await conferenceApi.options(accountId)
      } catch (error) {
        this.participantError = message(error, 'Unable to load conference media options.')
      }
    },
    replace(record: Conference): void {
      const index = this.records.findIndex(({ id }) => id === record.id)
      if (index >= 0) this.records[index] = record
      else this.records.unshift(record)
      this.detail = record
    },
    async save(accountId: string, input: ConferenceInput): Promise<boolean> {
      this.saving = true
      this.clearMutationError()
      try {
        const isNew = !this.detail
        this.replace(
          isNew
            ? await conferenceApi.create(accountId, input)
            : await conferenceApi.update(accountId, this.detail!.id, input),
        )
        if (isNew) this.total += 1
        await this.load(accountId, this.page)
        return true
      } catch (error) {
        this.capture(error, 'Unable to save conference.')
        return false
      } finally {
        this.saving = false
      }
    },
    async remove(accountId: string): Promise<boolean> {
      if (!this.detail) return false
      this.saving = true
      this.clearMutationError()
      try {
        const id = this.detail.id
        await conferenceApi.remove(accountId, id)
        this.records = this.records.filter((record) => record.id !== id)
        this.detail = null
        this.total = Math.max(0, this.total - 1)
        await this.load(accountId, this.page)
        return true
      } catch (error) {
        this.capture(error, 'Unable to delete conference.')
        return false
      } finally {
        this.saving = false
      }
    },
    async control(
      accountId: string,
      conference: Conference,
      action: ConferenceControlAction,
    ): Promise<boolean> {
      this.controllingId = conference.id
      this.error = null
      try {
        await conferenceApi.control(accountId, conference.id, action)
        await this.synchronize(accountId)

        return true
      } catch (error) {
        this.error = message(error, `Unable to ${action} conference.`)

        return false
      } finally {
        this.controllingId = null
      }
    },
    async loadParticipants(accountId: string, conferenceId: string): Promise<void> {
      this.participantsLoading = true
      this.participantError = null
      try {
        this.participants = await conferenceApi.participants(accountId, conferenceId)
      } catch (error) {
        this.participantError = message(error, 'Unable to load active conference participants.')
      } finally {
        this.participantsLoading = false
      }
    },
    async controlParticipant(
      accountId: string,
      conference: Conference,
      participant: ConferenceParticipant,
      action: ConferenceParticipantAction,
    ): Promise<boolean> {
      this.participantControlId = participant.id
      this.participantError = null
      try {
        await conferenceApi.controlParticipant(accountId, conference.id, participant.id, action)
        await this.synchronize(accountId)
        await this.loadParticipants(accountId, conference.id)

        return true
      } catch (error) {
        this.participantError = message(error, `Unable to ${action} participant.`)

        return false
      } finally {
        this.participantControlId = null
      }
    },
    async controlParticipants(
      accountId: string,
      conference: Conference,
      action: ConferenceBulkParticipantAction,
      expectedParticipantCount: number,
      expectedTargetCount: number,
    ): Promise<boolean> {
      this.bulkControllingAction = action
      this.bulkControlObservation = null
      this.participantError = null
      try {
        const result = await conferenceApi.controlParticipants(
          accountId,
          conference.id,
          action,
          expectedParticipantCount,
          expectedTargetCount,
        )
        this.bulkControlObservation = await this.reconcileBulkControl(
          accountId,
          conference.id,
          action,
          expectedParticipantCount,
          result.targeted_participants,
        )

        return true
      } catch (error) {
        const errorMessage = message(error, `Unable to ${action} room participants.`)
        await this.loadParticipants(accountId, conference.id)
        this.participantError = errorMessage

        return false
      } finally {
        this.bulkControllingAction = null
      }
    },
    async reconcileBulkControl(
      accountId: string,
      conferenceId: string,
      action: ConferenceBulkParticipantAction,
      expectedParticipantCount: number,
      expectedTargetCount: number,
    ): Promise<ConferenceBulkControlObservation> {
      let observedParticipants = 0

      for (let attempt = 0; attempt < bulkReconciliationAttempts; attempt += 1) {
        if (attempt > 0) await wait(bulkReconciliationDelayMs)

        try {
          this.participants = await conferenceApi.participants(accountId, conferenceId)
        } catch {
          return {
            action,
            status: 'pending',
            targeted_participants: expectedTargetCount,
            observed_participants: observedParticipants,
            message:
              'Switch accepted the command, but GridPBX could not refresh the live room. Refresh to verify the result.',
          }
        }

        if (this.participants.length !== expectedParticipantCount) {
          return {
            action,
            status: 'room_changed',
            targeted_participants: expectedTargetCount,
            observed_participants: observedParticipants,
            message:
              'Switch accepted the command, but room membership changed during verification. Review the refreshed participants.',
          }
        }

        const remainingTargets = bulkTargetCount(this.participants, action)
        observedParticipants = Math.max(0, expectedTargetCount - remainingTargets)

        if (remainingTargets === 0) {
          return {
            action,
            status: 'observed',
            targeted_participants: expectedTargetCount,
            observed_participants: expectedTargetCount,
            message: `Observed the requested state for all ${expectedTargetCount} targeted participant(s).`,
          }
        }
      }

      return {
        action,
        status: 'pending',
        targeted_participants: expectedTargetCount,
        observed_participants: observedParticipants,
        message: `Switch accepted the command; ${observedParticipants} of ${expectedTargetCount} targeted participant(s) are currently observed in the requested state.`,
      }
    },
    async playMedia(
      accountId: string,
      conference: Conference,
      mediaId: string,
      participantId: string | null,
    ): Promise<boolean> {
      this.playingMedia = true
      this.participantError = null
      try {
        await conferenceApi.playMedia(accountId, conference.id, mediaId, participantId)
        await this.loadParticipants(accountId, conference.id)

        return true
      } catch (error) {
        this.participantError = message(error, 'Unable to play media in the conference.')

        return false
      } finally {
        this.playingMedia = false
      }
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true
      this.error = null
      try {
        let run = await conferenceApi.startSync(accountId)
        for (
          let attempt = 0;
          attempt < 40 && ['queued', 'running'].includes(run.status);
          attempt += 1
        ) {
          await new Promise((resolve) => window.setTimeout(resolve, 500))
          run = await conferenceApi.syncStatus(accountId, run.id)
        }
        if (run.status !== 'succeeded')
          throw new Error(run.error_message ?? 'Conference sync did not finish.')
        await this.load(accountId)
      } catch (error) {
        this.error =
          error instanceof Error
            ? error.message
            : message(error, 'Unable to synchronize conferences.')
      } finally {
        this.synchronizing = false
      }
    },
  },
})
