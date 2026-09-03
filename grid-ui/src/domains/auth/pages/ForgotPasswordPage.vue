<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { EnvelopeIcon } from '@heroicons/vue/24/outline'
import AppAlert from '@/shared/components/AppAlert.vue'
import FormInput from '@/shared/components/FormInput.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import AuthPageLayout from '../components/AuthPageLayout.vue'
import { forgotPasswordFormSchema } from '../schemas/forgotPasswordFormSchema'
import { useAuthStore } from '../stores/authStore'

const auth = useAuthStore()
const form = reactive({ email: '' })
const validationErrors = ref<FormErrors>({})

auth.clearPasswordResetState()

watch(form, () => {
  validationErrors.value = {}
  auth.passwordResetFieldErrors = {}
  auth.passwordResetError = null
})

async function submit(): Promise<void> {
  const result = validateForm(forgotPasswordFormSchema, form)
  validationErrors.value = result.errors
  if (!result.success) return

  await auth.requestPasswordReset(result.data)
}
</script>

<template>
  <AuthPageLayout>
    <form class="p-7 sm:p-10" novalidate @submit.prevent="submit">
      <span class="grid size-11 place-items-center rounded-full bg-brand-50 text-brand-500">
        <EnvelopeIcon class="size-5" aria-hidden="true" />
      </span>
      <h2 class="mt-6 text-2xl font-semibold tracking-tight text-slate-800">Reset your password</h2>
      <p class="mt-2 text-sm leading-6 text-heading-description">
        Enter your sign-in email and we’ll send reset instructions if an account exists.
      </p>

      <AppAlert
        v-if="auth.passwordResetMessage"
        :message="auth.passwordResetMessage"
        tone="success"
        auto-close
        :duration="6_000"
        class="mt-6"
        @dismiss="auth.passwordResetMessage = null"
      />
      <AppAlert
        v-if="auth.passwordResetError"
        :message="auth.passwordResetError"
        tone="error"
        class="mt-6"
        @dismiss="auth.passwordResetError = null"
      />

      <FormInput
        v-model="form.email"
        label="Email address"
        class="mt-7"
        type="email"
        name="email"
        autocomplete="username"
        required
        :error="validationErrors.email ?? auth.passwordResetFieldErrors.email"
        input-class="h-11 px-3.5 text-sm"
      />

      <button
        type="submit"
        :disabled="auth.passwordResetLoading"
        class="mt-7 h-11 w-full rounded-md bg-brand-500 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 disabled:cursor-wait disabled:opacity-60"
      >
        {{ auth.passwordResetLoading ? 'Sending…' : 'Send reset link' }}
      </button>
      <RouterLink
        :to="{ name: 'login' }"
        class="mt-5 block text-center text-xs font-semibold text-brand-600 transition hover:text-brand-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
      >
        Back to sign in
      </RouterLink>
    </form>
  </AuthPageLayout>
</template>
