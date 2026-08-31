<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  CreditCardIcon,
  ExclamationTriangleIcon,
  LockClosedIcon,
  UserPlusIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import FormInput from '@/shared/components/FormInput.vue'
import { useAuthorizeNetAcceptUi } from '../composables/useAuthorizeNetAcceptUi'
import { usePaymentStore } from '../stores/paymentStore'
import type { PaymentAttempt } from '../types/payment'

const props = defineProps<{ accountId: string }>()
const payments = usePaymentStore()
const amountMinor = computed(() =>
  Math.min(100, payments.capability?.client.sandbox_max_charge_minor ?? 100),
)
const formattedAmount = computed(() =>
  new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(
    amountMinor.value / 100,
  ),
)
const chargeAvailable = computed(
  () =>
    payments.capability?.client.available === true && payments.capability.mutations.charge === true,
)
const successfulCharge = computed<PaymentAttempt | null>(
  () =>
    payments.attempts.find(
      (attempt) => attempt.operation === 'charge' && attempt.status === 'succeeded',
    ) ?? null,
)
const successfulChildren = computed(() =>
  payments.attempts.filter(
    (attempt) =>
      attempt.source_attempt_id === successfulCharge.value?.id && attempt.status === 'succeeded',
  ),
)
const isVoided = computed(() =>
  successfulChildren.value.some((attempt) => attempt.operation === 'void'),
)
const hasCustomerProfile = computed(
  () =>
    payments.customerProfile !== null ||
    successfulChildren.value.some((attempt) => attempt.operation === 'attach_payment_method'),
)
const chargedMinor = computed(() =>
  successfulCharge.value?.amount ? Math.round(Number(successfulCharge.value.amount) * 100) : 0,
)
const refundedMinor = computed(() =>
  successfulChildren.value
    .filter((attempt) => attempt.operation === 'refund' && attempt.amount !== null)
    .reduce((sum, attempt) => sum + Math.round(Number(attempt.amount) * 100), 0),
)
const refundableMinor = computed(() => Math.max(0, chargedMinor.value - refundedMinor.value))
const refundLimitMinor = computed(() =>
  Math.min(
    refundableMinor.value,
    payments.capability?.client.sandbox_max_refund_minor ?? refundableMinor.value,
  ),
)
const operationsAvailable = computed(
  () =>
    successfulCharge.value !== null &&
    !isVoided.value &&
    (payments.capability?.mutations.void === true ||
      payments.capability?.mutations.refund === true ||
      payments.capability?.mutations.attach_payment_method === true),
)
const refundAmountMinor = ref<number>(100)
const confirmation = ref<'void' | 'refund' | 'profile' | null>(null)
const refundError = computed(() => {
  if (!Number.isInteger(refundAmountMinor.value) || refundAmountMinor.value < 1) {
    return 'Enter a whole amount of at least 1 cent.'
  }
  if (refundAmountMinor.value > refundLimitMinor.value) {
    return `The maximum refundable amount is ${formatMinor(refundLimitMinor.value)}.`
  }

  return null
})
const confirmationDetails = computed(() => {
  if (confirmation.value === 'void') {
    return {
      title: 'Void sandbox transaction?',
      description:
        'This requests cancellation of the unsettled Authorize.Net sandbox transaction. It cannot be undone.',
      label: 'Void transaction',
      text: 'VOID',
      tone: 'danger' as const,
    }
  }
  if (confirmation.value === 'refund') {
    return {
      title: 'Refund sandbox transaction?',
      description: `This submits a ${formatMinor(refundAmountMinor.value)} sandbox refund against the selected charge.`,
      label: 'Submit refund',
      text: 'REFUND',
      tone: 'warning' as const,
    }
  }

  return {
    title: 'Save sandbox payment profile?',
    description:
      'Authorize.Net will create a customer payment profile from this sandbox transaction. GridPBX never receives the raw card number or CVV.',
    label: 'Save payment profile',
    text: 'SAVE',
    tone: 'primary' as const,
  }
})

function formatMinor(value: number): string {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(
    value / 100,
  )
}
const {
  ready,
  error: hostedFormError,
  load,
  handlerName,
} = useAuthorizeNetAcceptUi(async (opaqueData) => {
  await payments.sandboxCharge(props.accountId, amountMinor.value, opaqueData)
})

const loadHostedForm = async (): Promise<void> => {
  // AcceptUI scans for its configured button when the script loads.
  await nextTick()
  const url = payments.capability?.client.accept_ui_url
  if (chargeAvailable.value && url) load(url)
}

const loadAccount = async (accountId: string): Promise<void> => {
  payments.reset()
  await Promise.all([payments.loadCapability(accountId), payments.loadAttempts(accountId)])
  refundAmountMinor.value = Math.max(1, Math.min(100, refundLimitMinor.value || 100))
  await loadHostedForm()
}

const confirmOperation = async (): Promise<void> => {
  const sourceAttemptId = successfulCharge.value?.id
  if (!sourceAttemptId || !confirmation.value) return

  let succeeded = false
  if (confirmation.value === 'void') {
    succeeded = await payments.sandboxVoid(props.accountId, sourceAttemptId)
  } else if (confirmation.value === 'refund' && !refundError.value) {
    succeeded = await payments.sandboxRefund(
      props.accountId,
      sourceAttemptId,
      refundAmountMinor.value,
    )
  } else if (confirmation.value === 'profile') {
    succeeded = await payments.createSandboxCustomerProfile(props.accountId, sourceAttemptId)
  }

  if (succeeded) confirmation.value = null
}

onMounted(() => loadAccount(props.accountId))

watch(
  () => props.accountId,
  (accountId) => loadAccount(accountId),
)
</script>

<template>
  <article class="card-surface overflow-hidden">
    <div class="flex items-start gap-3 border-b border-slate-200 p-5">
      <span class="grid size-10 shrink-0 place-items-center rounded-md bg-brand-50 text-brand-600">
        <CreditCardIcon class="size-5" />
      </span>
      <div class="min-w-0 flex-1">
        <h2 class="text-sm font-semibold text-slate-700">Sandbox payment verification</h2>
        <p class="mt-1 text-[10px] leading-4 text-slate-500">
          Authorize.Net hosts the card fields. GridPBX receives only a one-time opaque token.
        </p>
      </div>
    </div>

    <div class="p-5">
      <p v-if="payments.loading" class="text-xs text-slate-500">
        Checking the sandbox payment capability…
      </p>

      <div
        v-else-if="!chargeAvailable"
        class="flex gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-800"
      >
        <LockClosedIcon class="mt-0.5 size-4 shrink-0" />
        <div>
          <p class="text-xs font-semibold">Sandbox charging is disabled</p>
          <p class="mt-1 text-[10px] leading-4">
            An administrator must explicitly enable both payment-mutation safety flags. Production
            payment mutations remain unavailable.
          </p>
        </div>
      </div>

      <template v-else>
        <div class="rounded-md border border-slate-200 bg-slate-50/70 p-3">
          <p class="text-xs font-semibold text-slate-700">
            Secure {{ formattedAmount }} sandbox charge
          </p>
          <p class="mt-1 text-[10px] leading-4 text-slate-500">
            Clicking below opens Authorize.Net’s hosted form. Submitting that form creates one
            idempotent sandbox charge; no card number or CVV is sent to GridPBX.
          </p>
        </div>

        <button
          type="button"
          class="AcceptUI mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!ready || payments.charging"
          :data-billingAddressOptions="JSON.stringify({ show: true, required: false })"
          :data-apiLoginID="payments.capability?.client.api_login_id"
          :data-clientKey="payments.capability?.client.public_client_key"
          data-acceptUIFormBtnTxt="Submit sandbox payment"
          data-acceptUIFormHeaderTxt="Sandbox card information"
          :data-paymentOptions="JSON.stringify({ showCreditCard: true, showBankAccount: false })"
          :data-responseHandler="handlerName"
        >
          <LockClosedIcon class="size-4" />
          {{ payments.charging ? 'Confirming…' : ready ? 'Open secure payment form' : 'Loading…' }}
        </button>
      </template>

      <section v-if="operationsAvailable" class="mt-5 border-t border-slate-200 pt-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="text-xs font-semibold text-slate-700">Sandbox transaction controls</h3>
            <p class="mt-1 text-[10px] leading-4 text-slate-500">
              Uses the stored GridPBX attempt reference. Provider transaction identifiers and card
              data are never accepted from the browser.
            </p>
          </div>
          <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-[9px] text-slate-600">
            {{ successfulCharge?.id }}
          </span>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <button
            v-if="payments.capability?.mutations.void"
            type="button"
            class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 disabled:opacity-50"
            :disabled="payments.operating"
            @click="confirmation = 'void'"
          >
            <XCircleIcon class="size-4" />
            Void unsettled charge
          </button>

          <button
            v-if="payments.capability?.mutations.attach_payment_method && !hasCustomerProfile"
            type="button"
            class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-brand-200 bg-brand-50 px-3 text-xs font-semibold text-brand-700 disabled:opacity-50"
            :disabled="payments.operating"
            @click="confirmation = 'profile'"
          >
            <UserPlusIcon class="size-4" />
            Save payment profile
          </button>
        </div>

        <div
          v-if="payments.capability?.mutations.refund && refundLimitMinor > 0"
          class="mt-3 rounded-md border border-slate-200 bg-slate-50/70 p-3"
        >
          <FormInput
            v-model.number="refundAmountMinor"
            type="number"
            label="Refund amount (cents)"
            :description="`Available: ${formatMinor(refundableMinor)} · Safety limit: ${formatMinor(refundLimitMinor)}`"
            :error="refundError"
            min="1"
            :max="refundLimitMinor"
            step="1"
          />
          <button
            type="button"
            class="mt-3 inline-flex h-9 items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-800 disabled:opacity-50"
            :disabled="payments.operating || Boolean(refundError)"
            @click="confirmation = 'refund'"
          >
            <ArrowPathIcon class="size-4" />
            Refund {{ formatMinor(refundAmountMinor) }}
          </button>
        </div>
      </section>

      <div
        v-if="hostedFormError || payments.error"
        class="mt-4 flex gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-red-700"
      >
        <ExclamationTriangleIcon class="mt-0.5 size-4 shrink-0" />
        <p class="text-[11px] leading-4">{{ hostedFormError || payments.error }}</p>
      </div>

      <div
        v-if="payments.latestAttempt"
        class="mt-4 flex gap-2 rounded-md border p-3"
        :class="
          payments.latestAttempt.status === 'succeeded'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : 'border-amber-200 bg-amber-50 text-amber-800'
        "
      >
        <CheckCircleIcon class="mt-0.5 size-4 shrink-0" />
        <div>
          <p class="text-xs font-semibold">
            Payment attempt {{ payments.latestAttempt.status.replaceAll('_', ' ') }}
          </p>
          <p class="mt-1 font-mono text-[9px]">Reference {{ payments.latestAttempt.id }}</p>
        </div>
      </div>
    </div>
  </article>

  <ConfirmDialog
    :open="confirmation !== null"
    :title="confirmationDetails.title"
    :description="confirmationDetails.description"
    :confirm-label="confirmationDetails.label"
    :confirmation-text="confirmationDetails.text"
    :tone="confirmationDetails.tone"
    :busy="payments.operating"
    :disabled="confirmation === 'refund' && Boolean(refundError)"
    @close="confirmation = null"
    @confirm="confirmOperation"
  />
</template>
