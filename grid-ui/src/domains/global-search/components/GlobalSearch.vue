<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  Combobox,
  ComboboxInput,
  ComboboxOption,
  ComboboxOptions,
  Dialog,
  DialogPanel,
  DialogTitle,
  Disclosure,
  DisclosureButton,
  DisclosurePanel,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'
import {
  ArrowRightIcon,
  BuildingOffice2Icon,
  CheckIcon,
  DevicePhoneMobileIcon,
  FunnelIcon,
  HashtagIcon,
  IdentificationIcon,
  MagnifyingGlassIcon,
  MicrophoneIcon,
  MusicalNoteIcon,
  PhoneArrowDownLeftIcon,
  PhoneIcon,
  PrinterIcon,
  QueueListIcon,
  ShieldExclamationIcon,
  Squares2X2Icon,
  UserGroupIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline'
import { useGlobalSearch } from '../composables/useGlobalSearch'
import { globalSearchDestination } from '../services/globalSearchNavigation'
import {
  globalSearchTypeOptions,
  type GlobalSearchResult,
  type GlobalSearchType,
} from '../types/globalSearch'

const props = defineProps<{
  accountId: string | null
  userId: number | null
}>()

const router = useRouter()
const open = ref(false)
const accountId = computed(() => props.accountId)
const selectedTypes = ref<GlobalSearchType[]>([])
const { query, groups, total, ready, loading, error, reset } = useGlobalSearch(
  accountId,
  selectedTypes,
)
const recent = ref<GlobalSearchResult[]>([])

const visibleRecent = computed(() => {
  if (selectedTypes.value.length === 0) return recent.value

  return recent.value.filter((result) => selectedTypes.value.includes(result.type))
})
const visibleGroups = computed(() => {
  if (ready.value) return groups.value
  if (visibleRecent.value.length === 0) return []

  return [{ type: 'extension' as const, label: 'Recent', results: visibleRecent.value }]
})
const visibleResults = computed(() => visibleGroups.value.flatMap((group) => group.results))
const typeFilterLabel = computed(() => {
  if (selectedTypes.value.length === 0) return 'All types'
  if (selectedTypes.value.length === 1) {
    return (
      globalSearchTypeOptions.find((option) => option.value === selectedTypes.value[0])?.label ??
      '1 type'
    )
  }

  return `${selectedTypes.value.length} types`
})

const icons: Record<GlobalSearchType, typeof UsersIcon> = {
  extension: UsersIcon,
  device: DevicePhoneMobileIcon,
  phone_number: PhoneIcon,
  callflow: PhoneArrowDownLeftIcon,
  voicemail_box: MicrophoneIcon,
  queue: QueueListIcon,
  menu: Squares2X2Icon,
  conference: UserGroupIcon,
  directory: BuildingOffice2Icon,
  group: HashtagIcon,
  media: MusicalNoteIcon,
  recording: MicrophoneIcon,
  fax_box: PrinterIcon,
  blacklist: ShieldExclamationIcon,
  caller_id_list: IdentificationIcon,
}

function remember(result: GlobalSearchResult): void {
  recent.value = [
    result,
    ...recent.value.filter((item) => item.id !== result.id || item.type !== result.type),
  ].slice(0, 5)
}

function toggleType(type: GlobalSearchType): void {
  selectedTypes.value = selectedTypes.value.includes(type)
    ? selectedTypes.value.filter((selectedType) => selectedType !== type)
    : [...selectedTypes.value, type]
}

async function selectResult(result: GlobalSearchResult | null): Promise<void> {
  if (!result) return
  remember(result)
  open.value = false
  reset()
  await router.push(globalSearchDestination(result))
}

function selectSoleResult(event: KeyboardEvent): void {
  if (visibleResults.value.length !== 1) return

  event.preventDefault()
  void selectResult(visibleResults.value[0] ?? null)
}

function show(): void {
  if (!props.accountId) return
  open.value = true
}

function close(): void {
  open.value = false
  reset()
}

function handleShortcut(event: KeyboardEvent): void {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    show()
  }
}

watch([() => props.accountId, () => props.userId], () => {
  recent.value = []
})
onMounted(() => window.addEventListener('keydown', handleShortcut))
onBeforeUnmount(() => window.removeEventListener('keydown', handleShortcut))
</script>

<template>
  <button
    type="button"
    :disabled="!accountId"
    class="flex h-9 w-full items-center justify-center gap-2 rounded-full border border-transparent bg-slate-100 px-0 text-left text-xs text-slate-500 transition hover:border-slate-200 hover:bg-white disabled:cursor-not-allowed disabled:opacity-60 sm:justify-start sm:px-3"
    aria-label="Search this workspace"
    @click="show"
  >
    <MagnifyingGlassIcon class="size-4 shrink-0" />
    <span class="hidden truncate sm:block">{{
      accountId ? 'Search this workspace…' : 'Select an account to search'
    }}</span>
    <kbd
      class="ml-auto hidden rounded border border-slate-200 bg-white px-1.5 py-0.5 font-sans text-[9px] font-semibold text-slate-400 lg:inline"
      >Ctrl K</kbd
    >
  </button>

  <TransitionRoot appear :show="open" as="template">
    <Dialog as="div" class="relative z-50" @close="close">
      <TransitionChild
        as="template"
        enter="duration-150 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-100 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-slate-950/35 backdrop-blur-[1px]" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div class="mx-auto flex min-h-full max-w-2xl items-start justify-center pt-[8vh]">
          <TransitionChild
            as="template"
            enter="duration-150 ease-out"
            enter-from="opacity-0 -translate-y-2 scale-[0.98]"
            enter-to="opacity-100 translate-y-0 scale-100"
            leave="duration-100 ease-in"
            leave-from="opacity-100 translate-y-0 scale-100"
            leave-to="opacity-0 -translate-y-2 scale-[0.98]"
          >
            <DialogPanel
              class="w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
            >
              <DialogTitle class="sr-only">Search this workspace</DialogTitle>
              <Combobox :model-value="null" nullable @update:model-value="selectResult">
                <div class="flex items-center gap-3 border-b border-slate-200 px-4">
                  <MagnifyingGlassIcon class="size-5 shrink-0 text-slate-400" />
                  <ComboboxInput
                    class="h-14 w-full border-0 bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:ring-0"
                    autocomplete="off"
                    placeholder="Search people, devices, numbers, routes…"
                    :value="query"
                    @input="query = ($event.target as HTMLInputElement).value"
                    @keydown.enter="selectSoleResult"
                  />
                  <span
                    v-if="loading"
                    class="size-4 animate-spin rounded-full border-2 border-brand-200 border-t-brand-600"
                  />
                  <kbd
                    class="rounded border border-slate-200 px-1.5 py-0.5 text-[9px] text-slate-400"
                    >ESC</kbd
                  >
                </div>

                <Disclosure
                  v-slot="{ open: filtersOpen }"
                  as="div"
                  class="border-b border-slate-100"
                >
                  <DisclosureButton
                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-[10px] font-semibold text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                    aria-label="Filter search types"
                  >
                    <FunnelIcon class="size-3.5 text-slate-400" />
                    <span>{{ typeFilterLabel }}</span>
                    <span class="ml-auto text-[9px] font-medium text-slate-400">
                      {{ filtersOpen ? 'Hide filters' : 'Filter results' }}
                    </span>
                  </DisclosureButton>
                  <DisclosurePanel class="border-t border-slate-100 bg-slate-50/70 px-4 py-3">
                    <div class="flex flex-wrap gap-1.5" aria-label="Search resource types">
                      <button
                        type="button"
                        class="inline-flex h-7 items-center gap-1.5 rounded-md border px-2.5 text-[10px] font-semibold transition"
                        :class="
                          selectedTypes.length === 0
                            ? 'border-brand-300 bg-brand-50 text-brand-700'
                            : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700'
                        "
                        :aria-pressed="selectedTypes.length === 0"
                        @click="selectedTypes = []"
                      >
                        <CheckIcon v-if="selectedTypes.length === 0" class="size-3" />
                        All types
                      </button>
                      <button
                        v-for="option in globalSearchTypeOptions"
                        :key="option.value"
                        type="button"
                        class="inline-flex h-7 items-center gap-1.5 rounded-md border px-2.5 text-[10px] font-semibold transition"
                        :class="
                          selectedTypes.includes(option.value)
                            ? 'border-brand-300 bg-brand-50 text-brand-700'
                            : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700'
                        "
                        :aria-pressed="selectedTypes.includes(option.value)"
                        @click="toggleType(option.value)"
                      >
                        <CheckIcon v-if="selectedTypes.includes(option.value)" class="size-3" />
                        {{ option.label }}
                      </button>
                    </div>
                  </DisclosurePanel>
                </Disclosure>

                <ComboboxOptions
                  static
                  class="max-h-[62vh] overflow-y-auto py-2 focus:outline-none"
                >
                  <div v-if="!ready && visibleRecent.length === 0" class="px-5 py-10 text-center">
                    <MagnifyingGlassIcon class="mx-auto size-8 text-slate-300" />
                    <p class="mt-3 text-sm font-semibold text-slate-600">Search this account</p>
                    <p class="mt-1 text-xs text-slate-400">Enter at least two characters.</p>
                  </div>

                  <div v-else-if="error" class="px-5 py-10 text-center text-xs text-danger">
                    {{ error }}
                  </div>

                  <div v-else-if="ready && !loading && total === 0" class="px-5 py-10 text-center">
                    <p class="text-sm font-semibold text-slate-600">No matching resources</p>
                    <p class="mt-1 text-xs text-slate-400">
                      Try a name, extension, number, or device identifier.
                    </p>
                  </div>

                  <section v-for="group in visibleGroups" :key="group.label" class="py-1">
                    <h2
                      class="px-4 py-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                    >
                      {{ group.label }}
                    </h2>
                    <ComboboxOption
                      v-for="result in group.results"
                      :key="`${result.type}:${result.id}`"
                      v-slot="{ active }"
                      :value="result"
                      as="template"
                    >
                      <li
                        class="mx-2 flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5"
                        :class="active ? 'bg-brand-50' : 'bg-white'"
                      >
                        <span
                          class="grid size-9 shrink-0 place-items-center rounded-lg"
                          :class="
                            active ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500'
                          "
                        >
                          <component :is="icons[result.type]" class="size-4.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                          <span class="block truncate text-xs font-semibold text-slate-700">{{
                            result.title
                          }}</span>
                          <span
                            v-if="result.subtitle"
                            class="mt-0.5 block truncate text-[10px] text-slate-500"
                            >{{ result.subtitle }}</span
                          >
                        </span>
                        <ArrowRightIcon
                          class="size-4 text-slate-300"
                          :class="active && 'text-brand-500'"
                        />
                      </li>
                    </ComboboxOption>
                  </section>
                </ComboboxOptions>

                <footer
                  class="flex items-center gap-4 border-t border-slate-100 bg-slate-50/80 px-4 py-2 text-[9px] text-slate-400"
                >
                  <span><kbd class="font-sans">↑↓</kbd> Navigate</span>
                  <span><kbd class="font-sans">↵</kbd> Open</span>
                  <span class="ml-auto">Selected account only</span>
                </footer>
              </Combobox>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
