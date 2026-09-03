<script setup lang="ts">
import { ref } from 'vue'
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import {
  ArrowDownTrayIcon,
  ArrowPathIcon,
  ArrowPathRoundedSquareIcon,
  ArrowTopRightOnSquareIcon,
  CheckCircleIcon,
  ClipboardDocumentIcon,
  EllipsisVerticalIcon,
  EyeIcon,
  LockClosedIcon,
  LockOpenIcon,
  PencilSquareIcon,
  PlayIcon,
  SquaresPlusIcon,
  TrashIcon,
  UserGroupIcon,
  WrenchScrewdriverIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'
import type { RowAction } from './rowAction'

const props = defineProps<{
  label: string
  actions: RowAction[]
}>()

const emit = defineEmits<{ select: [actionId: string] }>()
const menuPosition = ref({ top: '0px', left: '0px' })
const menuWidth = 176

const iconComponents = {
  view: EyeIcon,
  edit: PencilSquareIcon,
  delete: TrashIcon,
  download: ArrowDownTrayIcon,
  manage: WrenchScrewdriverIcon,
  participants: UserGroupIcon,
  lock: LockClosedIcon,
  unlock: LockOpenIcon,
  enable: CheckCircleIcon,
  disable: XCircleIcon,
  reset: ArrowPathIcon,
  copy: ClipboardDocumentIcon,
  route: ArrowTopRightOnSquareIcon,
  play: PlayIcon,
  'line-keys': SquaresPlusIcon,
  sync: ArrowPathIcon,
  reprovision: ArrowPathRoundedSquareIcon,
}

function positionMenu(event: MouseEvent): void {
  const bounds = (event.currentTarget as HTMLElement).getBoundingClientRect()

  const estimatedHeight = props.actions.length * 36 + 8
  const left = Math.min(window.innerWidth - menuWidth - 8, Math.max(8, bounds.right - menuWidth))
  const below = bounds.bottom + 4
  const top =
    below + estimatedHeight <= window.innerHeight - 8
      ? below
      : Math.max(8, bounds.top - estimatedHeight - 4)

  menuPosition.value = { top: `${top}px`, left: `${left}px` }
}
</script>

<template>
  <Menu as="div" class="relative inline-flex text-left">
    <MenuButton
      type="button"
      :aria-label="label"
      class="grid size-8 place-items-center rounded-full text-slate-400 transition hover:bg-brand-50 hover:text-brand-600 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-500"
      @click.stop="positionMenu"
    >
      <EllipsisVerticalIcon class="size-5" aria-hidden="true" />
    </MenuButton>

    <Teleport to="body">
      <MenuItems
        data-testid="row-action-menu"
        class="fixed z-[70] w-44 rounded-md border border-slate-200 bg-white p-1 shadow-lg focus:outline-none"
        :style="menuPosition"
        @click.stop
      >
        <MenuItem
          v-for="action in actions"
          :key="action.id"
          v-slot="{ active, disabled }"
          :disabled="action.disabled"
        >
          <button
            type="button"
            class="flex h-9 w-full items-center gap-2.5 rounded px-2.5 text-left text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
            :class="[
              action.destructive ? 'text-red-700' : 'text-slate-700',
              active && (action.destructive ? 'bg-red-50' : 'bg-brand-50 text-brand-700'),
            ]"
            :disabled="disabled"
            @click="emit('select', action.id)"
          >
            <component
              :is="iconComponents[action.icon]"
              class="size-4 shrink-0"
              aria-hidden="true"
            />
            <span>{{ action.label }}</span>
          </button>
        </MenuItem>
      </MenuItems>
    </Teleport>
  </Menu>
</template>
