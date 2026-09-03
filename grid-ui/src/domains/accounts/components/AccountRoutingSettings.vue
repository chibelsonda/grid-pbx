<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronDownIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import { useDelimitedStringList } from '@/shared/forms/useDelimitedStringList'
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
      <p class="mt-1 text-[10px] leading-4 text-heading-description">
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
            <FormInput
              v-model="systemDialPlans"
              label="System dial plans"
              placeholder="System plan names, comma separated"
              :error="error('dial_plan.system')"
            />
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
              <FormInput
                v-model="rule.pattern"
                label="Regex pattern"
                class="sm:col-span-2"
                maxlength="512"
                input-class="font-mono"
                placeholder="^([2-9][0-9]{6})$"
                :error="error(`dial_plan.rules.${index}.pattern`)"
              />
              <FormInput
                v-model="rule.description"
                label="Description"
                class="sm:col-span-2"
                maxlength="255"
                :error="error(`dial_plan.rules.${index}.description`)"
              />
              <FormInput
                v-for="key in ['prefix', 'suffix'] as const"
                :key="key"
                v-model="rule[key]"
                :label="key"
                class="capitalize"
                maxlength="64"
                :error="error(`dial_plan.rules.${index}.${key}`)"
              />
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
