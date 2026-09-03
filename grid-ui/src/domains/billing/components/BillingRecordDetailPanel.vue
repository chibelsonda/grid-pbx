<script setup lang="ts">
import { computed } from 'vue'
import {
  ArrowDownTrayIcon,
  BanknotesIcon,
  DocumentTextIcon,
  InformationCircleIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { BillingInvoiceDetail, BillingReceiptDetail, BillingRecord } from '../types/billing'

const props = withDefaults(
  defineProps<{
    record: BillingRecord
    invoiceDetail?: BillingInvoiceDetail | null
    invoiceLoading?: boolean
    invoiceDownloading?: boolean
    invoiceError?: string | null
    receiptDetail?: BillingReceiptDetail | null
    receiptLoading?: boolean
    receiptDownloading?: boolean
    receiptError?: string | null
  }>(),
  {
    invoiceDetail: null,
    invoiceLoading: false,
    invoiceDownloading: false,
    invoiceError: null,
    receiptDetail: null,
    receiptLoading: false,
    receiptDownloading: false,
    receiptError: null,
  },
)
defineEmits<{ close: []; download: [] }>()

const displayedInvoice = computed(() =>
  props.record.kind === 'invoice' ? (props.invoiceDetail ?? props.record.item) : null,
)
const displayedReceipt = computed(() =>
  props.record.kind === 'receipt' ? (props.receiptDetail ?? props.record.item) : null,
)

const amount = (value: string | null): string => {
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
const sourceLabel = (value: string): string =>
  value.toLowerCase().includes('mysql') ? 'GridPBX projection' : label(value)

const title =
  props.record.kind === 'invoice'
    ? props.record.item.number || 'Invoice summary'
    : props.record.kind === 'receipt'
      ? props.record.item.number || 'Receipt summary'
      : props.record.kind === 'payment_confirmation'
        ? `${label(props.record.item.operation)} confirmation`
        : props.record.item.description || 'Switch transaction'
</script>

<template>
  <CrudSlideOver
    :title="title"
    eyebrow="GridPBX / Billing"
    description="Read-only billing record details."
    width="medium"
    @close="$emit('close')"
  >
    <div class="grid gap-5">
      <article v-if="record.kind === 'invoice'" class="card-surface p-5">
        <div class="flex items-start gap-3">
          <span class="grid size-10 place-items-center rounded-md bg-indigo-50 text-indigo-600">
            <DocumentTextIcon class="size-5" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-800">Invoice summary</h2>
            <p class="mt-1 text-xs text-heading-description">
              {{ record.authoritative ? 'Authoritative summary' : 'Informational summary' }} from
              {{ sourceLabel(record.source) }}.
            </p>
          </div>
        </div>
        <dl class="mt-5 grid gap-3 text-xs">
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Status</dt>
            <dd class="font-semibold text-slate-800">{{ label(displayedInvoice!.status) }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Total</dt>
            <dd class="font-semibold text-slate-800">
              {{ displayedInvoice!.currency || 'Currency not reported' }}
              {{ amount(displayedInvoice!.total) }}
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Paid</dt>
            <dd class="font-semibold text-slate-800">
              {{ amount(displayedInvoice!.amount_paid) }}
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Amount due</dt>
            <dd class="font-semibold text-slate-800">{{ amount(displayedInvoice!.amount_due) }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Issued</dt>
            <dd class="font-semibold text-slate-800">
              {{ dateTime(displayedInvoice!.issued_at) }}
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Due</dt>
            <dd class="font-semibold text-slate-800">{{ dateTime(displayedInvoice!.due_at) }}</dd>
          </div>
        </dl>
        <p v-if="invoiceLoading" class="mt-5 text-xs text-slate-500">
          Loading the authoritative invoice detail…
        </p>
        <p
          v-else-if="invoiceError"
          class="mt-5 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"
        >
          {{ invoiceError }} The summary remains available in read-only mode.
        </p>
        <button
          v-else-if="invoiceDetail?.document.available"
          type="button"
          :disabled="invoiceDownloading"
          class="mt-5 inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white hover:bg-brand-600 disabled:opacity-50"
          @click="$emit('download')"
        >
          <ArrowDownTrayIcon class="size-4" />
          {{ invoiceDownloading ? 'Preparing download…' : 'Download invoice PDF' }}
        </button>
        <p
          v-else
          class="mt-5 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"
        >
          {{
            displayedInvoice?.document_available
              ? 'The provider reported a document, but it did not pass the safe download contract.'
              : 'No authoritative invoice document is available for download.'
          }}
        </p>
      </article>

      <article v-else-if="record.kind === 'receipt'" class="card-surface p-5">
        <div class="flex items-start gap-3">
          <span class="grid size-10 place-items-center rounded-md bg-violet-50 text-violet-600">
            <DocumentTextIcon class="size-5" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-800">Receipt summary</h2>
            <p class="mt-1 text-xs text-heading-description">
              {{ record.authoritative ? 'Authoritative receipt' : 'Informational receipt' }} from
              {{ sourceLabel(record.source) }}.
            </p>
          </div>
        </div>
        <dl class="mt-5 grid gap-3 text-xs">
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Status</dt>
            <dd class="font-semibold text-slate-800">{{ label(displayedReceipt!.status) }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Amount</dt>
            <dd class="font-semibold text-slate-800">
              {{ displayedReceipt!.currency || 'Currency not reported' }}
              {{ amount(displayedReceipt!.amount) }}
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Paid</dt>
            <dd class="font-semibold text-slate-800">
              {{ dateTime(displayedReceipt!.paid_at) }}
            </dd>
          </div>
        </dl>
        <p v-if="receiptLoading" class="mt-5 text-xs text-slate-500">
          Loading the authoritative receipt detail…
        </p>
        <p
          v-else-if="receiptError"
          class="mt-5 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"
        >
          {{ receiptError }} The summary remains available in read-only mode.
        </p>
        <button
          v-else-if="receiptDetail?.document.available"
          type="button"
          :disabled="receiptDownloading"
          class="mt-5 inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white hover:bg-brand-600 disabled:opacity-50"
          @click="$emit('download')"
        >
          <ArrowDownTrayIcon class="size-4" />
          {{ receiptDownloading ? 'Preparing download…' : 'Download receipt PDF' }}
        </button>
        <p
          v-else
          class="mt-5 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"
        >
          {{
            displayedReceipt?.document_available
              ? 'The provider reported a document, but it did not pass the safe download contract.'
              : 'No authoritative receipt document is available for download.'
          }}
        </p>
      </article>

      <article v-else-if="record.kind === 'payment_confirmation'" class="card-surface p-5">
        <div class="flex items-start gap-3">
          <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600">
            <BanknotesIcon class="size-5" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-800">Payment confirmation</h2>
            <p class="mt-1 text-xs text-heading-description">
              A successful GridPBX payment operation recorded through
              {{ label(record.item.provider) }}.
            </p>
          </div>
        </div>
        <dl class="mt-5 grid gap-3 text-xs">
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Operation</dt>
            <dd class="font-semibold text-slate-800">{{ label(record.item.operation) }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Amount</dt>
            <dd class="font-semibold text-slate-800">
              {{ record.item.currency || 'Currency not reported' }} {{ amount(record.item.amount) }}
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Completed</dt>
            <dd class="font-semibold text-slate-800">
              {{ dateTime(record.item.completed_at) }}
            </dd>
          </div>
        </dl>
        <p class="mt-5 rounded-md border border-sky-200 bg-sky-50 p-3 text-xs text-sky-800">
          This confirmation is not an invoice, tax document, or provider-issued receipt.
        </p>
      </article>

      <article v-else class="card-surface p-5">
        <div class="flex items-start gap-3">
          <span class="grid size-10 place-items-center rounded-md bg-sky-50 text-sky-600">
            <InformationCircleIcon class="size-5" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-800">Switch transaction projection</h2>
            <p class="mt-1 text-xs text-heading-description">
              Read-only operational activity reported by Switch.
            </p>
          </div>
        </div>
        <dl class="mt-5 grid gap-3 text-xs">
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Amount</dt>
            <dd class="font-semibold text-slate-800">{{ amount(record.item.amount) }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Type</dt>
            <dd class="font-semibold text-slate-800">
              {{ record.item.type ? label(record.item.type) : 'Not reported' }}
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Reason</dt>
            <dd class="font-semibold text-slate-800">
              {{ record.item.reason ? label(record.item.reason) : 'Not reported' }}
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Recorded</dt>
            <dd class="font-semibold text-slate-800">{{ dateTime(record.item.created_at) }}</dd>
          </div>
        </dl>
        <p class="mt-5 rounded-md border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
          Switch transactions are not presented as invoices or payment-provider receipts.
        </p>
      </article>

      <p class="font-mono text-[10px] text-slate-500">Public reference: {{ record.item.id }}</p>
    </div>
  </CrudSlideOver>
</template>
