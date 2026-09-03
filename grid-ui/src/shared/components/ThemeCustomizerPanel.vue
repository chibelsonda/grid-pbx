<script setup lang="ts">
import { computed } from 'vue'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  CheckIcon,
  ChevronDownIcon,
  SwatchIcon,
} from '@heroicons/vue/24/outline'
import { useUiStore } from '@/app/stores/uiStore'
import {
  applicationThemes,
  findApplicationTheme,
  findShellTheme,
  headerThemes,
  sidebarThemes,
  type ShellTheme,
  type ShellThemeRegion,
} from '@/app/theme/themeCatalog'
import SlideOver from './SlideOver.vue'

const ui = useUiStore()
const selectedApplicationTheme = computed(() => findApplicationTheme(ui.applicationTheme))
const themeRegions: ShellThemeRegion[] = ['header', 'sidebar']

function selectedTheme(region: ShellThemeRegion): ShellTheme {
  return findShellTheme(region, region === 'header' ? ui.headerTheme : ui.sidebarTheme)
}

function hasOverride(region: ShellThemeRegion): boolean {
  return region === 'header' ? ui.headerThemeOverride : ui.sidebarThemeOverride
}
</script>

<template>
  <SlideOver
    :show="ui.themePanelOpen"
    title="Theme customizer"
    description="Personalize this browser without changing account settings."
    width="narrow"
    close-label="Close theme customizer"
    content-class="space-y-4 p-5"
    overlay-class="bg-slate-950/20 backdrop-blur-[1px]"
    compact-header
    @close="ui.closeThemePanel"
  >
    <template #leading>
      <SwatchIcon class="size-5 text-brand-500" />
    </template>

    <section class="card-surface overflow-hidden">
      <header class="flex items-center gap-3 border-b border-slate-200 px-4 py-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-700">Application theme</h2>
          <p class="mt-0.5 text-[10px] text-slate-500">
            One choice coordinates navigation, accents, and the workspace.
          </p>
        </div>
        <span
          class="ml-auto rounded-full bg-brand-50 px-2.5 py-1 text-[9px] font-bold tracking-wide text-brand-700 uppercase"
        >
          {{ selectedApplicationTheme.label }}
        </span>
      </header>

      <div class="space-y-4 p-4">
        <div
          class="h-32 overflow-hidden rounded-lg border shadow-sm"
          :style="{
            background: selectedApplicationTheme.tokens.canvas,
            borderColor: selectedApplicationTheme.tokens.border,
          }"
          aria-label="Application theme preview"
        >
          <div
            class="flex h-7 items-center gap-2 border-b px-3"
            :style="{
              background: selectedTheme('header').tokens.background,
              borderColor: selectedTheme('header').tokens.border,
              color: selectedTheme('header').tokens.foreground,
            }"
          >
            <span
              class="size-2.5 rounded-full"
              :style="{ background: selectedApplicationTheme.tokens.accent500 }"
            />
            <span class="text-[8px] font-bold">GridPBX</span>
            <span
              class="ml-auto text-[7px]"
              :style="{ color: selectedTheme('header').tokens.muted }"
            >
              Workspace
            </span>
          </div>
          <div class="flex h-[calc(100%-1.75rem)]">
            <div
              class="w-20 border-r p-2"
              :style="{
                background: selectedTheme('sidebar').tokens.background,
                borderColor: selectedTheme('sidebar').tokens.border,
                color: selectedTheme('sidebar').tokens.foreground,
              }"
            >
              <div
                class="h-2 w-11 rounded-full"
                :style="{ background: selectedTheme('sidebar').tokens.muted }"
              />
              <div
                class="mt-2 rounded px-1.5 py-1 text-[7px] font-semibold"
                :style="{
                  background: selectedTheme('sidebar').tokens.activeBackground,
                  color: selectedTheme('sidebar').tokens.activeForeground,
                }"
              >
                Dashboard
              </div>
            </div>
            <div class="grid flex-1 grid-cols-3 content-start gap-2 p-3">
              <div
                v-for="index in 3"
                :key="index"
                class="h-10 rounded border p-2"
                :style="{
                  background: selectedApplicationTheme.tokens.surface,
                  borderColor: selectedApplicationTheme.tokens.border,
                }"
              >
                <div
                  class="h-1.5 rounded-full"
                  :style="{
                    background:
                      index === 1
                        ? selectedApplicationTheme.tokens.accent500
                        : selectedApplicationTheme.tokens.surfaceMuted,
                  }"
                />
              </div>
            </div>
          </div>
        </div>

        <div role="radiogroup" aria-label="Application color scheme" class="grid grid-cols-2 gap-2">
          <button
            v-for="theme in applicationThemes"
            :key="theme.id"
            type="button"
            role="radio"
            :aria-checked="ui.applicationTheme === theme.id"
            :aria-label="`${theme.label} application theme`"
            class="group flex min-h-14 items-center gap-3 rounded-lg border bg-white p-2.5 text-left transition hover:-translate-y-px hover:border-brand-300 hover:shadow-sm"
            :class="
              ui.applicationTheme === theme.id
                ? 'border-brand-500 ring-2 ring-brand-100'
                : 'border-slate-200'
            "
            @click="ui.setApplicationTheme(theme.id)"
          >
            <span class="flex shrink-0 -space-x-1.5">
              <span
                v-for="swatch in theme.swatches"
                :key="swatch"
                class="size-6 rounded-full border-2 border-white shadow-sm"
                :style="{ background: swatch }"
              />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-[11px] font-semibold text-slate-700">
                {{ theme.label }}
              </span>
              <span class="block truncate text-[9px] text-slate-500">
                {{ theme.description }}
              </span>
            </span>
            <CheckIcon
              v-if="ui.applicationTheme === theme.id"
              class="size-4 shrink-0 stroke-[2.5] text-brand-600"
            />
          </button>
        </div>
      </div>
    </section>

    <Disclosure v-slot="{ open }" as="section" class="card-surface overflow-hidden">
      <DisclosureButton
        class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-slate-50"
      >
        <AdjustmentsHorizontalIcon class="size-4 text-brand-500" />
        <div>
          <h2 class="text-sm font-semibold text-slate-700">Advanced overrides</h2>
          <p class="mt-0.5 text-[10px] text-slate-500">
            Fine-tune header or sidebar independently.
          </p>
        </div>
        <span
          v-if="ui.headerThemeOverride || ui.sidebarThemeOverride"
          class="ml-auto rounded-full bg-amber-50 px-2 py-1 text-[8px] font-bold tracking-wide text-amber-700 uppercase"
        >
          Custom
        </span>
        <ChevronDownIcon
          class="size-4 text-slate-400 transition-transform"
          :class="[
            open && 'rotate-180',
            !(ui.headerThemeOverride || ui.sidebarThemeOverride) && 'ml-auto',
          ]"
        />
      </DisclosureButton>

      <DisclosurePanel class="space-y-5 border-t border-slate-200 p-4">
        <section v-for="region in themeRegions" :key="region">
          <div class="mb-3 flex items-center gap-2">
            <div>
              <h3 class="text-xs font-semibold text-slate-700 capitalize">{{ region }} style</h3>
              <p class="text-[9px] text-slate-500">
                {{ selectedTheme(region).label }}
                <template v-if="hasOverride(region)"> · custom override</template>
              </p>
            </div>
            <button
              type="button"
              class="ml-auto inline-flex h-7 items-center gap-1 rounded-md border border-slate-200 px-2.5 text-[9px] font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-default disabled:opacity-40"
              :disabled="!hasOverride(region)"
              :aria-label="`Reset ${region} override`"
              @click="ui.resetTheme(region)"
            >
              <ArrowPathIcon class="size-3" /> Use preset
            </button>
          </div>
          <div
            role="radiogroup"
            :aria-label="`${region === 'header' ? 'Header' : 'Sidebar'} color scheme`"
            class="grid grid-cols-8 gap-2"
          >
            <button
              v-for="theme in region === 'header' ? headerThemes : sidebarThemes"
              :key="theme.id"
              type="button"
              role="radio"
              :aria-checked="selectedTheme(region).id === theme.id"
              :aria-label="`${theme.label} ${region}`"
              :title="theme.label"
              class="group grid justify-items-center gap-1 text-[8px] font-medium text-slate-500"
              @click="ui.setTheme(region, theme.id)"
            >
              <span
                class="grid size-8 place-items-center rounded-full border border-slate-200 shadow-sm transition group-hover:scale-105"
                :class="
                  selectedTheme(region).id === theme.id && 'ring-2 ring-brand-500 ring-offset-2'
                "
                :style="{ background: theme.swatch }"
              >
                <CheckIcon
                  v-if="selectedTheme(region).id === theme.id"
                  class="size-3.5 stroke-[3] text-white drop-shadow"
                />
              </span>
            </button>
          </div>
        </section>
      </DisclosurePanel>
    </Disclosure>

    <template #footer>
      <footer class="shrink-0 border-t border-slate-200 bg-white p-4">
        <button
          type="button"
          class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700"
          @click="ui.resetTheme()"
        >
          <ArrowPathIcon class="size-4" /> Restore all defaults
        </button>
      </footer>
    </template>
  </SlideOver>
</template>
