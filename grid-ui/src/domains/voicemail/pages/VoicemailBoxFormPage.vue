<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircleIcon, KeyIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import { validateForm } from '@/shared/forms/zod'
import VoicemailBoxFormFields from '../components/VoicemailBoxFormFields.vue'
import { voicemailBoxFormSchemaFor } from '../schemas/voicemailBoxFormSchema'
import { useVoicemailStore } from '../stores/voicemailStore'
import type {
  VoicemailBoxBasicForm,
  VoicemailBoxConfiguration,
  VoicemailNotificationCallback,
} from '../types/voicemail'
import {
  buildVoicemailBoxInput,
  defaultVoicemailBoxBasicForm,
  defaultVoicemailBoxConfiguration,
  defaultVoicemailNotificationCallback,
  hydrateVoicemailBoxConfiguration,
} from '../voicemailForm'

const route = useRoute()
const router = useRouter()
const accounts = useAccountStore()
const voicemail = useVoicemailStore()
const isEditing = computed(() => route.name === 'voicemail-edit')
const voicemailBoxId = computed(() =>
  isEditing.value ? String(route.params.voicemailBoxId) : null,
)
const title = computed(() => (isEditing.value ? 'Edit voicemail box' : 'Create voicemail box'))
const canManage = computed(() => accounts.selected?.permissions.can_manage_voicemail ?? false)
const pinConfigured = computed(() => voicemail.detail?.pin_configured ?? false)
const form = reactive<VoicemailBoxBasicForm>(defaultVoicemailBoxBasicForm())
const configuration = reactive<VoicemailBoxConfiguration>(defaultVoicemailBoxConfiguration())
const callbackConfigured = ref(false)
const callbackSchedule = ref('')
const notificationCallback = reactive<VoicemailNotificationCallback>(
  defaultVoicemailNotificationCallback(),
)

watch(
  [() => accounts.selectedId, voicemailBoxId],
  async ([accountId, selectedId]) => {
    voicemail.mutationError = null
    voicemail.fieldErrors = {}
    if (!accountId) return
    await voicemail.loadFormOptions(accountId)
    if (!selectedId) return
    await voicemail.loadDetail(accountId, selectedId)
    const record = voicemail.detail
    if (!record) return
    Object.assign(form, {
      name: record.name ?? '',
      mailbox: record.mailbox ?? '',
      assigned_extension_id: record.assigned_extension?.id ?? null,
      timezone: record.timezone,
      notification_emails: record.notification_emails.join('\n'),
      transcribe: record.transcribe,
      require_pin: record.require_pin,
      pin: '',
    })
    Object.assign(configuration, hydrateVoicemailBoxConfiguration(record.configuration))
    callbackConfigured.value = record.configuration.notify_callback !== null
    Object.assign(
      notificationCallback,
      record.configuration.notify_callback ?? defaultVoicemailNotificationCallback(),
    )
    callbackSchedule.value = notificationCallback.schedule.join('\n')
  },
  { immediate: true },
)

function close(): void {
  void router.push(
    voicemailBoxId.value
      ? { name: 'voicemail-detail', params: { voicemailBoxId: voicemailBoxId.value } }
      : { name: 'voicemail' },
  )
}

async function save(): Promise<void> {
  if (!accounts.selectedId) return
  voicemail.mutationError = null
  const input = buildVoicemailBoxInput(
    form,
    configuration,
    callbackConfigured.value,
    notificationCallback,
    callbackSchedule.value,
  )
  const validation = validateForm(
    voicemailBoxFormSchemaFor(isEditing.value, pinConfigured.value),
    input,
  )

  if (!validation.success) {
    voicemail.fieldErrors = validation.errors

    return
  }

  const record = voicemailBoxId.value
    ? await voicemail.update(accounts.selectedId, voicemailBoxId.value, validation.data)
    : await voicemail.create(accounts.selectedId, validation.data)
  if (record) await router.push({ name: 'voicemail-detail', params: { voicemailBoxId: record.id } })
}
</script>

<template>
  <CrudSlideOver
    :title="title"
    :eyebrow="`GridPBX / Voicemail / ${title}`"
    description="Changes are written to Switch first and then projected into GridPBX."
    @close="close"
  >
    <div
      v-if="accounts.selected && !canManage"
      class="card-surface grid min-h-72 place-items-center p-8 text-center"
    >
      <div>
        <KeyIcon class="mx-auto size-10 text-slate-400" />
        <h2 class="mt-4 text-sm font-semibold text-slate-700">Read-only account access</h2>
        <p class="mt-2 text-xs text-heading-description">
          Your organization role can view voicemail boxes but cannot change Switch configuration.
        </p>
        <button
          type="button"
          class="mt-5 h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
          @click="close"
        >
          Return to voicemail
        </button>
      </div>
    </div>
    <div
      v-else-if="isEditing && voicemail.detailLoading"
      class="card-surface grid min-h-72 place-items-center text-xs text-slate-500"
    >
      Loading voicemail configuration…
    </div>
    <div
      v-else-if="isEditing && voicemail.detailError"
      class="card-surface p-8 text-center text-xs text-danger"
    >
      {{ voicemail.detailError }}
    </div>
    <form v-else class="grid gap-5" novalidate @submit.prevent="save">
      <FormErrorSummary
        :error="Object.keys(voicemail.fieldErrors).length === 0 ? voicemail.mutationError : null"
        :field-errors="voicemail.fieldErrors"
        :title="isEditing ? 'Unable to save the voicemail box' : 'Unable to create the voicemail box'"
      />

      <VoicemailBoxFormFields
        v-model:form="form"
        v-model:configuration="configuration"
        v-model:callback-configured="callbackConfigured"
        v-model:callback-schedule="callbackSchedule"
        v-model:notification-callback="notificationCallback"
        :field-errors="voicemail.fieldErrors"
        :options="voicemail.formOptions"
        :editing="isEditing"
        :pin-configured="pinConfigured"
      />

      <div class="slide-over-actions flex justify-end">
        <button
          type="submit"
          :disabled="voicemail.mutationLoading || !accounts.selectedId"
          class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
        >
          <CheckCircleIcon class="size-4" />{{
            voicemail.mutationLoading
              ? 'Saving…'
              : isEditing
                ? 'Save changes'
                : 'Create voicemail box'
          }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
