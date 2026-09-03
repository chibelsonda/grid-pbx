<script setup lang="ts">
import { computed } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import {
  ArrowTopRightOnSquareIcon,
  PlusIcon,
  WrenchScrewdriverIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { useRouter, type RouteLocationRaw } from 'vue-router'
import type { CallflowDestinationType } from '../types/callRouting'

const props = defineProps<{
  open: boolean
  type: CallflowDestinationType
  selectedId: string | null
  selectedLabel: string | null
}>()
const emit = defineEmits<{ close: [] }>()
const router = useRouter()

const resourceNames: Partial<Record<CallflowDestinationType, string>> = {
  extension: 'extension',
  device: 'device',
  voicemail: 'voicemail box',
  callflow: 'callflow',
  media: 'media file',
  directory: 'directory',
  group: 'group',
  queue: 'queue',
  menu: 'menu',
  conference: 'conference',
  fax_box: 'fax box',
  temporal_rule_set: 'time-of-day rule set',
}

const collectionRoutes: Partial<Record<CallflowDestinationType, RouteLocationRaw>> = {
  extension: { name: 'extensions', query: { create: '1' } },
  device: { name: 'device-create' },
  voicemail: { name: 'voicemail-create' },
  callflow: { name: 'call-routing' },
  media: { name: 'media' },
  directory: { name: 'directories' },
  group: { name: 'groups' },
  queue: { name: 'queues' },
  menu: { name: 'menus' },
  conference: { name: 'conferences' },
  fax_box: { name: 'faxes' },
  temporal_rule_set: { name: 'business-hours' },
}

const resourceName = computed(() => resourceNames[props.type] ?? 'resource')
const selectedRoute = computed<RouteLocationRaw | null>(() => {
  if (!props.selectedId) return null

  switch (props.type) {
    case 'extension':
      return {
        name: 'extension-detail',
        params: { extensionId: props.selectedId },
        query: { action: 'edit' },
      }
    case 'device':
      return { name: 'device-edit', params: { deviceId: props.selectedId } }
    case 'voicemail':
      return { name: 'voicemail-edit', params: { voicemailBoxId: props.selectedId } }
    case 'callflow':
      return { name: 'call-routing', query: { callflow: props.selectedId } }
    case 'media':
      return { name: 'media', query: { media: props.selectedId } }
    case 'fax_box':
      return { name: 'faxes', query: { fax_box: props.selectedId } }
    default: {
      const route = collectionRoutes[props.type]
      return route && typeof route === 'object'
        ? { ...route, query: props.selectedLabel ? { search: props.selectedLabel } : undefined }
        : null
    }
  }
})
const selectedHref = computed(() =>
  selectedRoute.value === null ? null : router.resolve(selectedRoute.value).href,
)
const collectionHref = computed(() => {
  const route = collectionRoutes[props.type]
  return route ? router.resolve(route).href : null
})
</script>

<template>
  <TransitionRoot appear :show="open" as="template">
    <Dialog class="relative z-[70]" @close="emit('close')">
      <TransitionChild
        as="template"
        enter="duration-200 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-150 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-[1px]" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div class="flex min-h-full items-center justify-center">
          <TransitionChild
            as="template"
            enter="duration-200 ease-out"
            enter-from="translate-y-2 opacity-0 sm:scale-95"
            enter-to="translate-y-0 opacity-100 sm:scale-100"
            leave="duration-150 ease-in"
            leave-from="translate-y-0 opacity-100 sm:scale-100"
            leave-to="translate-y-2 opacity-0 sm:scale-95"
          >
            <DialogPanel class="w-full max-w-md rounded-lg bg-white p-6 shadow-2xl">
              <div class="flex items-start gap-3">
                <span
                  class="grid size-10 shrink-0 place-items-center rounded-md bg-brand-50 text-brand-600"
                >
                  <WrenchScrewdriverIcon class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                  <DialogTitle class="text-sm font-semibold text-slate-800">
                    {{ resourceName }} actions
                  </DialogTitle>
                  <p class="mt-1 text-xs leading-5 text-slate-500">
                    Edit the selected resource or create another one without losing this callflow
                    draft. Resource management opens in a new tab.
                  </p>
                </div>
                <button
                  type="button"
                  class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                  aria-label="Close resource actions"
                  @click="emit('close')"
                >
                  <XMarkIcon class="size-5" />
                </button>
              </div>

              <div class="mt-5 grid gap-3">
                <a
                  v-if="selectedHref"
                  :href="selectedHref"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="flex items-center gap-3 rounded-md border border-slate-200 p-4 text-left hover:border-brand-200 hover:bg-brand-50/40"
                >
                  <WrenchScrewdriverIcon class="size-5 shrink-0 text-brand-600" />
                  <span class="min-w-0 flex-1">
                    <span class="block text-xs font-semibold text-slate-700">Edit selected</span>
                    <span class="mt-0.5 block truncate text-[10px] text-slate-500">
                      {{ selectedLabel ?? `Selected ${resourceName}` }}
                    </span>
                  </span>
                  <ArrowTopRightOnSquareIcon class="size-4 shrink-0 text-slate-400" />
                </a>

                <a
                  v-if="collectionHref"
                  :href="collectionHref"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="flex items-center gap-3 rounded-md border border-slate-200 p-4 text-left hover:border-brand-200 hover:bg-brand-50/40"
                >
                  <PlusIcon class="size-5 shrink-0 text-brand-600" />
                  <span class="min-w-0 flex-1">
                    <span class="block text-xs font-semibold text-slate-700">Create or manage</span>
                    <span class="mt-0.5 block text-[10px] text-slate-500">
                      Open the {{ resourceName }} manager.
                    </span>
                  </span>
                  <ArrowTopRightOnSquareIcon class="size-4 shrink-0 text-slate-400" />
                </a>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
