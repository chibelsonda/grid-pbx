<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  ArrowPathRoundedSquareIcon,
  BoltIcon,
  ChevronRightIcon,
  PhoneArrowDownLeftIcon,
  QueueListIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import SearchInput from '@/shared/components/SearchInput.vue'
import CallflowDetailPanel from '../components/CallflowDetailPanel.vue'
import CallflowEditorPanel from '../components/CallflowEditorPanel.vue'
import CallflowInlineNodeEditorPanel from '../components/CallflowInlineNodeEditorPanel.vue'
import CallflowNodeEditorPanel from '../components/CallflowNodeEditorPanel.vue'
import { isGuidedInlineCallflowModule } from '../catalog/callflowActionCatalog'
import { useCallflowStore } from '../stores/callflowStore'
import type {
  CallflowNodeEditorContext,
  CallflowTreeMoveInput,
  CallflowTreeReorderInput,
  CallflowTreeNodeCreateInput,
  CallflowTreeNodeUpdateInput,
  CallflowInlineNodeCreateInput,
  CallflowInlineNodeUpdateInput,
  CallflowUpdate,
} from '../types/callRouting'

const accounts = useAccountStore()
const callflows = useCallflowStore()
const nodeEditorContext = ref<CallflowNodeEditorContext | null>(null)
const nodesOnPage = computed(() =>
  callflows.records.reduce((total, route) => total + route.node_count, 0),
)
const numberRoutesOnPage = computed(
  () => callflows.records.filter((route) => route.route_type === 'phone_number').length,
)
const featureCodesOnPage = computed(
  () => callflows.records.filter((route) => route.route_type === 'feature_code').length,
)
const availableModules = computed(() =>
  [...new Set(callflows.records.flatMap((route) => route.modules))].sort(),
)
const workspaceOpen = computed(
  () =>
    !callflows.editorOpen &&
    (callflows.detailLoading || callflows.detail !== null || callflows.detailError !== null),
)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const freshnessLabel = computed(() =>
  callflows.sync.last_successful_at
    ? `PBX projection synchronized ${new Date(callflows.sync.last_successful_at).toLocaleString()}`
    : 'PBX projection not synchronized yet',
)

watch(
  () => accounts.selectedId,
  (accountId) => {
    nodeEditorContext.value = null
    callflows.reset()
    if (accountId) void callflows.load(accountId, 1)
  },
  { immediate: true },
)

function applyFilters(): void {
  if (accounts.selectedId) void callflows.load(accounts.selectedId, 1)
}

function synchronize(): void {
  if (accounts.selectedId) void callflows.synchronize(accounts.selectedId)
}

function openDetail(id: string): void {
  if (accounts.selectedId) void callflows.loadDetail(accounts.selectedId, id)
}

function openEditor(): void {
  if (accounts.selectedId && callflows.detail) {
    void callflows.openEditor(accounts.selectedId, callflows.detail.id)
  }
}

function openCreateEditor(): void {
  if (accounts.selectedId) void callflows.openCreateEditor(accounts.selectedId)
}

function saveRoute(input: CallflowUpdate): void {
  if (!accounts.selectedId) return

  if (callflows.detail) void callflows.update(accounts.selectedId, callflows.detail.id, input)
  else void callflows.create(accounts.selectedId, input)
}

function moveTreeNode(input: CallflowTreeMoveInput): void {
  if (accounts.selectedId && callflows.detail) {
    void callflows.moveTreeNode(accounts.selectedId, callflows.detail.id, input)
  }
}

function reorderTreeNodes(input: CallflowTreeReorderInput): void {
  if (accounts.selectedId && callflows.detail) {
    void callflows.reorderTreeNodes(accounts.selectedId, callflows.detail.id, input)
  }
}

function openNodeEditor(context: CallflowNodeEditorContext): void {
  if (!accounts.selectedId || !callflows.detail) return
  nodeEditorContext.value = context
  if (isGuidedInlineCallflowModule(context.module) && context.module !== 'missed_call_alert') {
    callflows.closeTreeEditor()
  } else {
    void callflows.loadTreeEditor(accounts.selectedId, callflows.detail.id)
  }
}

function closeNodeEditor(): void {
  nodeEditorContext.value = null
  callflows.closeTreeEditor()
}

async function saveTreeNode(
  input:
    | CallflowTreeNodeCreateInput
    | CallflowTreeNodeUpdateInput
    | CallflowInlineNodeCreateInput
    | CallflowInlineNodeUpdateInput,
): Promise<void> {
  if (!accounts.selectedId || !callflows.detail || !nodeEditorContext.value) return

  const inline = 'module' in input && 'data' in input
  const updated = inline
    ? nodeEditorContext.value.operation === 'create' && 'parent_path' in input
      ? await callflows.createInlineTreeNode(accounts.selectedId, callflows.detail.id, input)
      : nodeEditorContext.value.operation === 'update' && 'node_path' in input
        ? await callflows.updateInlineTreeNode(accounts.selectedId, callflows.detail.id, input)
        : null
    : nodeEditorContext.value.operation === 'create' && 'parent_path' in input
      ? await callflows.createTreeNode(accounts.selectedId, callflows.detail.id, input)
      : nodeEditorContext.value.operation === 'update' && 'node_path' in input
        ? await callflows.updateTreeNode(accounts.selectedId, callflows.detail.id, input)
        : null

  if (updated) nodeEditorContext.value = null
}

function deleteRoute(): void {
  if (accounts.selectedId && callflows.detail) {
    void callflows.destroy(accounts.selectedId, callflows.detail.id)
  }
}

function humanize(value: string | null): string {
  return value
    ? value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
    : 'Unknown'
}

function routeTitle(route: {
  name: string | null
  feature_code: { name: string | null } | null
  numbers: string[]
}): string {
  return route.name ?? route.feature_code?.name ?? route.numbers[0] ?? 'Unnamed route'
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="flex w-full flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Call Routing</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">Call Routing</h1>
        <p class="mt-1 text-xs text-slate-500">
          Understand incoming entry points and the safe structural path each call follows.
        </p>
      </div>
      <div class="flex gap-3 sm:ml-auto">
        <button
          v-if="canManage"
          type="button"
          :disabled="!accounts.selectedId"
          class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
          @click="openCreateEditor"
        >
          <PlusIcon class="size-4" /> Create route
        </button>
        <button
          type="button"
          :disabled="!accounts.selectedId || callflows.synchronizing"
          class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50 disabled:opacity-50"
          @click="synchronize"
        >
          <ArrowPathIcon class="size-4" :class="callflows.synchronizing && 'animate-spin'" />
          {{ callflows.synchronizing ? 'Synchronizing…' : 'Synchronize routing' }}
        </button>
      </div>
    </div>
  </section>

  <div
    :class="
      workspaceOpen
        ? 'w-full px-4 py-4 sm:px-6 sm:py-5'
        : 'mx-auto w-full max-w-[1500px] p-4 sm:p-6 lg:p-8'
    "
  >
    <CallflowDetailPanel
      v-if="workspaceOpen"
      :record="callflows.detail"
      :loading="callflows.detailLoading"
      :error="callflows.detailError"
      :can-manage="canManage"
      :deleting="callflows.deleting"
      :mutation-error="callflows.mutationError"
      :tree-moving="callflows.treeMoving"
      :tree-mutation-error="callflows.treeMutationError"
      @close="callflows.closeDetail"
      @edit="openEditor"
      @delete="deleteRoute"
      @move-node="moveTreeNode"
      @reorder-nodes="reorderTreeNodes"
      @create-node="openNodeEditor"
      @edit-node="openNodeEditor"
    />

    <template v-else>
      <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
            ><ArrowPathRoundedSquareIcon class="size-5"
          /></span>
          <div>
            <p class="text-lg font-semibold text-slate-700">{{ callflows.total }}</p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Projected routes
            </p>
          </div>
        </article>
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-blue-50 text-blue-600"
            ><QueueListIcon class="size-5"
          /></span>
          <div>
            <p class="text-lg font-semibold text-slate-700">{{ nodesOnPage }}</p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Nodes on page
            </p>
          </div>
        </article>
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600"
            ><PhoneArrowDownLeftIcon class="size-5"
          /></span>
          <div>
            <p class="text-lg font-semibold text-slate-700">{{ numberRoutesOnPage }}</p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Number routes
            </p>
          </div>
        </article>
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-amber-50 text-amber-600"
            ><BoltIcon class="size-5"
          /></span>
          <div>
            <p class="text-lg font-semibold text-slate-700">{{ featureCodesOnPage }}</p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Feature codes
            </p>
          </div>
        </article>
      </div>

      <form
        class="mb-4 grid gap-3 lg:grid-cols-[minmax(240px,1fr)_190px_190px_auto]"
        @submit.prevent="applyFilters"
      >
        <SearchInput
          v-model="callflows.filters.search"
          label="Search call routes"
          placeholder="Search route, number, pattern, feature code…"
          input-class="h-10 bg-white text-xs shadow-sm"
        />
        <FormSelect
          v-model="callflows.filters.type"
          aria-label="Route type"
          class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 shadow-sm outline-none"
        >
          <option value="">All route types</option>
          <option value="phone_number">Phone number</option>
          <option value="extension">Extension</option>
          <option value="feature_code">Feature code</option>
          <option value="pattern">Pattern</option>
          <option value="unassigned">Unassigned</option>
        </FormSelect>
        <FormSelect
          v-model="callflows.filters.module"
          aria-label="Route module"
          class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 shadow-sm outline-none"
        >
          <option value="">All modules</option>
          <option v-for="module in availableModules" :key="module" :value="module">
            {{ humanize(module) }}
          </option>
        </FormSelect>
        <button
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50"
        >
          Apply filters
        </button>
      </form>

      <div
        v-if="callflows.error"
        class="mb-4 rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ callflows.error }}
      </div>
      <div class="mb-4 flex justify-end">
        <span
          class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-500"
          >{{ freshnessLabel }}</span
        >
      </div>

      <div class="card-surface overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[900px] text-left">
            <thead
              class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
            >
              <tr>
                <th class="px-5 py-3.5">Route</th>
                <th class="px-5 py-3.5">Entry points</th>
                <th class="px-5 py-3.5">Type</th>
                <th class="px-5 py-3.5">Path</th>
                <th class="px-5 py-3.5">Assignment</th>
                <th class="w-12 px-5 py-3.5"><span class="sr-only">View</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr v-if="callflows.loading">
                <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                  Loading projected call routes…
                </td>
              </tr>
              <tr v-else-if="callflows.records.length === 0">
                <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                  <ArrowPathRoundedSquareIcon class="mx-auto mb-3 size-8 text-slate-400" />No call
                  routes match this account and filter.
                </td>
              </tr>
              <tr
                v-for="route in callflows.records"
                v-else
                :key="route.id"
                class="cursor-pointer hover:bg-slate-50/60"
                @click="openDetail(route.id)"
              >
                <td class="px-5 py-3.5">
                  <p class="font-semibold text-slate-700">{{ routeTitle(route) }}</p>
                  <p class="mt-1 font-mono text-[10px] text-slate-400">
                    {{ route.root_module ?? 'No root module' }}
                  </p>
                </td>
                <td class="px-5 py-3.5 font-mono text-[11px] text-slate-600">
                  {{
                    route.numbers.join(', ') ||
                    route.patterns[0] ||
                    route.feature_code?.number ||
                    '—'
                  }}
                </td>
                <td class="px-5 py-3.5">
                  <span
                    class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-bold text-blue-700"
                    >{{ humanize(route.route_type) }}</span
                  >
                </td>
                <td class="px-5 py-3.5">
                  <p class="font-semibold text-slate-600">
                    {{ route.node_count }} nodes · depth {{ route.max_depth }}
                  </p>
                  <p class="mt-1 max-w-xs truncate text-[10px] text-slate-400">
                    {{ route.modules.map(humanize).join(' → ') || 'No structural modules' }}
                  </p>
                </td>
                <td class="px-5 py-3.5 text-slate-500">
                  {{
                    route.linked_extension?.display_name ??
                    route.phone_numbers[0]?.number ??
                    'Unassigned'
                  }}
                </td>
                <td class="px-5 py-3.5">
                  <button
                    type="button"
                    :aria-label="`View ${routeTitle(route)}`"
                    class="grid size-8 place-items-center rounded text-slate-400 hover:bg-brand-50 hover:text-brand-600"
                    @click.stop="openDetail(route.id)"
                  >
                    <ChevronRightIcon class="size-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div
        v-if="callflows.lastPage > 1"
        class="mt-4 flex items-center justify-between text-xs text-slate-500"
      >
        <button
          :disabled="callflows.page <= 1"
          class="rounded-md border border-slate-200 bg-white px-4 py-2 disabled:opacity-40"
          @click="accounts.selectedId && callflows.load(accounts.selectedId, callflows.page - 1)"
        >
          Previous</button
        ><span>Page {{ callflows.page }} of {{ callflows.lastPage }}</span
        ><button
          :disabled="callflows.page >= callflows.lastPage"
          class="rounded-md border border-slate-200 bg-white px-4 py-2 disabled:opacity-40"
          @click="accounts.selectedId && callflows.load(accounts.selectedId, callflows.page + 1)"
        >
          Next
        </button>
      </div>
    </template>
  </div>
  <CallflowEditorPanel
    v-if="callflows.editorOpen"
    :record="callflows.detail"
    :editor="callflows.editor"
    :loading="callflows.editorLoading"
    :saving="callflows.saving"
    :error="callflows.editorError"
    :field-errors="callflows.fieldErrors"
    :can-manage="canManage"
    @close="callflows.closeEditor"
    @save="saveRoute"
  />
  <CallflowNodeEditorPanel
    v-if="nodeEditorContext && !isGuidedInlineCallflowModule(nodeEditorContext.module)"
    :context="nodeEditorContext"
    :editor="callflows.treeEditor"
    :loading="callflows.treeEditorLoading"
    :saving="callflows.treeNodeSaving"
    :error="callflows.treeNodeError"
    :field-errors="callflows.treeNodeFieldErrors"
    @close="closeNodeEditor"
    @save="saveTreeNode"
  />
  <CallflowInlineNodeEditorPanel
    v-if="nodeEditorContext && isGuidedInlineCallflowModule(nodeEditorContext.module)"
    :context="nodeEditorContext"
    :editor="callflows.treeEditor"
    :loading="callflows.treeEditorLoading"
    :saving="callflows.treeNodeSaving"
    :error="callflows.treeNodeError"
    :field-errors="callflows.treeNodeFieldErrors"
    @close="closeNodeEditor"
    @save="saveTreeNode"
  />
</template>
