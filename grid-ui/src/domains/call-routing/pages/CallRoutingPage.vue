<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  ArrowPathIcon,
  ArrowPathRoundedSquareIcon,
  BoltIcon,
  ChevronRightIcon,
  PhoneArrowDownLeftIcon,
  PencilSquareIcon,
  QueueListIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import PageBackLink from '@/shared/components/PageBackLink.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import CallflowDetailPanel from '../components/CallflowDetailPanel.vue'
import CallflowAddEntryNumberDialog, {
  type CallflowEntryNumberAddition,
} from '../components/CallflowAddEntryNumberDialog.vue'
import CallflowEditorPanel from '../components/CallflowEditorPanel.vue'
import CallflowInlineNodeEditorPanel from '../components/CallflowInlineNodeEditorPanel.vue'
import CallflowNodeEditorPanel from '../components/CallflowNodeEditorPanel.vue'
import {
  callflowInlineModuleNeedsEditorCatalog,
  isGuidedInlineCallflowModule,
} from '../catalog/callflowActionCatalog'
import { useCallflowStore } from '../stores/callflowStore'
import {
  listenForCallflowCapabilitiesChanged,
  type CallflowCapabilitiesChanged,
} from '../services/callflowCapabilityRefresh'
import type {
  CallflowNodeEditorContext,
  CallflowCreateInput,
  CallflowTreeMoveInput,
  CallflowTreeReorderInput,
  CallflowTreeNodeCreateInput,
  CallflowTreeNodeUpdateInput,
  CallflowInlineNodeCreateInput,
  CallflowInlineNodeUpdateInput,
} from '../types/callRouting'

const accounts = useAccountStore()
const route = useRoute()
const callflows = useCallflowStore()
const nodeEditorContext = ref<CallflowNodeEditorContext | null>(null)
const entryNumberOpen = ref(false)
let stopCapabilityListener: (() => void) | null = null

function nodeEditorAction(context: CallflowNodeEditorContext): unknown {
  return (
    context.preset?.action ??
    context.node.settings?.action ??
    (context.preset?.service_mode === true || context.node.settings?.service_mode === true
      ? 'service'
      : undefined)
  )
}
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
const creatingRoute = computed(() => callflows.editorOpen && callflows.editor?.mode === 'create')
const workspaceOpen = computed(
  () =>
    creatingRoute.value ||
    (!callflows.editorOpen &&
      (callflows.detailLoading || callflows.detail !== null || callflows.detailError !== null)),
)
const viewingRoute = computed(() => workspaceOpen.value && !creatingRoute.value)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const pageEyebrow = computed(() =>
  creatingRoute.value
    ? 'GridPBX / Callflows / Create'
    : viewingRoute.value
      ? 'GridPBX / Callflows / Detail'
      : 'GridPBX / Callflows',
)
const pageTitle = computed(() =>
  creatingRoute.value
    ? 'Create callflow'
    : viewingRoute.value && callflows.detail
      ? routeTitle(callflows.detail)
      : viewingRoute.value
        ? 'Callflow workspace'
        : 'Callflows',
)
const pageDescription = computed(() =>
  creatingRoute.value
    ? 'Create a phone-number route using safe public GridPBX references.'
    : viewingRoute.value
      ? 'The full-width route map stays on the main page; select a node to inspect it in a modal.'
      : 'Understand incoming entry points and the safe structural path each call follows.',
)
watch(
  [() => accounts.selectedId, () => route.query.callflow],
  ([accountId, callflowId]) => {
    nodeEditorContext.value = null
    callflows.reset()
    if (accountId) {
      void callflows.load(accountId, 1)
      if (typeof callflowId === 'string') {
        void callflows.loadDetail(accountId, callflowId)
        void callflows.loadTreeEditor(accountId, callflowId)
      }
    }
  },
  { immediate: true },
)

function applyFilters(): void {
  if (accounts.selectedId) void callflows.load(accounts.selectedId, 1)
}

function synchronize(): void {
  if (accounts.selectedId) void callflows.synchronize(accounts.selectedId)
}

function refreshCallflowNodes(): void {
  if (!accounts.selectedId || !callflows.detail || callflows.synchronizing) return
  void callflows.refreshDetail(accounts.selectedId, callflows.detail.id)
}

function openDetail(id: string): void {
  if (accounts.selectedId) {
    void callflows.loadDetail(accounts.selectedId, id)
    void callflows.loadTreeEditor(accounts.selectedId, id)
  }
}

function closeWorkspace(): void {
  if (creatingRoute.value) callflows.closeEditor()
  else callflows.closeDetail()
}

function openEditor(): void {
  if (accounts.selectedId && callflows.detail) {
    void callflows.openEditor(accounts.selectedId, callflows.detail.id)
  }
}

async function openEntryNumberDialog(): Promise<void> {
  if (!accounts.selectedId || !callflows.detail) return

  if (!callflows.treeEditor) {
    const loaded = await callflows.loadTreeEditor(accounts.selectedId, callflows.detail.id)
    if (!loaded) return
  }

  callflows.entryPointError = null
  callflows.entryPointFieldErrors = {}
  entryNumberOpen.value = true
}

async function addEntryNumber(addition: CallflowEntryNumberAddition): Promise<void> {
  if (!accounts.selectedId || !callflows.detail || !callflows.treeEditor) return

  const phoneNumberIds = callflows.treeEditor.phone_numbers
    .filter(({ selected }) => selected)
    .map(({ id }) => id)
  const extensionNumbers = [...(callflows.treeEditor.extension_numbers ?? [])]

  if (addition.type === 'phone_number') phoneNumberIds.push(addition.id)
  else extensionNumbers.push(addition.value)

  const callflowId = callflows.detail.id
  const updated = await callflows.updateEntryPoints(accounts.selectedId, callflowId, {
    phone_number_ids: [...new Set(phoneNumberIds)],
    extension_numbers: [...new Set(extensionNumbers)],
  })

  if (!updated) return

  entryNumberOpen.value = false
  await callflows.loadTreeEditor(accounts.selectedId, callflowId)
}

function openCreateEditor(): void {
  if (accounts.selectedId) void callflows.openCreateEditor(accounts.selectedId)
}

function refreshCapabilities(change: CallflowCapabilitiesChanged): void {
  if (change.accountId !== accounts.selectedId || !workspaceOpen.value) return

  const callflowId = creatingRoute.value ? null : (callflows.detail?.id ?? null)
  void callflows.refreshCapabilityOptions(change.accountId, callflowId)
}

onMounted(() => {
  stopCapabilityListener = listenForCallflowCapabilitiesChanged(refreshCapabilities)
})

onBeforeUnmount(() => {
  stopCapabilityListener?.()
  stopCapabilityListener = null
})

function saveRoute(input: CallflowCreateInput): void {
  if (!accounts.selectedId) return

  if (callflows.detail) {
    if (input.destination_type === null) return
    void callflows.update(accounts.selectedId, callflows.detail.id, input)
  } else void callflows.create(accounts.selectedId, input)
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

async function deleteTreeNode(path: string[]): Promise<void> {
  if (!accounts.selectedId || !callflows.detail) return

  const updated = await callflows.deleteTreeNode(accounts.selectedId, callflows.detail.id, {
    node_path: path,
    confirm_subtree: true,
  })

  if (updated) nodeEditorContext.value = null
}

function openNodeEditor(context: CallflowNodeEditorContext): void {
  if (!accounts.selectedId || !callflows.detail) return
  nodeEditorContext.value = context
  if (
    isGuidedInlineCallflowModule(context.module, nodeEditorAction(context)) &&
    !callflowInlineModuleNeedsEditorCatalog(context.module)
  ) {
    callflows.clearTreeNodeErrors()
  } else {
    void callflows.loadTreeEditor(accounts.selectedId, callflows.detail.id)
  }
}

function closeNodeEditor(): void {
  nodeEditorContext.value = null
  callflows.clearTreeNodeErrors()
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
  <section
    data-callflow-page-header
    class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8"
  >
    <div class="flex w-full flex-col gap-4 sm:flex-row sm:items-center">
      <div class="min-w-0 flex-1">
        <p class="mb-1 flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
          <template v-if="viewingRoute">
            <PageBackLink label="Back to callflows" @click="closeWorkspace" />
            <span aria-hidden="true">/</span>
            <span>Detail</span>
          </template>
          <template v-else>{{ pageEyebrow }}</template>
        </p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">{{ pageTitle }}</h1>
        <p class="mt-1 text-xs text-slate-500">{{ pageDescription }}</p>
      </div>
      <div class="flex w-full flex-wrap justify-end gap-3 sm:ml-auto sm:w-auto">
        <button
          v-if="canManage && !workspaceOpen"
          type="button"
          :disabled="!accounts.selectedId"
          class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
          @click="openCreateEditor"
        >
          <PlusIcon class="size-4" /> Create callflow
        </button>
        <ProjectionSyncButton
          v-if="!workspaceOpen"
          :synchronizing="callflows.synchronizing"
          :disabled="!accounts.selectedId || callflows.synchronizing"
          @sync="synchronize"
        />
        <template v-else-if="viewingRoute">
          <button
            type="button"
            aria-label="Refresh callflow nodes"
            title="Refresh callflow nodes"
            :disabled="callflows.detailLoading || callflows.synchronizing || !callflows.detail"
            class="grid size-9 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-brand-600 disabled:cursor-wait disabled:text-slate-300"
            @click="refreshCallflowNodes"
          >
            <ArrowPathIcon
              class="size-4"
              :class="(callflows.detailLoading || callflows.synchronizing) && 'animate-spin'"
            />
          </button>
          <button
            v-if="canManage && callflows.detail"
            type="button"
            class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600"
            @click="openEditor"
          >
            <PencilSquareIcon class="size-4" /> Edit callflow
          </button>
        </template>
        <button
          v-else
          type="button"
          class="grid size-9 place-items-center rounded-md border border-slate-200 bg-white text-slate-500 shadow-sm hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600"
          aria-label="Close create callflow"
          @click="closeWorkspace"
        >
          <XMarkIcon class="size-5" />
        </button>
        <ProjectionFreshness
          v-if="!workspaceOpen"
          class="w-full justify-end"
          :last-synchronized-at="callflows.sync.last_successful_at"
          :status="callflows.sync.status"
        />
      </div>
    </div>
  </section>

  <div :class="workspaceOpen ? 'w-full' : 'mx-auto w-full max-w-[1500px] p-4 sm:p-6 lg:p-8'">
    <template v-if="workspaceOpen">
      <CallflowEditorPanel
        v-if="creatingRoute"
        workspace
        :record="null"
        :editor="callflows.editor"
        :loading="callflows.editorLoading"
        :saving="callflows.saving"
        :error="callflows.editorError"
        :field-errors="callflows.fieldErrors"
        :can-manage="canManage"
        @close="closeWorkspace"
        @save="saveRoute"
      />
      <template v-else>
        <CallflowDetailPanel
          :record="callflows.detail"
          :loading="callflows.detailLoading"
          :error="callflows.detailError"
          :can-manage="canManage"
          :deleting="callflows.deleting"
          :mutation-error="callflows.mutationError"
          :tree-moving="callflows.treeMoving"
          :tree-deleting="callflows.treeDeleting"
          :tree-mutation-error="callflows.treeMutationError"
          :action-capabilities="callflows.treeEditor?.action_capabilities ?? {}"
          @delete="deleteRoute"
          @edit-entry="openEditor"
          @add-entry="openEntryNumberDialog"
          @move-node="moveTreeNode"
          @reorder-nodes="reorderTreeNodes"
          @delete-node="deleteTreeNode"
          @create-node="openNodeEditor"
          @edit-node="openNodeEditor"
        />
      </template>
    </template>

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
          label="Search callflows"
          placeholder="Search route, number, pattern, feature code…"
          input-class="h-10 bg-white text-xs shadow-sm"
          live
          @search="applyFilters"
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
        role="alert"
        class="mb-4 rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ callflows.error }}
      </div>
      <div class="card-surface overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[900px] text-left" :aria-busy="callflows.loading">
            <caption class="sr-only">
              Projected callflows for the selected Switch account
            </caption>
            <thead
              class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
            >
              <tr>
                <th scope="col" class="px-5 py-3.5">Route</th>
                <th scope="col" class="px-5 py-3.5">Entry points</th>
                <th scope="col" class="px-5 py-3.5">Type</th>
                <th scope="col" class="px-5 py-3.5">Path</th>
                <th scope="col" class="px-5 py-3.5">Assignment</th>
                <th scope="col" aria-label="View callflow" class="w-12 px-5 py-3.5" />
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr v-if="callflows.loading">
                <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                  <span role="status">Loading projected callflows…</span>
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
                  <button
                    type="button"
                    class="font-semibold text-slate-700 hover:text-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                    @click.stop="openDetail(route.id)"
                  >
                    {{ routeTitle(route) }}
                  </button>
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
        class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500"
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
    v-if="callflows.editorOpen && callflows.editor?.mode === 'update'"
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
  <CallflowAddEntryNumberDialog
    v-if="callflows.treeEditor"
    :open="entryNumberOpen"
    :phone-numbers="callflows.treeEditor.phone_numbers"
    :phone-number-ids="
      callflows.treeEditor.phone_numbers.filter(({ selected }) => selected).map(({ id }) => id)
    "
    :extension-numbers="callflows.treeEditor.extension_numbers ?? []"
    :preserved-numbers="callflows.treeEditor.preserved_numbers ?? []"
    :saving="callflows.entryPointSaving"
    :error="callflows.entryPointError"
    :field-errors="callflows.entryPointFieldErrors"
    @close="entryNumberOpen = false"
    @add="addEntryNumber"
  />
  <CallflowNodeEditorPanel
    v-if="
      nodeEditorContext &&
      !isGuidedInlineCallflowModule(nodeEditorContext.module, nodeEditorAction(nodeEditorContext))
    "
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
    v-if="
      nodeEditorContext &&
      isGuidedInlineCallflowModule(nodeEditorContext.module, nodeEditorAction(nodeEditorContext))
    "
    :context="nodeEditorContext"
    :root-configuration="nodeEditorContext.path.length === 0"
    :editor="callflows.treeEditor"
    :loading="callflows.treeEditorLoading"
    :saving="callflows.treeNodeSaving"
    :error="callflows.treeNodeError"
    :field-errors="callflows.treeNodeFieldErrors"
    @close="closeNodeEditor"
    @save="saveTreeNode"
  />
</template>
