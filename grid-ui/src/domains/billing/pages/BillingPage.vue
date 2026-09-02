<script setup lang="ts">
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import { computed, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  ArrowsRightLeftIcon,
  BanknotesIcon,
  CheckCircleIcon,
  ChevronRightIcon,
  CreditCardIcon,
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
const activeSection = ref(0)

const billingSections = [
  { label: 'Overview', icon: BanknotesIcon },
  { label: 'Documents', icon: DocumentTextIcon },
  { label: 'Transactions', icon: ArrowsRightLeftIcon },
  { label: 'Reconciliation', icon: ShieldCheckIcon },
  { label: 'Payment testing', icon: CreditCardIcon },
] as const

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

function selectSection(index: number): void {
  activeSection.value = index
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
    activeSection.value = 0
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
        <button
          type="button"
          class="card-surface flex items-center gap-4 p-4 text-left transition hover:border-brand-200 hover:shadow-md"
          aria-label="Open reconciliation"
          @click="selectSection(3)"
        >
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
          <ChevronRightIcon class="ml-auto size-4 text-slate-400" />
        </button>
      </div>

      <button
        v-if="attentionChecks.length"
        type="button"
        class="mt-5 flex w-full items-center gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-left text-amber-900 transition hover:border-amber-300 hover:bg-amber-100/70"
        @click="selectSection(3)"
      >
        <ExclamationTriangleIcon class="size-5 shrink-0 text-amber-600" />
        <span class="min-w-0 flex-1">
          <span class="block text-xs font-semibold">
            {{ attentionChecks.length }} reconciliation
            {{ attentionChecks.length === 1 ? 'check requires' : 'checks require' }} attention
          </span>
          <span class="mt-0.5 block text-[10px] text-amber-800">
            Review safe recovery guidance before relying on the current projection.
          </span>
        </span>
        <span class="shrink-0 text-[10px] font-semibold">Review</span>
        <ChevronRightIcon class="size-4 shrink-0" />
      </button>

      <TabGroup :selected-index="activeSection" @change="selectSection">
        <div data-testid="billing-workspace-card" class="card-surface mt-5 overflow-hidden">
          <TabList
            aria-label="Billing workspace sections"
            class="flex gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50/70 p-1.5"
          >
            <Tab
              v-for="section in billingSections"
              :key="section.label"
              v-slot="{ selected }"
              as="template"
            >
              <button
                type="button"
                class="flex h-9 shrink-0 items-center gap-2 rounded-md px-3 text-xs font-semibold outline-none transition sm:px-4"
                :class="
                  selected
                    ? 'bg-white text-brand-700 shadow-sm ring-1 ring-slate-200'
                    : 'text-slate-500 hover:bg-white/70 hover:text-slate-700'
                "
              >
                <component :is="section.icon" class="size-4 shrink-0" />
                {{ section.label }}
              </button>
            </Tab>
          </TabList>

          <TabPanels class="bg-white">
            <TabPanel class="focus:outline-none">
              <section class="overflow-hidden">
                <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
                  <DocumentTextIcon class="size-5 text-brand-600" />
                  <div>
                    <h2 class="text-sm font-semibold text-slate-800">Billing sources</h2>
                    <p class="mt-0.5 text-[10px] text-slate-500">
                      Availability and authority are reported independently for each document type.
                    </p>
                  </div>
                </header>
                <div
                  class="grid divide-y divide-slate-200 md:grid-cols-2 md:divide-x md:divide-y-0"
                >
                  <article class="p-5">
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="text-xs font-semibold text-slate-800">Invoices</h3>
                      <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        :class="
                          overview.documents.invoices.available
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'bg-slate-100 text-slate-600'
                        "
                      >
                        {{ overview.documents.invoices.available ? 'Available' : 'Unavailable' }}
                      </span>
                      <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        :class="
                          overview.documents.invoices.authoritative
                            ? 'bg-brand-50 text-brand-700'
                            : 'bg-amber-50 text-amber-800'
                        "
                      >
                        {{
                          overview.documents.invoices.authoritative
                            ? 'Authoritative'
                            : 'Unconfirmed'
                        }}
                      </span>
                    </div>
                    <dl class="mt-4 grid gap-2 text-xs">
                      <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Source</dt>
                        <dd class="text-right font-semibold text-slate-800">
                          {{ label(overview.documents.invoices.source) }}
                        </dd>
                      </div>
                    </dl>
                    <p class="mt-3 text-[10px] leading-4 text-slate-500">
                      {{ overview.documents.invoices.guidance }}
                    </p>
                  </article>

                  <article class="p-5">
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="text-xs font-semibold text-slate-800">Receipts</h3>
                      <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        :class="
                          overview.documents.receipts.available
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'bg-slate-100 text-slate-600'
                        "
                      >
                        {{ overview.documents.receipts.available ? 'Available' : 'Unavailable' }}
                      </span>
                      <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        :class="
                          overview.documents.receipts.authoritative
                            ? 'bg-brand-50 text-brand-700'
                            : 'bg-amber-50 text-amber-800'
                        "
                      >
                        {{
                          overview.documents.receipts.authoritative
                            ? 'Authoritative'
                            : 'Unconfirmed'
                        }}
                      </span>
                    </div>
                    <dl class="mt-4 grid gap-2 text-xs">
                      <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Source</dt>
                        <dd class="text-right font-semibold text-slate-800">
                          {{ label(overview.documents.receipts.source) }}
                        </dd>
                      </div>
                    </dl>
                    <p class="mt-3 text-[10px] leading-4 text-slate-500">
                      {{ overview.documents.receipts.guidance }}
                    </p>
                  </article>
                </div>
              </section>
            </TabPanel>

            <TabPanel class="grid gap-5 bg-slate-50/50 p-4 focus:outline-none sm:p-5">
              <section class="card-surface overflow-hidden">
                <header class="flex items-start gap-3 border-b border-slate-200 p-5">
                  <DocumentTextIcon class="size-5 text-indigo-600" />
                  <div>
                    <h2 class="text-sm font-semibold text-slate-800">Invoices</h2>
                    <p class="mt-1 text-xs text-slate-500">
                      Authoritative summaries only. PDF download appears only after provider detail
                      passes the safe document contract.
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
                        <td class="px-5 py-3.5 text-slate-600">
                          {{ dateTime(invoice.issued_at) }}
                        </td>
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

              <section class="card-surface overflow-hidden">
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
            </TabPanel>

            <TabPanel class="focus:outline-none">
              <section class="overflow-hidden">
                <header class="border-b border-slate-200 p-5">
                  <h2 class="text-sm font-semibold text-slate-800">
                    Recent Switch billing activity
                  </h2>
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
            </TabPanel>

            <TabPanel class="focus:outline-none">
              <section class="overflow-hidden">
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
            </TabPanel>

            <TabPanel class="bg-slate-50/50 p-4 focus:outline-none sm:p-5">
              <SandboxPaymentPanel v-if="accounts.selectedId" :account-id="accounts.selectedId" />
            </TabPanel>
          </TabPanels>
        </div>
      </TabGroup>
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
