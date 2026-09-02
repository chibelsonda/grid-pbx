<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ChevronDownIcon,
  ClockIcon,
  CreditCardIcon,
  ExclamationTriangleIcon,
  IdentificationIcon,
  LockClosedIcon,
  ShieldCheckIcon,
  UserPlusIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import FormInput from '@/shared/components/FormInput.vue'
import { validateForm } from '@/shared/forms/zod'
import { useAuthorizeNetAcceptUi } from '../composables/useAuthorizeNetAcceptUi'
import { createSandboxRefundFormSchema } from '../schemas/paymentSchema'
import { usePaymentStore } from '../stores/paymentStore'
import type { PaymentAttempt, PaymentAttemptEvent } from '../types/payment'

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
    payments.customerProfiles.length > 0 ||
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
const expandedAttemptId = ref<string | null>(null)
const confirmation = ref<'void' | 'refund' | 'profile' | null>(null)
const refundValidation = computed(() =>
  validateForm(createSandboxRefundFormSchema(refundLimitMinor.value), {
    amount_minor: refundAmountMinor.value,
  }),
)
const refundError = computed(() => refundValidation.value.errors.amount_minor?.[0] ?? null)
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
  await Promise.all([
    payments.loadCapability(accountId),
    payments.loadAttempts(accountId),
    payments.loadProfiles(accountId),
    payments.loadWebhookHealth(accountId),
  ])
  refundAmountMinor.value = Math.max(1, Math.min(100, refundLimitMinor.value || 100))
  await loadHostedForm()
}

const confirmOperation = async (): Promise<void> => {
  const sourceAttemptId = successfulCharge.value?.id
  if (!sourceAttemptId || !confirmation.value) return

  let succeeded = false
  if (confirmation.value === 'void') {
    succeeded = await payments.sandboxVoid(props.accountId, sourceAttemptId)
  } else if (confirmation.value === 'refund' && refundValidation.value.success) {
    succeeded = await payments.sandboxRefund(
      props.accountId,
      sourceAttemptId,
      refundValidation.value.data.amount_minor,
    )
  } else if (confirmation.value === 'profile') {
    succeeded = await payments.createSandboxCustomerProfile(props.accountId, sourceAttemptId)
  }

  if (succeeded) confirmation.value = null
}

const webhookStatusTone = (status: string): string => {
  if (status === 'processed') return 'bg-emerald-50 text-emerald-700'
  if (status === 'failed') return 'bg-red-50 text-red-700'
  if (status === 'retry_pending') return 'bg-amber-50 text-amber-800'

  return 'bg-slate-100 text-slate-600'
}

const webhookEventLabel = (eventType: string): string =>
  eventType
    .replace('net.authorize.payment.', '')
    .replace(/\.created$/, '')
    .replaceAll(/([a-z])([A-Z])/g, '$1 $2')
    .replaceAll('_', ' ')

const attemptStatusTone = (status: PaymentAttempt['status']): string => {
  if (status === 'succeeded') return 'bg-emerald-50 text-emerald-700'
  if (status === 'failed' || status === 'cancelled') return 'bg-red-50 text-red-700'
  if (status === 'indeterminate') return 'bg-amber-50 text-amber-800'

  return 'bg-slate-100 text-slate-600'
}

const attemptEvents = (attemptId: string): PaymentAttemptEvent[] =>
  payments.attemptDetails[attemptId]?.events ?? []

const toggleAttempt = async (attemptId: string): Promise<void> => {
  if (expandedAttemptId.value === attemptId) {
    expandedAttemptId.value = null

    return
  }

  expandedAttemptId.value = attemptId
  if (!payments.attemptDetails[attemptId]) {
    const loaded = await payments.loadAttempt(props.accountId, attemptId)
    if (!loaded) expandedAttemptId.value = null
  }
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
        <LockClosedIcon class="mt-0.5 size-5 shrink-0" />
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
          <div class="flex flex-wrap items-center justify-end gap-2">
            <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-[9px] text-slate-600">
              {{ successfulCharge?.id }}
            </span>
            <button
              type="button"
              class="inline-flex h-7 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 text-[10px] font-semibold text-slate-600 disabled:opacity-50"
              :disabled="payments.operating"
              @click="payments.loadAttempts(accountId)"
            >
              <ArrowPathIcon class="size-3.5" />
              Refresh status
            </button>
          </div>
        </div>

        <p
          v-if="successfulCharge?.provider_status"
          class="mt-3 text-[10px] leading-4 text-slate-500"
        >
          Provider state:
          <span class="font-semibold text-slate-700">
            {{ successfulCharge.provider_status.replaceAll('_', ' ') }}
          </span>
          <template v-if="successfulCharge.reconciled_at">
            · Reconciled {{ new Date(successfulCharge.reconciled_at).toLocaleString() }}
          </template>
        </p>

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

      <section class="mt-5 border-t border-slate-200 pt-5">
        <div class="flex items-start gap-2.5">
          <IdentificationIcon class="mt-0.5 size-5 shrink-0 text-brand-500" />
          <div>
            <h3 class="text-xs font-semibold text-slate-700">Saved payment profiles</h3>
            <p class="mt-1 text-[10px] leading-4 text-slate-500">
              Safe account-level inventory only. Provider profile identifiers remain encrypted and
              private.
            </p>
          </div>
        </div>

        <div
          v-if="payments.customerProfiles.length === 0"
          class="mt-3 rounded-md border border-dashed border-slate-200 p-3 text-[10px] text-slate-500"
        >
          No saved payment profiles are stored for this account.
        </div>

        <ul v-else class="mt-3 grid gap-2 sm:grid-cols-2">
          <li
            v-for="profile in payments.customerProfiles"
            :key="profile.id"
            class="rounded-md border border-slate-200 bg-slate-50/70 p-3"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="text-[11px] font-semibold text-slate-700">
                {{ profile.masked_account || 'Stored payment profile' }}
              </p>
              <span
                class="rounded px-1.5 py-0.5 text-[9px] font-semibold capitalize"
                :class="
                  profile.status === 'active'
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-slate-100 text-slate-600'
                "
              >
                {{ profile.status.replaceAll('_', ' ') }}
              </span>
            </div>
            <p class="mt-1 text-[10px] text-slate-500">
              {{ profile.account_type || 'Account details unavailable' }}
              <template v-if="profile.updated_at">
                · Updated {{ new Date(profile.updated_at).toLocaleString() }}
              </template>
            </p>
            <p class="mt-1 font-mono text-[9px] text-slate-500">{{ profile.id }}</p>
          </li>
        </ul>
      </section>

      <section v-if="payments.webhookHealth" class="mt-5 border-t border-slate-200 pt-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex min-w-0 items-start gap-2.5">
            <ShieldCheckIcon class="mt-0.5 size-5 shrink-0 text-brand-500" />
            <div>
              <h3 class="text-xs font-semibold text-slate-700">Webhook reconciliation health</h3>
              <p class="mt-1 text-[10px] leading-4 text-slate-500">
                Sanitized delivery state only. Provider references and signed payloads are never
                exposed.
              </p>
            </div>
          </div>
          <button
            type="button"
            class="inline-flex h-7 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 text-[10px] font-semibold text-slate-600 disabled:opacity-50"
            :disabled="payments.recoveringWebhookId !== null"
            @click="payments.loadWebhookHealth(accountId)"
          >
            <ArrowPathIcon class="size-3.5" />
            Refresh
          </button>
        </div>

        <div class="mt-3 grid grid-cols-3 gap-2">
          <div class="rounded-md border border-slate-200 bg-slate-50/70 p-2.5">
            <p class="text-sm font-semibold text-slate-700">
              {{ payments.webhookHealth.summary.total }}
            </p>
            <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">Received</p>
          </div>
          <div class="rounded-md border border-emerald-200 bg-emerald-50/70 p-2.5">
            <p class="text-sm font-semibold text-emerald-700">
              {{ payments.webhookHealth.summary.processed }}
            </p>
            <p class="text-[9px] font-semibold uppercase tracking-wide text-emerald-600">
              Reconciled
            </p>
          </div>
          <div
            class="rounded-md border p-2.5"
            :class="
              payments.webhookHealth.summary.requiring_attention > 0
                ? 'border-amber-200 bg-amber-50/70'
                : 'border-slate-200 bg-slate-50/70'
            "
          >
            <p class="text-sm font-semibold text-slate-700">
              {{ payments.webhookHealth.summary.requiring_attention }}
            </p>
            <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">Attention</p>
          </div>
        </div>

        <p
          v-if="!payments.webhookHealth.recovery_available"
          class="mt-3 rounded-md border border-slate-200 bg-slate-50 p-2.5 text-[10px] leading-4 text-slate-600"
        >
          Manual recovery is unavailable until sandbox provider status verification is configured.
        </p>

        <div
          v-if="payments.webhookHealth.deliveries.length === 0"
          class="mt-3 rounded-md border border-dashed border-slate-200 p-3 text-[10px] text-slate-500"
        >
          No account-linked webhook deliveries have been received.
        </div>

        <ul v-else class="mt-3 divide-y divide-slate-200 rounded-md border border-slate-200">
          <li
            v-for="delivery in payments.webhookHealth.deliveries"
            :key="delivery.id"
            class="flex flex-wrap items-center justify-between gap-3 p-3"
          >
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-[11px] font-semibold capitalize text-slate-700">
                  {{ webhookEventLabel(delivery.event_type) }}
                </p>
                <span
                  class="rounded px-1.5 py-0.5 text-[9px] font-semibold capitalize"
                  :class="webhookStatusTone(delivery.status)"
                >
                  {{ delivery.status.replaceAll('_', ' ') }}
                </span>
              </div>
              <p class="mt-1 text-[10px] leading-4 text-slate-500">
                {{ delivery.recovery_guidance }}
              </p>
              <p class="mt-1 font-mono text-[9px] text-slate-500">Delivery {{ delivery.id }}</p>
            </div>
            <button
              v-if="delivery.can_retry"
              type="button"
              class="inline-flex h-8 items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2.5 text-[10px] font-semibold text-amber-800 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="
                !payments.webhookHealth.recovery_available ||
                payments.recoveringWebhookId === delivery.id
              "
              @click="payments.retryWebhook(accountId, delivery.id)"
            >
              <ArrowPathIcon
                class="size-3.5"
                :class="payments.recoveringWebhookId === delivery.id ? 'animate-spin' : ''"
              />
              {{ payments.recoveringWebhookId === delivery.id ? 'Retrying…' : 'Retry' }}
            </button>
          </li>
        </ul>
      </section>

      <section class="mt-5 border-t border-slate-200 pt-5">
        <div class="flex items-start gap-2.5">
          <ClockIcon class="mt-0.5 size-5 shrink-0 text-brand-500" />
          <div>
            <h3 class="text-xs font-semibold text-slate-700">Recent payment activity</h3>
            <p class="mt-1 text-[10px] leading-4 text-slate-500">
              Account-scoped, immutable state transitions without provider references or raw gateway
              data.
            </p>
          </div>
        </div>

        <div
          v-if="payments.attempts.length === 0"
          class="mt-3 rounded-md border border-dashed border-slate-200 p-3 text-[10px] text-slate-500"
        >
          No payment attempts are stored for this account.
        </div>

        <ul v-else class="mt-3 divide-y divide-slate-200 rounded-md border border-slate-200">
          <li v-for="attempt in payments.attempts.slice(0, 10)" :key="attempt.id">
            <button
              type="button"
              class="flex w-full items-center justify-between gap-3 p-3 text-left hover:bg-slate-50"
              :aria-expanded="expandedAttemptId === attempt.id"
              @click="toggleAttempt(attempt.id)"
            >
              <span class="min-w-0">
                <span class="flex flex-wrap items-center gap-2">
                  <span class="text-[11px] font-semibold capitalize text-slate-700">
                    {{ attempt.operation.replaceAll('_', ' ') }}
                  </span>
                  <span
                    class="rounded px-1.5 py-0.5 text-[9px] font-semibold capitalize"
                    :class="attemptStatusTone(attempt.status)"
                  >
                    {{ attempt.status.replaceAll('_', ' ') }}
                  </span>
                </span>
                <span class="mt-1 block font-mono text-[9px] text-slate-500">
                  {{ attempt.id }}
                </span>
              </span>
              <ChevronDownIcon
                class="size-4 shrink-0 text-slate-400 transition-transform"
                :class="expandedAttemptId === attempt.id ? 'rotate-180' : ''"
              />
            </button>

            <div
              v-if="expandedAttemptId === attempt.id"
              class="border-t border-slate-200 bg-slate-50/70 p-3"
            >
              <p v-if="payments.loadingAttemptId === attempt.id" class="text-[10px] text-slate-500">
                Loading timeline…
              </p>
              <ol v-else-if="attemptEvents(attempt.id).length" class="space-y-3">
                <li v-for="event in attemptEvents(attempt.id)" :key="event.id" class="flex gap-2.5">
                  <span class="mt-1 size-2 shrink-0 rounded-full bg-brand-400" />
                  <div class="min-w-0">
                    <p class="text-[10px] font-semibold text-slate-700">{{ event.summary }}</p>
                    <p class="mt-0.5 text-[9px] text-slate-500">
                      {{ event.event_type.replaceAll('_', ' ') }}
                      <template v-if="event.provider_status">
                        · {{ event.provider_status.replaceAll('_', ' ') }}
                      </template>
                      <template v-if="event.created_at">
                        · {{ new Date(event.created_at).toLocaleString() }}
                      </template>
                    </p>
                    <p v-if="event.safe_error_code" class="mt-0.5 text-[9px] text-amber-700">
                      {{ event.safe_error_code.replaceAll('_', ' ') }}
                    </p>
                  </div>
                </li>
              </ol>
              <p v-else class="text-[10px] text-slate-500">
                No immutable state transitions are recorded for this attempt.
              </p>
            </div>
          </li>
        </ul>
      </section>

      <div
        v-if="hostedFormError || payments.error"
        class="mt-4 flex gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-red-700"
      >
        <ExclamationTriangleIcon class="mt-0.5 size-5 shrink-0" />
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
        <CheckCircleIcon class="mt-0.5 size-5 shrink-0" />
        <div>
          <p class="text-xs font-semibold">
            Payment attempt {{ payments.latestAttempt.status.replaceAll('_', ' ') }}
          </p>
          <p class="mt-1 font-mono text-[9px]">Reference {{ payments.latestAttempt.id }}</p>
          <p v-if="payments.latestAttempt.provider_status" class="mt-1 text-[10px]">
            Provider state: {{ payments.latestAttempt.provider_status.replaceAll('_', ' ') }}
          </p>
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
