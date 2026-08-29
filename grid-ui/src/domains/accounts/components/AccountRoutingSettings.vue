<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronDownIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useDelimitedStringList } from '@/shared/forms/useDelimitedStringList'
import { validationControlClass } from '@/shared/forms/validationStyles'
import AccountFormatterSettings from './AccountFormatterSettings.vue'
import type { AccountDialPlan, AccountFormatter } from '../types/account'

const props = defineProps<{ fieldErrors: Record<string, string[]> }>()
const dialPlan = defineModel<AccountDialPlan>('dialPlan', { required: true })
const formatters = defineModel<AccountFormatter[]>('formatters', { required: true })
const systemDialPlans = useDelimitedStringList(
  () => dialPlan.value.system,
  (values) => (dialPlan.value.system = values),
)

function error(field: string): string | null {
  return props.fieldErrors[field]?.[0] ?? null
}

function addRule(): void {
  dialPlan.value.rules.push({ pattern: '', description: '', prefix: '', suffix: '' })
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="border-b border-slate-200 px-5 py-4">
      <h2 class="text-sm font-semibold text-slate-700">Dial plan and formatters</h2>
      <p class="mt-1 text-[10px] leading-4 text-slate-500">
        Advanced transformations are validated and written as typed Switch objects.
      </p>
    </header>
    <div class="grid gap-3 p-5">
      <Disclosure v-slot="{ open }">
        <div class="rounded-md border border-slate-200">
          <DisclosureButton
            class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
          >
            Dial plan
            <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
          </DisclosureButton>
          <DisclosurePanel class="grid gap-4 border-t border-slate-200 p-4">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">System dial plans</span>
              <input
                v-model="systemDialPlans"
                class="field-control"
                :class="validationControlClass(error('dial_plan.system'))"
                :aria-invalid="Boolean(error('dial_plan.system'))"
                placeholder="System plan names, comma separated"
              />
              <span v-if="error('dial_plan.system')" class="text-[10px] text-danger">
                {{ error('dial_plan.system') }}
              </span>
            </label>
            <div class="flex items-center justify-between gap-3">
              <p class="text-[10px] text-slate-500">Rules modify locally dialed numbers.</p>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                @click="addRule"
              >
                <PlusIcon class="size-3.5" /> Add rule
              </button>
            </div>
            <p
              v-if="dialPlan.rules.length === 0"
              class="rounded-md bg-slate-50 p-4 text-xs text-slate-500"
            >
              No account dial-plan rules configured.
            </p>
            <section
              v-for="(rule, index) in dialPlan.rules"
              :key="index"
              class="grid gap-3 rounded-md border border-slate-200 p-4 sm:grid-cols-2"
            >
              <label class="grid gap-2 sm:col-span-2">
                <span class="text-xs font-semibold text-slate-600">Regex pattern</span>
                <input
                  v-model="rule.pattern"
                  maxlength="512"
                  class="field-control font-mono"
                  :class="validationControlClass(error(`dial_plan.rules.${index}.pattern`))"
                  :aria-invalid="Boolean(error(`dial_plan.rules.${index}.pattern`))"
                  placeholder="^([2-9][0-9]{6})$"
                />
                <span
                  v-if="error(`dial_plan.rules.${index}.pattern`)"
                  class="text-[10px] text-danger"
                  >{{ error(`dial_plan.rules.${index}.pattern`) }}</span
                >
              </label>
              <label class="grid gap-2 sm:col-span-2">
                <span class="text-xs font-semibold text-slate-600">Description</span>
                <input
                  v-model="rule.description"
                  maxlength="255"
                  class="field-control"
                  :class="validationControlClass(error(`dial_plan.rules.${index}.description`))"
                  :aria-invalid="Boolean(error(`dial_plan.rules.${index}.description`))"
                />
                <span
                  v-if="error(`dial_plan.rules.${index}.description`)"
                  class="text-[10px] text-danger"
                >
                  {{ error(`dial_plan.rules.${index}.description`) }}
                </span>
              </label>
              <label v-for="key in ['prefix', 'suffix'] as const" :key="key" class="grid gap-2">
                <span class="text-xs font-semibold capitalize text-slate-600">{{ key }}</span>
                <input
                  v-model="rule[key]"
                  maxlength="64"
                  class="field-control"
                  :class="validationControlClass(error(`dial_plan.rules.${index}.${key}`))"
                  :aria-invalid="Boolean(error(`dial_plan.rules.${index}.${key}`))"
                />
                <span
                  v-if="error(`dial_plan.rules.${index}.${key}`)"
                  class="text-[10px] text-danger"
                >
                  {{ error(`dial_plan.rules.${index}.${key}`) }}
                </span>
              </label>
              <button
                type="button"
                class="inline-flex items-center justify-center gap-1 rounded-md border border-red-200 px-3 py-2 text-[11px] font-semibold text-danger hover:bg-red-50 sm:col-span-2"
                @click="dialPlan.rules.splice(index, 1)"
              >
                <TrashIcon class="size-3.5" /> Remove rule
              </button>
            </section>
          </DisclosurePanel>
        </div>
      </Disclosure>

      <Disclosure v-slot="{ open }">
        <div class="rounded-md border border-slate-200">
          <DisclosureButton
            class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
          >
            Request formatters
            <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
          </DisclosureButton>
          <DisclosurePanel class="grid gap-4 border-t border-slate-200 p-4">
            <AccountFormatterSettings v-model="formatters" :field-errors="fieldErrors" />
          </DisclosurePanel>
        </div>
      </Disclosure>
    </div>
  </article>
</template>
