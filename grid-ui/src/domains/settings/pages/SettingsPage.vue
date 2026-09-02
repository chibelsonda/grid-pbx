<script setup lang="ts">
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowRightStartOnRectangleIcon,
  ArrowTopRightOnSquareIcon,
  BuildingOffice2Icon,
  ComputerDesktopIcon,
  PhotoIcon,
  RectangleGroupIcon,
  ShieldCheckIcon,
  SignalIcon,
  SquaresPlusIcon,
  SwatchIcon,
  TrashIcon,
  UserCircleIcon,
} from '@heroicons/vue/24/outline'
import { sidebarBrandDisplaySchema, useUiStore } from '@/app/stores/uiStore'
import { findApplicationTheme, findShellTheme } from '@/app/theme/themeCatalog'
import { accountRoleLabel } from '@/domains/accounts/accountRole'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import type { Account } from '@/domains/accounts/types/account'
import { profileFormSchema } from '@/domains/auth/schemas/profileFormSchema'
import { useAuthStore } from '@/domains/auth/stores/authStore'
import CallflowIntegrationProfilesPanel from '@/domains/call-routing/components/CallflowIntegrationProfilesPanel.vue'
import { organizationLogoSchema } from '@/domains/settings/schemas/organizationLogoSchema'
import FormFileInput from '@/shared/components/FormFileInput.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const accounts = useAccountStore()
const ui = useUiStore()
const editingProfile = ref(false)
const profileName = ref(auth.user?.name ?? '')
const profileValidationErrors = ref<FormErrors>({})
const organizationLogo = ref<File | null>(null)
const organizationLogoErrors = ref<FormErrors>({})
const organizationLogoInputKey = ref(0)
const confirmingLogoRemoval = ref(false)
const activeSettingsIndex = ref(0)

const settingsSections = [
  { id: 'profile', label: 'Profile', icon: UserCircleIcon },
  { id: 'organization-branding', label: 'Branding', icon: PhotoIcon },
  { id: 'appearance', label: 'Appearance', icon: SwatchIcon },
  { id: 'workspace-preferences', label: 'Workspace', icon: ComputerDesktopIcon },
  { id: 'administration', label: 'Administration', icon: RectangleGroupIcon },
  { id: 'callflow-integrations', label: 'Callflow integrations', icon: SquaresPlusIcon },
  { id: 'access-security', label: 'Access & security', icon: ShieldCheckIcon },
] as const

const permissionDefinitions: Array<{
  key: keyof Account['permissions']
  label: string
}> = [
  { key: 'can_manage_extensions', label: 'People & extensions' },
  { key: 'can_manage_devices', label: 'Devices' },
  { key: 'can_manage_voicemail', label: 'Voicemail' },
  { key: 'can_manage_call_routing', label: 'Call routing' },
  { key: 'can_manage_media', label: 'Media' },
  { key: 'can_sync_call_detail_records', label: 'Call history sync' },
  { key: 'can_view_services', label: 'Services' },
  { key: 'can_manage_account_settings', label: 'Account settings' },
  { key: 'can_onboard_descendants', label: 'Descendant onboarding' },
]

const initials = computed(() =>
  (auth.user?.name ?? 'Grid Admin')
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase(),
)
const accountOptions = computed<ListboxOptionValue[]>(() =>
  accounts.accounts.length
    ? accounts.accounts.map((account) => ({
        value: account.id,
        label: account.name,
        description: account.enabled ? account.organization.name : 'Disabled',
        disabled: !account.enabled,
      }))
    : [{ value: null, label: 'No mapped account', disabled: true }],
)
const sidebarBrandDisplayOptions: ListboxOptionValue[] = [
  {
    value: 'logo-and-name',
    label: 'Logo and company name',
    description: 'Show the selected organization name beside its logo.',
  },
  {
    value: 'logo-only',
    label: 'Logo only',
    description: 'Hide the company name and phone-system subtitle.',
  },
]
const selectedAccount = computed(() => accounts.selected)
const grantedPermissions = computed(() => {
  const account = selectedAccount.value
  return account ? permissionDefinitions.filter(({ key }) => account.permissions[key]) : []
})
const headerTheme = computed(() => findShellTheme('header', ui.headerTheme))
const sidebarTheme = computed(() => findShellTheme('sidebar', ui.sidebarTheme))
const applicationTheme = computed(() => findApplicationTheme(ui.applicationTheme))
const profileNameError = computed(
  () => profileValidationErrors.value.name?.[0] ?? auth.profileFieldErrors.name?.[0] ?? null,
)
const organizationLogoError = computed(
  () => organizationLogoErrors.value.logo?.[0] ?? accounts.organizationLogoError,
)

function selectSidebarBrandDisplay(value: ListboxValue): void {
  const result = sidebarBrandDisplaySchema.safeParse(value)

  if (result.success) ui.setSidebarBrandDisplay(result.data)
}

watch(profileName, () => {
  profileValidationErrors.value = {}
  auth.clearProfileError()
})
watch(
  () => auth.user?.name,
  (name) => {
    if (!editingProfile.value) profileName.value = name ?? ''
  },
)
watch(
  () => selectedAccount.value?.id,
  () => {
    organizationLogo.value = null
    organizationLogoErrors.value = {}
    accounts.organizationLogoError = null
    confirmingLogoRemoval.value = false
    organizationLogoInputKey.value += 1
  },
)
watch(
  () => route.hash,
  (hash) => {
    const sectionIndex = settingsSections.findIndex(({ id }) => `#${id}` === hash)
    if (sectionIndex >= 0) activeSettingsIndex.value = sectionIndex
  },
  { immediate: true },
)

function selectSettingsSection(index: number): void {
  activeSettingsIndex.value = index
  const section = settingsSections[index] ?? settingsSections[0]
  void router.replace({ hash: `#${section.id}` })
}

function selectAccount(value: ListboxValue): void {
  accounts.select(typeof value === 'string' ? value : null)
}

function startProfileEdit(): void {
  profileName.value = auth.user?.name ?? ''
  profileValidationErrors.value = {}
  auth.clearProfileError()
  editingProfile.value = true
}

function cancelProfileEdit(): void {
  editingProfile.value = false
  profileName.value = auth.user?.name ?? ''
  profileValidationErrors.value = {}
  auth.clearProfileError()
}

async function saveProfile(): Promise<void> {
  const result = validateForm(profileFormSchema, { name: profileName.value })
  profileValidationErrors.value = result.errors
  if (!result.success) return

  if (await auth.updateProfile(result.data)) editingProfile.value = false
}

function selectOrganizationLogo(file: File | null): void {
  organizationLogo.value = file
  organizationLogoErrors.value = {}
  accounts.organizationLogoError = null
}

async function uploadOrganizationLogo(): Promise<void> {
  const result = validateForm(organizationLogoSchema, { logo: organizationLogo.value })
  organizationLogoErrors.value = result.errors
  if (!result.success) return

  if (await accounts.uploadOrganizationLogo(result.data.logo)) {
    organizationLogo.value = null
    organizationLogoInputKey.value += 1
  }
}

async function removeOrganizationLogo(): Promise<void> {
  if (!confirmingLogoRemoval.value) {
    confirmingLogoRemoval.value = true
    return
  }

  if (await accounts.removeOrganizationLogo()) confirmingLogoRemoval.value = false
}

async function signOut(): Promise<void> {
  await auth.logout()
  accounts.reset()
  await router.push({ name: 'login' })
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container">
      <p class="mb-1 text-[11px] font-medium text-slate-500">GridPBX / Settings</p>
      <h1 class="text-xl font-semibold tracking-tight text-slate-800">Settings</h1>
      <p class="mt-1 text-xs text-slate-600">
        Personal identity, browser appearance, workspace defaults, and safe administration links.
      </p>
    </div>
  </section>

  <div class="page-container py-4 sm:py-6 lg:py-8">
    <TabGroup
      as="div"
      vertical
      :selected-index="activeSettingsIndex"
      @change="selectSettingsSection"
    >
      <div class="grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-start">
        <nav
          aria-label="Settings sections"
          class="card-surface overflow-hidden lg:sticky lg:top-20"
        >
          <div class="border-b border-slate-200 px-4 py-3">
            <p class="text-xs font-semibold text-slate-700">Settings sections</p>
            <p class="mt-0.5 text-[10px] text-slate-500">Choose a category.</p>
          </div>
          <TabList class="flex gap-1 overflow-x-auto p-3 lg:flex-col">
            <Tab v-for="(section, index) in settingsSections" :key="section.id" as="template">
              <button
                type="button"
                class="flex h-10 shrink-0 items-center gap-3 rounded-md px-3 text-left text-[12px] font-semibold transition-colors lg:w-full"
                :class="
                  index === activeSettingsIndex
                    ? 'bg-brand-50 text-brand-700'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800'
                "
              >
                <component :is="section.icon" class="size-[18px] shrink-0" />
                <span>{{ section.label }}</span>
              </button>
            </Tab>
          </TabList>
        </nav>

        <TabPanels class="min-w-0">
          <TabPanel
            v-show="activeSettingsIndex === 0"
            as="article"
            id="profile"
            class="card-surface overflow-hidden focus:outline-none"
          >
            <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
              <UserCircleIcon class="size-5 text-brand-500" />
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Profile</h2>
                <p class="mt-0.5 text-[10px] text-slate-500">Your GridPBX application identity.</p>
              </div>
              <button
                v-if="!editingProfile"
                type="button"
                aria-label="Edit display name"
                class="ml-auto rounded-md border border-slate-200 px-3 py-1.5 text-[10px] font-semibold text-slate-600 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700"
                @click="startProfileEdit"
              >
                Edit name
              </button>
            </header>
            <div v-if="!editingProfile" class="flex items-center gap-4 p-5">
              <span
                class="grid size-12 shrink-0 place-items-center rounded-full bg-brand-500 text-sm font-bold text-white shadow-sm"
              >
                {{ initials }}
              </span>
              <dl class="min-w-0">
                <dt class="sr-only">Name</dt>
                <dd class="truncate text-sm font-semibold text-slate-800">
                  {{ auth.user?.name ?? 'Grid Admin' }}
                </dd>
                <dt class="sr-only">Email</dt>
                <dd class="mt-1 truncate text-xs text-slate-500">
                  {{ auth.user?.email ?? 'Email unavailable' }}
                </dd>
              </dl>
            </div>
            <form
              v-else
              aria-label="Edit profile"
              class="grid gap-4 p-5"
              novalidate
              @submit.prevent="saveProfile"
            >
              <FormInput
                v-model="profileName"
                name="name"
                label="Display name"
                description="Used in the GridPBX header and application activity. It does not rename a Switch user or extension."
                :error="profileNameError"
                :disabled="auth.profileSaving"
                required
                autocomplete="name"
              />
              <p
                v-if="auth.profileError"
                role="alert"
                class="rounded-md border border-red-100 bg-red-50 px-3 py-2 text-[10px] text-danger"
              >
                {{ auth.profileError }}
              </p>
              <div class="flex flex-wrap gap-2">
                <button
                  type="submit"
                  :disabled="auth.profileSaving"
                  class="h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:cursor-wait disabled:opacity-60"
                >
                  {{ auth.profileSaving ? 'Saving…' : 'Save name' }}
                </button>
                <button
                  type="button"
                  :disabled="auth.profileSaving"
                  class="h-9 rounded-md border border-slate-200 px-4 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-60"
                  @click="cancelProfileEdit"
                >
                  Cancel
                </button>
              </div>
            </form>
            <p class="border-t border-slate-100 px-5 py-3 text-[10px] leading-4 text-slate-500">
              Your login email remains read-only. Email verification, password changes, MFA, and
              session management require separate security contracts.
            </p>
          </TabPanel>

          <TabPanel
            v-show="activeSettingsIndex === 1"
            as="article"
            id="organization-branding"
            class="card-surface overflow-hidden focus:outline-none"
          >
            <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
              <PhotoIcon class="size-5 text-brand-500" />
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Organization branding</h2>
                <p class="mt-0.5 text-[10px] text-slate-500">
                  A private GridPBX logo for the selected organization.
                </p>
              </div>
            </header>
            <div v-if="selectedAccount" class="grid gap-5 p-5">
              <div
                class="flex min-w-0 items-center gap-4 rounded-md border border-slate-200 bg-slate-50 p-4"
              >
                <span
                  class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white p-2"
                >
                  <img
                    v-if="accounts.organizationLogoUrl"
                    :src="accounts.organizationLogoUrl"
                    :alt="`${selectedAccount.organization.name} logo`"
                    class="max-h-full max-w-full object-contain"
                  />
                  <PhotoIcon v-else class="size-7 text-slate-300" />
                </span>
                <div class="min-w-0">
                  <p class="truncate text-xs font-semibold text-slate-700">
                    {{ selectedAccount.organization.name }}
                  </p>
                  <p class="mt-1 text-[10px] leading-4 text-slate-500">
                    {{
                      selectedAccount.organization.branding?.logo_available
                        ? 'Custom logo is active in the GridPBX sidebar.'
                        : 'The default GridPBX mark is currently used.'
                    }}
                  </p>
                </div>
              </div>

              <form
                v-if="selectedAccount.permissions.can_manage_account_settings"
                aria-label="Organization branding"
                class="grid gap-4"
                novalidate
                @submit.prevent="uploadOrganizationLogo"
              >
                <FormFileInput
                  :key="organizationLogoInputKey"
                  :model-value="organizationLogo"
                  label="Logo image"
                  accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp"
                  description="PNG, JPEG, or WebP; 2 MB maximum; 32–2048 pixels per side. GridPBX sanitizes and resizes the image before private storage."
                  :error="organizationLogoError"
                  :disabled="accounts.organizationLogoSaving"
                  dropzone
                  drop-prompt="Drag and drop your logo here"
                  required
                  @update:model-value="selectOrganizationLogo"
                />
                <div class="flex flex-wrap gap-2">
                  <button
                    type="submit"
                    :disabled="accounts.organizationLogoSaving"
                    class="h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:cursor-wait disabled:opacity-60"
                  >
                    {{
                      accounts.organizationLogoSaving
                        ? 'Saving…'
                        : selectedAccount.organization.branding?.logo_available
                          ? 'Update logo'
                          : 'Upload logo'
                    }}
                  </button>
                  <button
                    v-if="selectedAccount.organization.branding?.logo_available"
                    type="button"
                    :disabled="accounts.organizationLogoSaving"
                    class="inline-flex h-9 items-center gap-2 rounded-md border border-red-200 px-4 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60"
                    @click="removeOrganizationLogo"
                  >
                    <TrashIcon class="size-4" />
                    {{ confirmingLogoRemoval ? 'Confirm removal' : 'Remove logo' }}
                  </button>
                  <button
                    v-if="confirmingLogoRemoval"
                    type="button"
                    class="h-9 rounded-md border border-slate-200 px-4 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                    @click="confirmingLogoRemoval = false"
                  >
                    Cancel
                  </button>
                </div>
              </form>
              <p v-else class="text-xs leading-5 text-slate-500">
                You can view this organization logo, but your current role cannot replace or remove
                it.
              </p>
              <p class="text-[10px] leading-4 text-slate-500">
                This changes GridPBX application branding only. It does not modify Switch/Kazoo
                whitelabel settings.
              </p>
            </div>
            <p v-else class="p-5 text-xs text-slate-500">
              Select a mapped account to view its organization branding.
            </p>
          </TabPanel>

          <TabPanel
            v-show="activeSettingsIndex === 2"
            as="article"
            id="appearance"
            class="card-surface overflow-hidden focus:outline-none"
          >
            <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
              <SwatchIcon class="size-5 text-brand-500" />
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Appearance</h2>
                <p class="mt-0.5 text-[10px] text-slate-500">
                  Stored only in this browser; no account configuration is changed.
                </p>
              </div>
            </header>
            <div class="grid gap-4 p-5 sm:grid-cols-3">
              <div class="rounded-md border border-slate-200 p-4">
                <div class="flex items-center gap-3">
                  <span class="flex shrink-0 -space-x-2">
                    <span
                      v-for="swatch in applicationTheme.swatches"
                      :key="swatch"
                      class="size-7 rounded-full border-2 border-white shadow-sm"
                      :style="{ background: swatch }"
                    />
                  </span>
                  <div>
                    <p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                      Application
                    </p>
                    <p class="mt-0.5 text-xs font-semibold text-slate-700">
                      {{ applicationTheme.label }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="rounded-md border border-slate-200 p-4">
                <div class="flex items-center gap-3">
                  <span
                    class="size-8 rounded-full border border-slate-200 shadow-sm"
                    :style="{ background: headerTheme.swatch }"
                  />
                  <div>
                    <p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                      Header
                    </p>
                    <p class="mt-0.5 text-xs font-semibold text-slate-700">
                      {{ headerTheme.label }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="rounded-md border border-slate-200 p-4">
                <div class="flex items-center gap-3">
                  <span
                    class="size-8 rounded-full border border-slate-200 shadow-sm"
                    :style="{ background: sidebarTheme.swatch }"
                  />
                  <div>
                    <p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                      Sidebar
                    </p>
                    <p class="mt-0.5 text-xs font-semibold text-slate-700">
                      {{ sidebarTheme.label }}
                    </p>
                  </div>
                </div>
              </div>
              <button
                type="button"
                aria-label="Customize appearance"
                class="h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 sm:col-span-3 sm:justify-self-start"
                @click="ui.openThemePanel"
              >
                Customize appearance
              </button>
            </div>
          </TabPanel>

          <TabPanel
            v-show="activeSettingsIndex === 3"
            as="article"
            id="workspace-preferences"
            class="card-surface overflow-hidden focus:outline-none"
          >
            <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
              <ComputerDesktopIcon class="size-5 text-brand-500" />
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Workspace preferences</h2>
                <p class="mt-0.5 text-[10px] text-slate-500">
                  Browser-local defaults for the GridPBX shell.
                </p>
              </div>
            </header>
            <div class="grid gap-5 p-5">
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Current workspace account</span>
                <FormListbox
                  :model-value="accounts.selectedId"
                  :options="accountOptions"
                  aria-label="Settings workspace account"
                  class="w-full max-w-xl"
                  placeholder="Select a mapped account"
                  @update:model-value="selectAccount"
                />
                <span class="text-[10px] leading-4 text-slate-400">
                  The selected public account reference is restored in this browser on your next
                  visit.
                </span>
              </label>
              <div class="border-t border-slate-100 pt-5">
                <label class="mb-5 grid gap-2">
                  <span class="text-xs font-semibold text-slate-600">Sidebar branding</span>
                  <FormListbox
                    :model-value="ui.sidebarBrandDisplay"
                    :options="sidebarBrandDisplayOptions"
                    aria-label="Sidebar branding display"
                    class="w-full max-w-md"
                    @update:model-value="selectSidebarBrandDisplay"
                  />
                  <span class="text-[10px] leading-4 text-slate-400">
                    Choose whether the expanded sidebar shows only the logo or also the selected
                    organization name.
                  </span>
                </label>
                <ToggleSwitch
                  :model-value="ui.sidebarCollapsed"
                  label="Use compact desktop sidebar"
                  description="Start with the 80px icon rail and keep this preference in this browser."
                  @update:model-value="ui.setSidebarCollapsed"
                />
              </div>
            </div>
          </TabPanel>

          <TabPanel
            v-show="activeSettingsIndex === 4"
            as="section"
            id="administration"
            class="card-surface overflow-hidden focus:outline-none"
          >
            <header class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-sm font-semibold text-slate-700">Administration</h2>
              <p class="mt-1 text-[10px] text-slate-500">
                Account-wide PBX settings and operational diagnostics remain in their owning
                domains.
              </p>
            </header>
            <div class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
              <RouterLink
                to="/accounts"
                class="group flex items-start gap-3 rounded-md border border-slate-200 p-4 hover:border-brand-200 hover:bg-brand-50/50"
              >
                <BuildingOffice2Icon class="size-5 shrink-0 text-brand-500" />
                <span class="min-w-0 flex-1">
                  <span class="block text-xs font-semibold text-slate-700"
                    >Account configuration</span
                  >
                  <span class="mt-1 block text-[10px] leading-4 text-slate-500">
                    Caller ID, restrictions, recording defaults, routing, locale, and account
                    status.
                  </span>
                </span>
                <ArrowTopRightOnSquareIcon class="size-4 shrink-0 text-slate-400" />
              </RouterLink>
              <RouterLink
                to="/system-status"
                class="group flex items-start gap-3 rounded-md border border-slate-200 p-4 hover:border-brand-200 hover:bg-brand-50/50"
              >
                <SignalIcon class="size-5 shrink-0 text-brand-500" />
                <span class="min-w-0 flex-1">
                  <span class="block text-xs font-semibold text-slate-700">System status</span>
                  <span class="mt-1 block text-[10px] leading-4 text-slate-500">
                    Projection health, connected-service capability, and safe recovery guidance.
                  </span>
                </span>
                <ArrowTopRightOnSquareIcon class="size-4 shrink-0 text-slate-400" />
              </RouterLink>
              <RouterLink
                v-if="selectedAccount?.permissions.can_onboard_descendants"
                to="/reseller"
                class="group flex items-start gap-3 rounded-md border border-slate-200 p-4 hover:border-brand-200 hover:bg-brand-50/50"
              >
                <RectangleGroupIcon class="size-5 shrink-0 text-brand-500" />
                <span class="min-w-0 flex-1">
                  <span class="block text-xs font-semibold text-slate-700">
                    Reseller administration
                  </span>
                  <span class="mt-1 block text-[10px] leading-4 text-slate-500">
                    Review account hierarchy coverage and authorized descendant onboarding.
                  </span>
                </span>
                <ArrowTopRightOnSquareIcon class="size-4 shrink-0 text-slate-400" />
              </RouterLink>
            </div>
          </TabPanel>

          <TabPanel
            v-show="activeSettingsIndex === 5"
            as="section"
            id="callflow-integrations"
            class="card-surface overflow-hidden focus:outline-none"
          >
            <CallflowIntegrationProfilesPanel
              :account-id="selectedAccount?.id ?? null"
              :can-manage="Boolean(selectedAccount?.permissions.can_manage_account_settings)"
            />
          </TabPanel>

          <TabPanel
            v-show="activeSettingsIndex === 6"
            as="article"
            id="access-security"
            class="card-surface overflow-hidden focus:outline-none"
          >
            <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
              <ShieldCheckIcon class="size-5 text-brand-500" />
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Access and security</h2>
                <p class="mt-0.5 text-[10px] text-slate-500">
                  Account-scoped access from the authenticated session.
                </p>
              </div>
            </header>
            <div class="grid gap-4 p-5">
              <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                <p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                  Current role
                </p>
                <p class="mt-1 text-sm font-semibold text-slate-700">
                  {{ accountRoleLabel(selectedAccount?.organization_role) }}
                </p>
                <p v-if="selectedAccount" class="mt-1 text-[10px] text-slate-500">
                  {{ selectedAccount.name }} · {{ grantedPermissions.length }} granted capabilities
                </p>
              </div>
              <div v-if="grantedPermissions.length" class="flex flex-wrap gap-2">
                <span
                  v-for="permission in grantedPermissions"
                  :key="permission.key"
                  class="rounded-full bg-brand-50 px-2.5 py-1 text-[10px] font-semibold text-brand-700"
                >
                  {{ permission.label }}
                </span>
              </div>
              <p v-else class="text-xs text-slate-500">
                Select a mapped account to review its granted capabilities.
              </p>
              <button
                type="button"
                class="inline-flex h-9 w-fit items-center gap-2 rounded-md border border-red-200 px-4 text-xs font-semibold text-red-700 hover:bg-red-50"
                @click="signOut"
              >
                <ArrowRightStartOnRectangleIcon class="size-4" /> Sign out
              </button>
            </div>
          </TabPanel>
        </TabPanels>
      </div>
    </TabGroup>
  </div>
</template>
