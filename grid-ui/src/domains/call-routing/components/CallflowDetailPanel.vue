<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowLeftIcon,
  ArrowPathRoundedSquareIcon,
  HashtagIcon,
  LinkIcon,
  PencilSquareIcon,
  ShieldCheckIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import { findCallflowAction } from '../catalog/callflowActionCatalog'
import CallflowActionPalette from './CallflowActionPalette.vue'
import CallflowDiagram from './CallflowDiagram.vue'
import type { Callflow, CallflowNode, CallflowNodeSelection } from '../types/callRouting'

const props = defineProps<{
  record: Callflow | null
  loading: boolean
  error: string | null
  canManage: boolean
  deleting: boolean
  mutationError: string | null
}>()
defineEmits<{ close: []; edit: []; delete: [] }>()
const confirmingDelete = ref(false)
const selectedNode = ref<CallflowNode | null>(null)
const selectedPath = ref<string[]>([])
const title = computed(
  () =>
    props.record?.name ??
    props.record?.feature_code?.name ??
    props.record?.numbers[0] ??
    'Call route details',
)
const deletionBlocker = computed(() => {
  if (props.record?.feature_code) return 'Feature-code routes cannot be deleted here.'
  if (props.record?.linked_extension) return 'This route belongs to an extension.'
  if (props.record?.phone_numbers.length) return 'Remove assigned phone numbers before deletion.'

  return null
})
const selectedAction = computed(() =>
  selectedNode.value ? findCallflowAction(selectedNode.value.module) : null,
)
const selectionBreadcrumb = computed(() => {
  const labels = ['Root']
  let node = props.record?.flow ?? null

  for (const segment of selectedPath.value) {
    node = node?.children[segment] ?? null
    if (!node) break
    labels.push(node.branch?.label ?? humanize(segment))
  }

  return labels
})
const selectedStatusLabel = computed(() => {
  if (!selectedAction.value) return 'Preserved / read only'

  return {
    guided: 'Guided now',
    planned: 'Visual editor planned',
    restricted: 'Capability required',
  }[selectedAction.value.status]
})
const selectedStatusClass = computed(() => {
  switch (selectedAction.value?.status) {
    case 'guided':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'planned':
      return 'border-blue-200 bg-blue-50 text-blue-700'
    default:
      return 'border-amber-200 bg-amber-50 text-amber-700'
  }
})

watch(
  () => props.record?.flow,
  (flow) => {
    selectedNode.value = flow ?? null
    selectedPath.value = []
  },
  { immediate: true },
)

function selectNode(selection: CallflowNodeSelection): void {
  selectedNode.value = selection.node
  selectedPath.value = [...selection.path]
}

function humanize(value: string | null): string {
  return value
    ? value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
    : 'Unknown'
}
</script>

<template>
  <section aria-label="Callflow workspace" class="grid gap-5">
    <header class="card-surface flex flex-wrap items-center gap-4 p-5">
      <button
        type="button"
        aria-label="Back to call routes"
        class="grid size-9 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50"
        @click="$emit('close')"
      >
        <ArrowLeftIcon class="size-4" />
      </button>
      <div class="min-w-0">
        <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
          Callflow workspace
        </p>
        <h2 class="mt-1 truncate text-lg font-semibold text-slate-800">{{ title }}</h2>
        <p class="mt-1 text-xs text-slate-500">
          The route map and node inspector stay on the main page; mutation forms open from here.
        </p>
      </div>
      <button
        v-if="canManage && record"
        type="button"
        class="ml-auto inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600"
        @click="$emit('edit')"
      >
        <PencilSquareIcon class="size-4" /> Edit guided route
      </button>
    </header>

    <div v-if="loading" class="card-surface p-10 text-center text-xs text-slate-400">
      Loading call route…
    </div>
    <div
      v-else-if="error"
      class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ error }}
    </div>
    <div v-else-if="record" class="grid gap-5">
      <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-6">
        <article class="card-surface p-4">
          <HashtagIcon class="size-5 text-blue-500" />
          <p class="mt-3 text-lg font-semibold text-slate-700">{{ record.node_count }}</p>
          <p class="text-[9px] font-bold tracking-wide text-slate-400 uppercase">Nodes</p>
        </article>
        <article class="card-surface p-4">
          <LinkIcon class="size-5 text-violet-500" />
          <p class="mt-3 text-lg font-semibold text-slate-700">{{ record.max_depth }}</p>
          <p class="text-[9px] font-bold tracking-wide text-slate-400 uppercase">Max depth</p>
        </article>
        <article class="card-surface p-4">
          <ArrowPathRoundedSquareIcon class="size-5 text-emerald-500" />
          <p class="mt-3 text-lg font-semibold text-slate-700">{{ record.modules.length }}</p>
          <p class="text-[9px] font-bold tracking-wide text-slate-400 uppercase">Modules</p>
        </article>
        <article class="card-surface p-4 sm:col-span-2 xl:col-span-3">
          <div class="flex items-center gap-3">
            <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
              <ArrowPathRoundedSquareIcon class="size-4" />
            </span>
            <div>
              <p class="text-xs font-semibold text-slate-700">{{ humanize(record.route_type) }}</p>
              <p class="mt-0.5 text-[10px] text-slate-500">
                Synchronized
                {{
                  record.last_synced_at ? new Date(record.last_synced_at).toLocaleString() : 'never'
                }}
              </p>
            </div>
          </div>
        </article>
      </div>

      <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
        <div class="grid min-w-0 gap-5">
          <article class="card-surface p-5">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Route structure</h2>
            <CallflowDiagram
              v-if="record.flow"
              :node="record.flow"
              :selected-path="selectedPath"
              @select="selectNode"
            />
            <p v-else class="text-xs text-slate-500">
              Switch did not return a structural flow for this route.
            </p>
            <section
              v-if="selectedNode"
              aria-labelledby="selected-callflow-node-heading"
              class="mt-4 rounded-lg border border-slate-200 bg-slate-50/70 p-4"
            >
              <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-0">
                  <h3
                    id="selected-callflow-node-heading"
                    class="text-xs font-semibold text-slate-700"
                  >
                    Selected node
                  </h3>
                  <p class="mt-1 break-words text-[10px] text-slate-500">
                    {{ selectionBreadcrumb.join(' / ') }}
                  </p>
                </div>
                <span
                  class="ml-auto rounded-full border px-2.5 py-1 text-[9px] font-semibold"
                  :class="selectedStatusClass"
                >
                  {{ selectedStatusLabel }}
                </span>
              </div>
              <dl class="mt-4 grid gap-3 text-[10px] sm:grid-cols-2 lg:grid-cols-4">
                <div>
                  <dt class="font-bold tracking-wide text-slate-500 uppercase">Module</dt>
                  <dd class="mt-1 font-mono font-semibold text-slate-700">
                    {{ selectedNode.module }}
                  </dd>
                </div>
                <div>
                  <dt class="font-bold tracking-wide text-slate-500 uppercase">Destination</dt>
                  <dd class="mt-1 font-semibold text-slate-700">
                    {{ selectedNode.target?.label ?? 'Inline Switch action' }}
                  </dd>
                </div>
                <div>
                  <dt class="font-bold tracking-wide text-slate-500 uppercase">Reference</dt>
                  <dd class="mt-1 text-slate-700">{{ humanize(selectedNode.reference_status) }}</dd>
                </div>
                <div>
                  <dt class="font-bold tracking-wide text-slate-500 uppercase">Child paths</dt>
                  <dd class="mt-1 text-slate-700">
                    {{ Object.keys(selectedNode.children).length }}
                  </dd>
                </div>
              </dl>
            </section>
          </article>

          <CallflowActionPalette />
        </div>

        <aside class="grid gap-5 xl:sticky xl:top-5">
          <article class="card-surface p-5">
            <h2 class="text-sm font-semibold text-slate-700">Entry points</h2>
            <div class="mt-4 grid gap-3 text-xs">
              <div>
                <p class="text-[9px] font-bold tracking-wide text-slate-400 uppercase">Numbers</p>
                <p class="mt-1 font-mono text-slate-600">
                  {{ record.numbers.join(', ') || 'None' }}
                </p>
              </div>
              <div v-if="record.patterns.length">
                <p class="text-[9px] font-bold tracking-wide text-slate-400 uppercase">Patterns</p>
                <p class="mt-1 break-all font-mono text-slate-600">
                  {{ record.patterns.join(', ') }}
                </p>
              </div>
              <div v-if="record.feature_code">
                <p class="text-[9px] font-bold tracking-wide text-slate-400 uppercase">
                  Feature code
                </p>
                <p class="mt-1 text-slate-600">
                  {{ record.feature_code.name ?? 'Feature code' }}
                  <span class="font-mono">{{ record.feature_code.number }}</span>
                </p>
              </div>
            </div>
          </article>

          <article class="card-surface p-5">
            <h2 class="text-sm font-semibold text-slate-700">Assignments</h2>
            <div class="mt-4 grid gap-2 text-xs text-slate-600">
              <p v-if="record.linked_extension">
                Extension:
                <span class="font-semibold text-brand-600">{{
                  record.linked_extension.display_name ?? record.linked_extension.extension
                }}</span>
              </p>
              <p v-for="number in record.phone_numbers" :key="number.id">
                Phone number:
                <span class="font-mono font-semibold text-brand-600">{{ number.number }}</span>
              </p>
              <p
                v-if="!record.linked_extension && record.phone_numbers.length === 0"
                class="text-slate-500"
              >
                No projected extension or phone-number assignment.
              </p>
            </div>
          </article>

          <div
            class="flex gap-3 rounded-md border border-amber-100 bg-amber-50 p-4 text-xs leading-5 text-amber-800"
          >
            <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
            <p>
              Raw node data and Switch identifiers are never exposed. Guided mutations preserve
              existing unsupported branches.
            </p>
          </div>

          <button
            v-if="canManage"
            type="button"
            :disabled="Boolean(deletionBlocker)"
            class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-red-200 bg-white px-5 text-xs font-semibold text-danger hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
            @click="confirmingDelete = true"
          >
            <TrashIcon class="size-4" /> Delete route
          </button>
          <p v-if="canManage && deletionBlocker" class="text-[10px] text-amber-700">
            {{ deletionBlocker }}
          </p>
          <p v-if="mutationError" class="text-xs font-semibold text-danger">
            {{ mutationError }}
          </p>
        </aside>
      </div>
    </div>
  </section>
  <ConfirmDialog
    :open="confirmingDelete"
    title="Delete this route?"
    description="GridPBX will check projected dependencies again before deleting it from Switch."
    confirm-label="Delete route"
    :busy="deleting"
    @close="confirmingDelete = false"
    @confirm="$emit('delete')"
  />
</template>
