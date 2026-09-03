<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { KeyIcon } from '@heroicons/vue/24/outline'
import AppAlert from '@/shared/components/AppAlert.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import AuthPageLayout from '../components/AuthPageLayout.vue'
import PasswordInput from '../components/PasswordInput.vue'
import { forgotPasswordFormSchema } from '../schemas/forgotPasswordFormSchema'
import { resetPasswordFormSchema } from '../schemas/resetPasswordFormSchema'
import { useAuthStore } from '../stores/authStore'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const email = typeof route.query.email === 'string' ? route.query.email : ''
const token = typeof route.query.token === 'string' ? route.query.token : ''
const form = reactive({
  email,
  token,
  password: '',
  password_confirmation: '',
})
const validationErrors = ref<FormErrors>({})
const hasCompleteLink = computed(
  () =>
    forgotPasswordFormSchema.safeParse({ email: form.email }).success &&
    form.token.length > 0 &&
    form.token.length <= 2048,
)

auth.clearPasswordResetState()

watch(form, () => {
  validationErrors.value = {}
  auth.passwordResetFieldErrors = {}
  auth.passwordResetError = null
})

async function submit(): Promise<void> {
  const result = validateForm(resetPasswordFormSchema, form)
  validationErrors.value = result.errors
  if (!result.success) return

  if (await auth.resetPassword(result.data)) {
    await router.push({ name: 'login', query: { reset: 'success' } })
  }
}
</script>

<template>
  <AuthPageLayout>
    <form class="p-7 sm:p-10" novalidate @submit.prevent="submit">
      <span class="grid size-11 place-items-center rounded-full bg-brand-50 text-brand-500">
        <KeyIcon class="size-5" aria-hidden="true" />
      </span>
      <h2 class="mt-6 text-2xl font-semibold tracking-tight text-slate-800">
        Choose a new password
      </h2>
      <p class="mt-2 text-sm leading-6 text-slate-500">
        Use a unique password you do not use for another service.
      </p>

      <AppAlert
        v-if="!hasCompleteLink"
        message="This password reset link is incomplete. Request a new link and try again."
        tone="warning"
        :dismissible="false"
        class="mt-6"
      />
      <AppAlert
        v-if="auth.passwordResetError"
        :message="auth.passwordResetError"
        tone="error"
        class="mt-6"
        @dismiss="auth.passwordResetError = null"
      />

      <p v-if="hasCompleteLink" class="mt-7 text-xs text-slate-500">
        Resetting the password for <strong class="font-semibold text-slate-700">{{ email }}</strong>
      </p>
      <PasswordInput
        v-model="form.password"
        label="New password"
        class="mt-5"
        name="password"
        autocomplete="new-password"
        required
        description="At least 12 characters with uppercase, lowercase, number, and symbol."
        :disabled="!hasCompleteLink"
        :error="validationErrors.password ?? auth.passwordResetFieldErrors.password"
        input-class="h-11 px-3.5 text-sm"
      />
      <PasswordInput
        v-model="form.password_confirmation"
        label="Confirm new password"
        class="mt-5"
        name="password_confirmation"
        autocomplete="new-password"
        required
        :disabled="!hasCompleteLink"
        :error="
          validationErrors.password_confirmation ??
          auth.passwordResetFieldErrors.password_confirmation
        "
        input-class="h-11 px-3.5 text-sm"
      />

      <button
        type="submit"
        :disabled="auth.passwordResetLoading || !hasCompleteLink"
        class="mt-7 h-11 w-full rounded-md bg-brand-500 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {{ auth.passwordResetLoading ? 'Resetting…' : 'Reset password' }}
      </button>
      <RouterLink
        :to="{ name: 'forgot-password' }"
        class="mt-5 block text-center text-xs font-semibold text-brand-600 transition hover:text-brand-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
      >
        Request a new reset link
      </RouterLink>
    </form>
  </AuthPageLayout>
</template>
