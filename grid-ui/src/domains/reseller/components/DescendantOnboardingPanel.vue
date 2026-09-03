<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { RadioGroup, RadioGroupLabel, RadioGroupOption } from '@headlessui/vue'
import {
  BuildingOffice2Icon,
  CheckCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormInput from '@/shared/components/FormInput.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { descendantOnboardingSchema } from '../schemas/descendantOnboardingSchema'
import type { DescendantOnboardingCandidates, DescendantOnboardingInput } from '../types/reseller'

const props = defineProps<{
  data: DescendantOnboardingCandidates | null
  loading: boolean
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{
  close: []
  retry: []
  save: [input: DescendantOnboardingInput]
}>()

const form = reactive({
  reference: '',
  confirmation: '',
  acknowledge_existing_access: false,
})
const localErrors = ref<FormErrors>({})
const errors = computed(() => ({ ...props.fieldErrors, ...localErrors.value }))
const selectedCandidate = computed(
  () => props.data?.candidates.find((candidate) => candidate.reference === form.reference) ?? null,
)

const fieldError = (field: string): string | null => errors.value[field]?.[0] ?? null
const dateTime = (value: string | null): string => {
  if (!value) return 'after the next refresh'

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function selectCandidate(reference: string): void {
  form.reference = reference
  form.confirmation = ''
  localErrors.value = {}
}

function submit(): void {
  const result = validateForm(descendantOnboardingSchema, form)
  localErrors.value = result.errors
  if (!result.success || !result.data.acknowledge_existing_access) return

  emit('save', {
    reference: result.data.reference,
    confirmation: result.data.confirmation,
    acknowledge_existing_access: true,
  })
}

watch(
  () => props.data,
  () => {
    form.reference = ''
    form.confirmation = ''
    form.acknowledge_existing_access = false
    localErrors.value = {}
  },
)
</script>

<template>
  <CrudSlideOver
    title="Onboard a descendant"
    eyebrow="GridPBX / Reseller administration"
    description="Project one existing Switch descendant into the current organization through a confirmed, audited workflow."
    width="medium"
    @close="emit('close')"
  >
    <div v-if="loading" class="card-surface p-12 text-center text-xs text-slate-600">
      Loading unresolved descendants…
    </div>

    <div v-else-if="error && !data" class="rounded-md border border-red-200 bg-red-50 p-5">
      <div class="flex gap-3 text-red-800">
        <ExclamationTriangleIcon class="size-5 shrink-0" />
        <div>
          <p class="text-sm font-semibold">Unable to load descendants</p>
          <p class="mt-1 text-xs leading-5">{{ error }}</p>
          <button
            type="button"
            class="mt-4 rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold"
            @click="emit('retry')"
          >
            Try again
          </button>
        </div>
      </div>
    </div>

    <div v-else-if="data && !data.candidates.length" class="card-surface p-12 text-center">
      <CheckCircleIcon class="mx-auto size-9 text-emerald-500" />
      <p class="mt-3 text-sm font-semibold text-slate-800">All descendants are projected</p>
      <p class="mt-1 text-xs leading-5 text-slate-600">
        No unmanaged Switch descendant is available for onboarding.
      </p>
    </div>

    <form v-else-if="data" class="grid gap-5" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to onboard the descendant"
      />

      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
          <BuildingOffice2Icon class="size-5 text-brand-500" />
          <div>
            <h2 class="text-sm font-semibold text-slate-800">Target organization</h2>
            <p class="mt-0.5 text-xs text-slate-600">{{ data.target_organization.name }}</p>
          </div>
        </header>
        <div class="p-5 text-xs leading-5 text-slate-700">
          This organization currently has
          <strong>{{ data.access_inheritance.member_count }}</strong>
          {{ data.access_inheritance.member_count === 1 ? 'member' : 'members' }}. Their existing
          organization roles will also apply to the onboarded account.
        </div>
      </article>

      <RadioGroup
        :model-value="form.reference"
        class="card-surface overflow-hidden"
        @update:model-value="selectCandidate"
      >
        <div class="border-b border-slate-200 px-5 py-4">
          <RadioGroupLabel class="text-sm font-semibold text-slate-800">
            Unresolved descendants
          </RadioGroupLabel>
          <p class="mt-1 text-xs text-slate-600">Select exactly one account to project.</p>
        </div>
        <div class="grid gap-2 p-4">
          <RadioGroupOption
            v-for="candidate in data.candidates"
            :key="candidate.reference"
            v-slot="{ checked }"
            :value="candidate.reference"
            :disabled="!candidate.eligible"
            as="template"
          >
            <button
              type="button"
              class="flex w-full items-start gap-3 rounded-md border p-4 text-left outline-none transition"
              :class="
                !candidate.eligible
                  ? 'cursor-not-allowed border-slate-200 bg-slate-100 opacity-65'
                  : checked
                    ? 'border-brand-400 bg-brand-50 ring-1 ring-brand-200'
                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
              "
            >
              <span
                class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border"
                :class="checked ? 'border-brand-500' : 'border-slate-300'"
              >
                <span v-if="checked" class="size-2.5 rounded-full bg-brand-500"></span>
              </span>
              <span class="min-w-0 flex-1">
                <span class="block truncate text-xs font-semibold text-slate-800">
                  {{ candidate.name }}
                </span>
                <span class="mt-1 block truncate text-[11px] text-slate-600">
                  {{ candidate.realm || 'No realm reported' }}
                </span>
                <span
                  v-if="candidate.descendants_count"
                  class="mt-1 block text-[10px] text-slate-500"
                >
                  Contains {{ candidate.descendants_count }} nested
                  {{ candidate.descendants_count === 1 ? 'descendant' : 'descendants' }}
                </span>
                <span
                  v-if="!candidate.eligible"
                  class="mt-1 block text-[10px] font-semibold text-amber-700"
                >
                  Onboard this account's parent first.
                </span>
              </span>
            </button>
          </RadioGroupOption>
          <p v-if="fieldError('reference')" class="text-[10px] text-danger">
            {{ fieldError('reference') }}
          </p>
        </div>
      </RadioGroup>

      <article v-if="selectedCandidate" class="card-surface grid gap-4 p-5">
        <div>
          <h2 class="text-sm font-semibold text-slate-800">Confirm account mapping</h2>
          <p class="mt-1 text-xs leading-5 text-slate-600">
            Enter <strong>{{ selectedCandidate.name }}</strong> exactly. GridPBX will project
            account metadata only; it will not promote, demote, or modify the Switch account.
          </p>
        </div>
        <FormInput
          v-model="form.confirmation"
          label="Descendant account name"
          autocomplete="off"
          :error="fieldError('confirmation')"
        />
        <FormCheckbox
          v-model="form.acknowledge_existing_access"
          label="I acknowledge inherited organization access"
          :description="`${data.access_inheritance.member_count} existing organization member${data.access_inheritance.member_count === 1 ? '' : 's'} will inherit access according to their current roles.`"
          :error="fieldError('acknowledge_existing_access')"
          variant="card"
        />
        <div
          class="flex items-center gap-2 rounded-md bg-slate-100 px-3 py-2 text-[10px] text-slate-600"
        >
          <ClockIcon class="size-4 shrink-0" />
          This opaque reference expires {{ dateTime(data.reference_expires_at) }}.
        </div>
      </article>

      <div class="slide-over-actions flex justify-end gap-2 py-4">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="saving || !selectedCandidate"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Onboarding…' : 'Onboard descendant' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
