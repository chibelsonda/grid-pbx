<script setup lang="ts">
import { computed } from 'vue'
import {
  ArrowPathRoundedSquareIcon,
  IdentificationIcon,
  KeyIcon,
  MicrophoneIcon,
  MusicalNoteIcon,
  PhoneArrowUpRightIcon,
  ShieldCheckIcon,
  ShieldExclamationIcon,
  SparklesIcon,
  UserCircleIcon,
} from '@heroicons/vue/24/outline'
import FormTabBar from '@/shared/components/FormTabBar.vue'
import type { ExtensionAdvancedSection } from '../extensionAdvancedSections'

const props = withDefaults(defineProps<{ extended?: boolean }>(), { extended: false })
const selectedSection = defineModel<ExtensionAdvancedSection>({ default: 'options' })
const tabs = computed(() => [
  { key: 'caller-id', label: 'Caller ID', icon: IdentificationIcon },
  { key: 'options', label: 'Options', icon: ShieldCheckIcon },
  { key: 'call-forward', label: 'Call Forward', icon: PhoneArrowUpRightIcon },
  { key: 'password', label: 'Password Management', icon: KeyIcon },
  { key: 'hot-desking', label: 'Hot Desking', icon: UserCircleIcon },
  { key: 'restrictions', label: 'Restrictions', icon: ShieldExclamationIcon },
  { key: 'recording', label: 'Recording', icon: MicrophoneIcon },
  ...(props.extended
    ? [
        { key: 'media', label: 'Media', icon: MusicalNoteIcon },
        {
          key: 'routing-profile',
          label: 'Routing & Profile',
          icon: ArrowPathRoundedSquareIcon,
        },
        { key: 'metaflows', label: 'Metaflows', icon: SparklesIcon },
      ]
    : []),
])
const selectedIndex = computed({
  get: () =>
    Math.max(
      0,
      tabs.value.findIndex(({ key }) => key === selectedSection.value),
    ),
  set: (index: number) => {
    const tab = tabs.value[index]
    if (tab) selectedSection.value = tab.key as ExtensionAdvancedSection
  },
})
</script>

<template>
  <FormTabBar
    v-model="selectedIndex"
    :tabs="tabs"
    aria-label="Extension advanced sections"
    compact
    sticky
  />
</template>
