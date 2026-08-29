<script setup lang="ts">
import { computed } from 'vue'
import { LockClosedIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import type { ExtensionCredentialsInput } from '../types/extension'

const props = withDefaults(
  defineProps<{
    fieldErrors: Record<string, string[]>
    editing?: boolean
    originalUsername?: string | null
    passwordConfigured?: boolean
  }>(),
  { editing: false, originalUsername: null, passwordConfigured: false },
)
const credentials = defineModel<ExtensionCredentialsInput>({ required: true })
const hasUsername = computed(() => Boolean(credentials.value.username?.trim()))
const needsNewPassword = computed(
  () =>
    hasUsername.value &&
    (!props.editing ||
      credentials.value.username?.toLocaleLowerCase() !==
        props.originalUsername?.toLocaleLowerCase()),
)

function onUsernameInput(): void {
  if (hasUsername.value) {
    credentials.value.clear_credentials = false
  } else {
    credentials.value.require_password_update = false
    credentials.value.password = null
    credentials.value.password_confirmation = null
  }
}

function removeCredentials(): void {
  credentials.value.username = null
  credentials.value.password = null
  credentials.value.password_confirmation = null
  credentials.value.require_password_update = false
  credentials.value.clear_credentials = true
}

function keepCredentials(): void {
  credentials.value.username = props.originalUsername
  credentials.value.clear_credentials = false
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-emerald-50 text-emerald-600">
        <LockClosedIcon class="size-5" />
      </span>
      <div class="min-w-0 flex-1">
        <h2 class="text-sm font-semibold text-slate-700">Switch portal login</h2>
        <p class="text-[10px] text-slate-400">
          Optional credentials for this user to sign in to Switch applications.
        </p>
      </div>
      <span
        v-if="editing && passwordConfigured && !credentials.clear_credentials"
        class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700"
      >
        Configured
      </span>
    </header>

    <div v-if="credentials.clear_credentials" class="grid gap-3 p-5">
      <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700">
        <p class="font-semibold">Login credentials will be removed.</p>
        <p class="mt-1 text-[10px] leading-4 text-red-600">
          Saving removes the username and its derived authentication hashes from Switch.
        </p>
      </div>
      <button
        type="button"
        class="justify-self-start text-xs font-semibold text-brand-600 hover:text-brand-700"
        @click="keepCredentials"
      >
        Keep configured login
      </button>
      <span v-if="fieldErrors.clear_credentials" class="text-[10px] text-danger">
        {{ fieldErrors.clear_credentials[0] }}
      </span>
    </div>

    <div v-else class="grid gap-4 p-5 sm:grid-cols-2">
      <FormInput
        v-model="credentials.username"
        label="Login username"
        class="sm:col-span-2"
        maxlength="256"
        autocomplete="username"
        placeholder="alice.operator"
        description="Leave blank on creation when this person should not have a Switch portal login."
        :error="fieldErrors.username"
        @input="onUsernameInput"
      />

      <template v-if="hasUsername">
        <FormInput
          v-model="credentials.password"
          :label="editing ? 'New password' : 'Password'"
          type="password"
          minlength="6"
          maxlength="256"
          autocomplete="new-password"
          :description="editing && !needsNewPassword ? 'Optional when unchanged.' : null"
          :error="fieldErrors.password"
        />

        <FormInput
          v-model="credentials.password_confirmation"
          label="Confirm password"
          type="password"
          maxlength="256"
          autocomplete="new-password"
          :error="fieldErrors.password_confirmation"
        />

        <div
          class="rounded-md border border-slate-200 px-3 py-2.5 sm:col-span-2"
          :class="fieldErrors.require_password_update && 'border-danger'"
        >
          <ToggleSwitch
            v-model="credentials.require_password_update"
            label="Require password change on next login"
            description="Switch asks the user to replace this password after signing in"
            :invalid="Boolean(fieldErrors.require_password_update)"
          />
          <span
            v-if="fieldErrors.require_password_update"
            class="mt-2 block text-[10px] text-danger"
          >
            {{ fieldErrors.require_password_update[0] }}
          </span>
        </div>

        <p class="text-[10px] leading-4 text-slate-400 sm:col-span-2">
          Passwords are sent once to Switch, hashed upstream, and are never returned to GridPBX.
        </p>
      </template>

      <button
        v-if="editing && passwordConfigured"
        type="button"
        class="justify-self-start text-xs font-semibold text-danger hover:text-red-700 sm:col-span-2"
        @click="removeCredentials"
      >
        Remove login credentials
      </button>
    </div>
  </article>
</template>
