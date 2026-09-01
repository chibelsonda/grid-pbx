<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  BanknotesIcon,
  CheckCircleIcon,
  ChevronRightIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon,
  ReceiptPercentIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import SandboxPaymentPanel from '@/domains/payments/components/SandboxPaymentPanel.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import BillingRecordDetailPanel from '../components/BillingRecordDetailPanel.vue'
import { useBillingStore } from '../stores/billingStore'
import type { BillingRecord } from '../types/billing'

const accounts = useAccountStore()
const billing = useBillingStore()
const selectedRecord = ref<BillingRecord | null>(null)

const canView = computed(() => accounts.selected?.permissions.can_view_services ?? false)
const overview = computed(() => billing.overview)
const invoices = computed(() => overview.value?.documents.invoices.items ?? [])
const receipts = computed(() => overview.value?.documents.receipts.items ?? [])
const confirmations = computed(() => overview.value?.documents.payment_confirmations.items ?? [])
const transactions = computed(() => overview.value?.billing?.transactions ?? [])
const attentionChecks = computed(
  () => overview.value?.reconciliation.checks.filter((check) => check.status !== 'passed') ?? [],
)
const amount = (value: number | string | null): string => {
  if (value === null) return 'Not reported'

  return new Intl.NumberFormat(undefined, { maximumFractionDigits: 8 }).format(Number(value))
}
const dateTime = (value: string | null): string =>
  value ? new Date(value).toLocaleString() : 'Not reported'
const label = (value: string): string =>
  value
    .replaceAll('-', ' ')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase())

function showInvoice(index: number): void {
  const item = invoices.value[index]
  const source = overview.value?.documents.invoices
  if (!item || !source) return

  selectedRecord.value = {
    kind: 'invoice',
    source: source.source,
    authoritative: source.authoritative,
    item,
  }
  if (accounts.selectedId) void billing.loadInvoice(accounts.selectedId, item.id)
}

function closeRecord(): void {
  selectedRecord.value = null
  billing.clearInvoice()
  billing.clearReceipt()
}

function downloadDocument(): void {
  if (!accounts.selectedId) return

  if (selectedRecord.value?.kind === 'invoice') {
    void billing.downloadInvoice(accounts.selectedId, selectedRecord.value.item.id)
  } else if (selectedRecord.value?.kind === 'receipt') {
    void billing.downloadReceipt(accounts.selectedId, selectedRecord.value.item.id)
  }
}

function showReceipt(index: number): void {
  const item = receipts.value[index]
  const source = overview.value?.documents.receipts
  if (!item || !source) return

  selectedRecord.value = {
    kind: 'receipt',
    source: source.source,
    authoritative: source.authoritative,
    item,
  }
  if (accounts.selectedId) void billing.loadReceipt(accounts.selectedId, item.id)
}

function showConfirmation(index: number): void {
  const item = confirmations.value[index]
  if (!item) return

  selectedRecord.value = {
    kind: 'payment_confirmation',
    source: 'gridpbx_payment_attempts',
    authoritative: false,
    item,
  }
}

function showTransaction(index: number): void {
  const item = transactions.value[index]
  if (!item) return

  selectedRecord.value = {
    kind: 'switch_transaction',
    source: 'switch_projection',
    authoritative: false,
    item,
  }
}

watch(
  [() => accounts.selectedId, canView],
  ([accountId, allowed]) => {
    billing.reset()
    selectedRecord.value = null
    if (accountId && allowed) void billing.load(accountId)
  },
  { immediate: true },
)
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex items-center gap-4">
      <div>
        <p class="mb-1 text-[11px] text-slate-500">GridPBX / Account</p>
        <h1 class="text-xl font-semibold text-slate-800">Billing</h1>
        <p class="mt-1 text-xs text-slate-500">
          Source-aware invoices, payment confirmations, and Switch billing activity.
        </p>
      </div>
      <div v-if="canView && accounts.selectedId" class="ml-auto flex flex-col items-end gap-1">
        <button
          type="button"
          :disabled="billing.loading"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 shadow-sm hover:border-brand-200 hover:text-brand-600 disabled:opacity-50"
          @click="billing.load(accounts.selectedId)"
        >
          <ArrowPathIcon class="size-4" :class="billing.loading && 'animate-spin'" />Refresh
        </button>
        <ProjectionFreshness
          v-if="overview"
          :last-synchronized-at="overview.last_synced_at"
          :status="overview.sync_status"
        />
      </div>
    </div>
  </section>

  <main class="page-container py-4 sm:py-6 lg:py-8">
    <div
      v-if="!canView"
      class="rounded-md border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800"
    >
      Billing information is available only to account, reseller, or platform administrators.
    </div>
    <div
      v-else-if="billing.error"
      class="rounded-md border border-red-200 bg-red-50 p-4 text-xs text-red-700"
    >
      {{ billing.error }}
    </div>
    <div v-else-if="billing.loading" class="card-surface p-14 text-center text-xs text-slate-500">
      Loading billing information…
    </div>
    <div v-else-if="!overview" class="card-surface p-14 text-center">
      <BanknotesIcon class="mx-auto size-9 text-slate-400" />
      <p class="mt-3 text-sm font-semibold text-slate-700">No billing projection yet</p>
      <p class="mt-1 text-xs text-slate-500">
        Synchronize Services & Limits to build the current read-only projection.
      </p>
    </div>

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600">
            <BanknotesIcon class="size-5" />
          </span>
          <div>
            <p class="text-lg font-semibold text-slate-800">
              {{ amount(overview.billing_impact.recurring_amount) }}
            </p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
              Recurring amount
            </p>
          </div>
        </article>
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-amber-50 text-amber-600">
            <ReceiptPercentIcon class="size-5" />
          </span>
          <div>
            <p class="text-lg font-semibold text-slate-800">
              {{ amount(overview.billing_impact.due_today) }}
            </p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
              Due today
            </p>
          </div>
        </article>
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-indigo-50 text-indigo-600">
            <DocumentTextIcon class="size-5" />
          </span>
          <div>
            <p class="text-lg font-semibold text-slate-800">{{ invoices.length }}</p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
              Invoice summaries
            </p>
          </div>
        </article>
        <article class="card-surface flex items-center gap-4 p-4">
          <span
            class="grid size-10 place-items-center rounded-md"
            :class="
              overview.reconciliation.status === 'healthy'
                ? 'bg-emerald-50 text-emerald-600'
                : 'bg-red-50 text-red-600'
            "
          >
            <ShieldCheckIcon class="size-5" />
          </span>
          <div>
            <p class="text-sm font-semibold text-slate-800">
              {{ label(overview.reconciliation.status) }}
            </p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
              Reconciliation
            </p>
          </div>
        </article>
      </div>

      <section class="mt-5 grid gap-4 lg:grid-cols-2">
        <article class="card-surface overflow-hidden">
          <header class="border-b border-slate-200 p-4">
            <h2 class="text-sm font-semibold text-slate-800">Invoice source</h2>
            <p class="mt-1 text-xs text-slate-500">
              {{ overview.documents.invoices.guidance }}
            </p>
          </header>
          <dl class="grid gap-3 p-4 text-xs">
            <div class="flex justify-between gap-4">
              <dt class="text-slate-500">Source</dt>
              <dd class="font-semibold text-slate-800">
                {{ label(overview.documents.invoices.source) }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-slate-500">Authority</dt>
              <dd class="font-semibold text-slate-800">
                {{ overview.documents.invoices.authoritative ? 'Confirmed' : 'Not confirmed' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-slate-500">Availability</dt>
              <dd class="font-semibold text-slate-800">
                {{ overview.documents.invoices.available ? 'Available' : 'Unavailable' }}
              </dd>
            </div>
          </dl>
        </article>
        <article class="card-surface overflow-hidden">
          <header class="border-b border-slate-200 p-4">
            <h2 class="text-sm font-semibold text-slate-800">Receipt source</h2>
            <p class="mt-1 text-xs text-slate-500">
              {{ overview.documents.receipts.guidance }}
            </p>
          </header>
          <dl class="grid gap-3 p-4 text-xs">
            <div class="flex justify-between gap-4">
              <dt class="text-slate-500">Source</dt>
              <dd class="font-semibold text-slate-800">
                {{ label(overview.documents.receipts.source) }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-slate-500">Authority</dt>
              <dd class="font-semibold text-slate-800">
                {{ overview.documents.receipts.authoritative ? 'Confirmed' : 'Not confirmed' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-slate-500">Availability</dt>
              <dd class="font-semibold text-slate-800">
                {{ overview.documents.receipts.available ? 'Available' : 'Unavailable' }}
              </dd>
            </div>
          </dl>
        </article>
      </section>

      <section class="card-surface mt-5 overflow-hidden">
        <header class="flex items-start gap-3 border-b border-slate-200 p-5">
          <DocumentTextIcon class="size-5 text-indigo-600" />
          <div>
            <h2 class="text-sm font-semibold text-slate-800">Invoices</h2>
            <p class="mt-1 text-xs text-slate-500">
              Authoritative summaries only. PDF download appears only after provider detail passes
              the safe document contract.
            </p>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[760px] text-left text-xs">
            <thead
              class="border-b border-slate-200 bg-slate-50 text-[10px] text-slate-500 uppercase"
            >
              <tr>
                <th class="px-5 py-3">Invoice</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Issued</th>
                <th class="px-5 py-3 text-right">Total</th>
                <th class="px-5 py-3 text-right">Due</th>
                <th class="w-12"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!invoices.length">
                <td colspan="6" class="px-5 py-10 text-center text-slate-500">
                  No authoritative invoice summaries are available.
                </td>
              </tr>
              <tr
                v-for="(invoice, index) in invoices"
                v-else
                :key="invoice.id"
                class="cursor-pointer hover:bg-slate-50"
                @click="showInvoice(index)"
              >
                <td class="px-5 py-3.5 font-semibold text-slate-800">
                  {{ invoice.number || 'Number not reported' }}
                </td>
                <td class="px-5 py-3.5 text-slate-600">{{ label(invoice.status) }}</td>
                <td class="px-5 py-3.5 text-slate-600">{{ dateTime(invoice.issued_at) }}</td>
                <td class="px-5 py-3.5 text-right font-semibold text-slate-800">
                  {{ invoice.currency || '' }} {{ amount(invoice.total) }}
                </td>
                <td class="px-5 py-3.5 text-right font-semibold text-slate-800">
                  {{ amount(invoice.amount_due) }}
                </td>
                <td class="px-3"><ChevronRightIcon class="size-4 text-slate-400" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="card-surface mt-5 overflow-hidden">
        <header class="flex items-start gap-3 border-b border-slate-200 p-5">
          <ReceiptPercentIcon class="size-5 text-violet-600" />
          <div>
            <h2 class="text-sm font-semibold text-slate-800">Receipts</h2>
            <p class="mt-1 text-xs text-slate-500">
              Provider-issued receipts only. GridPBX payment confirmations remain separate.
            </p>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[640px] text-left text-xs">
            <thead
              class="border-b border-slate-200 bg-slate-50 text-[10px] text-slate-500 uppercase"
            >
              <tr>
                <th class="px-5 py-3">Receipt</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Paid</th>
                <th class="px-5 py-3 text-right">Amount</th>
                <th class="w-12"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!receipts.length">
                <td colspan="5" class="px-5 py-10 text-center text-slate-500">
                  No authoritative provider receipts are available.
                </td>
              </tr>
              <tr
                v-for="(receipt, index) in receipts"
                v-else
                :key="receipt.id"
                class="cursor-pointer hover:bg-slate-50"
                @click="showReceipt(index)"
              >
                <td class="px-5 py-3.5 font-semibold text-slate-800">
                  {{ receipt.number || 'Number not reported' }}
                </td>
                <td class="px-5 py-3.5 text-slate-600">{{ label(receipt.status) }}</td>
                <td class="px-5 py-3.5 text-slate-600">{{ dateTime(receipt.paid_at) }}</td>
                <td class="px-5 py-3.5 text-right font-semibold text-slate-800">
                  {{ receipt.currency || '' }} {{ amount(receipt.amount) }}
                </td>
                <td class="px-3"><ChevronRightIcon class="size-4 text-slate-400" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="card-surface overflow-hidden">
          <header class="border-b border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-800">Payment confirmations</h2>
            <p class="mt-1 text-xs text-slate-500">
              {{ overview.documents.payment_confirmations.guidance }}
            </p>
          </header>
          <div v-if="confirmations.length" class="divide-y divide-slate-100">
            <button
              v-for="(confirmation, index) in confirmations"
              :key="confirmation.id"
              type="button"
              class="flex w-full items-center gap-3 px-5 py-4 text-left hover:bg-slate-50"
              @click="showConfirmation(index)"
            >
              <BanknotesIcon class="size-5 text-emerald-600" />
              <span class="min-w-0 flex-1">
                <span class="block text-xs font-semibold text-slate-800">
                  {{ label(confirmation.operation) }} confirmed
                </span>
                <span class="mt-0.5 block text-[10px] text-slate-500">
                  {{ dateTime(confirmation.completed_at) }}
                </span>
              </span>
              <span class="text-xs font-semibold text-slate-800">
                {{ confirmation.currency || '' }} {{ amount(confirmation.amount) }}
              </span>
              <ChevronRightIcon class="size-4 text-slate-400" />
            </button>
          </div>
          <p v-else class="p-8 text-center text-xs text-slate-500">
            No successful payment confirmations are stored.
          </p>
        </section>

        <section class="card-surface overflow-hidden">
          <header class="border-b border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-800">Reconciliation health</h2>
            <p class="mt-1 text-xs text-slate-500">
              {{ attentionChecks.length }} checks require attention.
            </p>
          </header>
          <div v-if="attentionChecks.length" class="divide-y divide-slate-100">
            <div v-for="check in attentionChecks" :key="check.code" class="flex gap-3 p-4">
              <ExclamationTriangleIcon
                class="mt-0.5 size-4 shrink-0"
                :class="check.status === 'failed' ? 'text-red-600' : 'text-amber-600'"
              />
              <div>
                <p class="text-xs font-semibold text-slate-800">{{ check.label }}</p>
                <p class="mt-1 text-[11px] leading-4 text-slate-600">{{ check.message }}</p>
                <p class="mt-1 text-[10px] leading-4 text-slate-500">
                  Recovery: {{ check.guidance }}
                </p>
              </div>
            </div>
          </div>
          <div v-else class="flex items-center gap-3 p-5 text-xs text-emerald-700">
            <CheckCircleIcon class="size-5" />All reconciliation checks passed.
          </div>
        </section>
      </div>

      <section class="card-surface mt-5 overflow-hidden">
        <header class="border-b border-slate-200 p-5">
          <h2 class="text-sm font-semibold text-slate-800">Recent Switch billing activity</h2>
          <p class="mt-1 text-xs text-slate-500">
            Operational transaction projection; not an invoice or provider receipt.
          </p>
        </header>
        <div v-if="transactions.length" class="divide-y divide-slate-100">
          <button
            v-for="(transaction, index) in transactions"
            :key="transaction.id"
            type="button"
            class="flex w-full items-center gap-4 px-5 py-4 text-left hover:bg-slate-50"
            @click="showTransaction(index)"
          >
            <span class="min-w-0 flex-1">
              <span class="block truncate text-xs font-semibold text-slate-800">
                {{ transaction.description || label(transaction.reason || 'Transaction') }}
              </span>
              <span class="mt-0.5 block text-[10px] text-slate-500">
                {{ transaction.type ? label(transaction.type) : 'Type not reported' }} ·
                {{ dateTime(transaction.created_at) }}
              </span>
            </span>
            <span class="text-xs font-semibold text-slate-800">
              {{ amount(transaction.amount) }}
            </span>
            <ChevronRightIcon class="size-4 text-slate-400" />
          </button>
        </div>
        <p v-else class="p-8 text-center text-xs text-slate-500">
          No Switch billing transactions were reported.
        </p>
      </section>

      <section v-if="accounts.selectedId" class="mt-5">
        <SandboxPaymentPanel :account-id="accounts.selectedId" />
      </section>
    </template>
  </main>

  <BillingRecordDetailPanel
    v-if="selectedRecord"
    :record="selectedRecord"
    :invoice-detail="billing.invoiceDetail"
    :invoice-loading="billing.invoiceLoading"
    :invoice-downloading="billing.invoiceDownloading"
    :invoice-error="billing.invoiceError"
    :receipt-detail="billing.receiptDetail"
    :receipt-loading="billing.receiptLoading"
    :receipt-downloading="billing.receiptDownloading"
    :receipt-error="billing.receiptError"
    @close="closeRecord"
    @download="downloadDocument"
  />
</template>
