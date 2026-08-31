<script setup lang="ts">
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { ArrowPathIcon, CheckIcon, SwatchIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { useUiStore } from '@/app/stores/uiStore'
import {
  findShellTheme,
  headerThemes,
  sidebarThemes,
  type ShellTheme,
  type ShellThemeRegion,
} from '@/app/theme/themeCatalog'

const ui = useUiStore()

function selectedTheme(region: ShellThemeRegion): ShellTheme {
  return findShellTheme(region, region === 'header' ? ui.headerTheme : ui.sidebarTheme)
}
</script>

<template>
  <TransitionRoot appear :show="ui.themePanelOpen" as="template">
    <Dialog class="relative z-50" @close="ui.closeThemePanel">
      <TransitionChild
        as="template"
        enter="ease-out duration-200"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="ease-in duration-150"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-slate-950/20 backdrop-blur-[1px]" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
          <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-8 sm:pl-12">
            <TransitionChild
              as="template"
              enter="transform transition ease-out duration-300"
              enter-from="translate-x-full"
              enter-to="translate-x-0"
              leave="transform transition ease-in duration-200"
              leave-from="translate-x-0"
              leave-to="translate-x-full"
            >
              <DialogPanel
                class="pointer-events-auto flex w-screen max-w-md flex-col bg-slate-50 shadow-2xl"
              >
                <header
                  class="flex items-center gap-3 border-b border-slate-200 bg-white px-5 py-4"
                >
                  <span
                    class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
                  >
                    <SwatchIcon class="size-5" />
                  </span>
                  <div>
                    <DialogTitle class="text-base font-semibold text-slate-800">
                      Theme customizer
                    </DialogTitle>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                      Personalize this browser without changing account settings.
                    </p>
                  </div>
                  <button
                    type="button"
                    class="ml-auto grid size-9 place-items-center rounded-md border border-slate-200 bg-white text-slate-500 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600"
                    aria-label="Close theme customizer"
                    @click="ui.closeThemePanel"
                  >
                    <XMarkIcon class="size-5" />
                  </button>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                  <section class="card-surface overflow-hidden">
                    <header class="flex items-center gap-3 border-b border-slate-200 px-4 py-3">
                      <div>
                        <h2 class="text-sm font-semibold text-slate-700">Header style</h2>
                        <p class="mt-0.5 text-[10px] text-slate-500">
                          Current: {{ selectedTheme('header').label }}
                        </p>
                      </div>
                      <button
                        type="button"
                        class="ml-auto inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 px-3 text-[10px] font-semibold text-slate-600 hover:bg-slate-50"
                        @click="ui.resetTheme('header')"
                      >
                        <ArrowPathIcon class="size-3.5" /> Reset
                      </button>
                    </header>
                    <div class="p-4">
                      <div
                        class="mb-4 h-12 overflow-hidden rounded-md border shadow-sm"
                        :style="{
                          background: selectedTheme('header').tokens.background,
                          borderColor: selectedTheme('header').tokens.border,
                          color: selectedTheme('header').tokens.foreground,
                        }"
                      >
                        <div class="flex h-full items-center gap-2 px-3">
                          <span
                            class="size-5 rounded-full"
                            :style="{ background: selectedTheme('header').tokens.accent }"
                          />
                          <span class="text-[11px] font-semibold">GridPBX workspace</span>
                          <span
                            class="ml-auto text-[10px]"
                            :style="{ color: selectedTheme('header').tokens.muted }"
                            >Account menu</span
                          >
                        </div>
                      </div>
                      <div
                        role="radiogroup"
                        aria-label="Header color scheme"
                        class="grid grid-cols-5 gap-x-3 gap-y-4"
                      >
                        <button
                          v-for="theme in headerThemes"
                          :key="theme.id"
                          type="button"
                          role="radio"
                          :aria-checked="ui.headerTheme === theme.id"
                          :aria-label="`${theme.label} header`"
                          class="group grid justify-items-center gap-1.5 text-[10px] font-medium text-slate-600"
                          @click="ui.setTheme('header', theme.id)"
                        >
                          <span
                            class="grid size-10 place-items-center rounded-full border border-slate-200 shadow-sm transition group-hover:scale-105"
                            :class="
                              ui.headerTheme === theme.id && 'ring-2 ring-brand-500 ring-offset-2'
                            "
                            :style="{ background: theme.swatch }"
                          >
                            <span
                              v-if="ui.headerTheme === theme.id"
                              class="grid size-5 place-items-center rounded-full bg-white text-brand-600 shadow"
                            >
                              <CheckIcon class="size-3.5 stroke-[3]" />
                            </span>
                          </span>
                          <span>{{ theme.label }}</span>
                        </button>
                      </div>
                    </div>
                  </section>

                  <section class="card-surface mt-5 overflow-hidden">
                    <header class="flex items-center gap-3 border-b border-slate-200 px-4 py-3">
                      <div>
                        <h2 class="text-sm font-semibold text-slate-700">Sidebar style</h2>
                        <p class="mt-0.5 text-[10px] text-slate-500">
                          Current: {{ selectedTheme('sidebar').label }}
                        </p>
                      </div>
                      <button
                        type="button"
                        class="ml-auto inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 px-3 text-[10px] font-semibold text-slate-600 hover:bg-slate-50"
                        @click="ui.resetTheme('sidebar')"
                      >
                        <ArrowPathIcon class="size-3.5" /> Reset
                      </button>
                    </header>
                    <div class="p-4">
                      <div
                        class="mb-4 overflow-hidden rounded-md border p-3 shadow-sm"
                        :style="{
                          background: selectedTheme('sidebar').tokens.background,
                          borderColor: selectedTheme('sidebar').tokens.border,
                          color: selectedTheme('sidebar').tokens.foreground,
                        }"
                      >
                        <div class="flex items-center gap-2">
                          <span
                            class="grid size-7 place-items-center rounded text-[10px] font-bold text-white"
                            :style="{ background: selectedTheme('sidebar').tokens.accent }"
                            >G</span
                          >
                          <span class="text-[11px] font-semibold">Navigation preview</span>
                        </div>
                        <div
                          class="mt-3 rounded px-2.5 py-2 text-[10px] font-semibold"
                          :style="{
                            background: selectedTheme('sidebar').tokens.activeBackground,
                            color: selectedTheme('sidebar').tokens.activeForeground,
                          }"
                        >
                          Active workspace
                        </div>
                      </div>
                      <div
                        role="radiogroup"
                        aria-label="Sidebar color scheme"
                        class="grid grid-cols-5 gap-x-3 gap-y-4"
                      >
                        <button
                          v-for="theme in sidebarThemes"
                          :key="theme.id"
                          type="button"
                          role="radio"
                          :aria-checked="ui.sidebarTheme === theme.id"
                          :aria-label="`${theme.label} sidebar`"
                          class="group grid justify-items-center gap-1.5 text-[10px] font-medium text-slate-600"
                          @click="ui.setTheme('sidebar', theme.id)"
                        >
                          <span
                            class="grid size-10 place-items-center rounded-full border border-slate-200 shadow-sm transition group-hover:scale-105"
                            :class="
                              ui.sidebarTheme === theme.id && 'ring-2 ring-brand-500 ring-offset-2'
                            "
                            :style="{ background: theme.swatch }"
                          >
                            <span
                              v-if="ui.sidebarTheme === theme.id"
                              class="grid size-5 place-items-center rounded-full bg-white text-brand-600 shadow"
                            >
                              <CheckIcon class="size-3.5 stroke-[3]" />
                            </span>
                          </span>
                          <span>{{ theme.label }}</span>
                        </button>
                      </div>
                    </div>
                  </section>
                </div>

                <footer class="border-t border-slate-200 bg-white p-4">
                  <button
                    type="button"
                    class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700"
                    @click="ui.resetTheme()"
                  >
                    <ArrowPathIcon class="size-4" /> Restore all defaults
                  </button>
                </footer>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
