<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LockClosedIcon } from '@heroicons/vue/24/outline'
import AppAlert from '@/shared/components/AppAlert.vue'
import FormInput from '@/shared/components/FormInput.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import AuthPageLayout from '../components/AuthPageLayout.vue'
import PasswordInput from '../components/PasswordInput.vue'
import { loginFormSchema } from '../schemas/loginFormSchema'
import { useAuthStore } from '../stores/authStore'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const credentials = reactive({
  email: 'admin@gridpbx.local',
  password: 'admin-change-me',
  remember: true,
})
const validationErrors = ref<FormErrors>({})

watch(credentials, () => (validationErrors.value = {}), { deep: true })

function safeRedirect(): string {
  const redirect = route.query.redirect

  return typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')
    ? redirect
    : '/'
}

async function submit(): Promise<void> {
  const result = validateForm(loginFormSchema, credentials)
  validationErrors.value = result.errors
  if (!result.success) return

  try {
    await auth.login(result.data)
    await router.push(safeRedirect())
  } catch {
    // The store exposes a user-safe error message in the form.
  }
}
</script>

<template>
  <AuthPageLayout>
    <form class="p-7 sm:p-10" novalidate @submit.prevent="submit">
      <span class="grid size-11 place-items-center rounded-full bg-brand-50 text-brand-500">
        <LockClosedIcon class="size-5" />
      </span>
      <h2 class="mt-6 text-2xl font-semibold tracking-tight text-slate-800">Welcome back</h2>
      <p class="mt-2 text-sm text-heading-description">Sign in to manage your mapped PBX accounts.</p>

      <AppAlert
        v-if="route.query.reset === 'success'"
        message="Your password has been reset. Sign in with your new password."
        tone="success"
        auto-close
        class="mt-6"
      />
      <AppAlert
        v-if="auth.error"
        :message="auth.error"
        tone="error"
        class="mt-6"
        @dismiss="auth.error = null"
      />

      <FormInput
        v-model="credentials.email"
        label="Email address"
        class="mt-7"
        type="email"
        name="email"
        autocomplete="username"
        required
        :error="validationErrors.email"
        input-class="h-11 px-3.5 text-sm"
      />
      <PasswordInput
        v-model="credentials.password"
        label="Password"
        class="mt-5"
        name="password"
        autocomplete="current-password"
        required
        :error="validationErrors.password"
        input-class="h-11 px-3.5 text-sm"
      />
      <div class="mt-5 flex items-center justify-between gap-4">
        <ToggleSwitch v-model="credentials.remember" label="Remember me" />
        <RouterLink
          :to="{ name: 'forgot-password' }"
          class="text-xs font-semibold text-brand-600 transition hover:text-brand-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
        >
          Forgot password?
        </RouterLink>
      </div>

      <button
        type="submit"
        :disabled="auth.loading"
        class="mt-7 h-11 w-full rounded-md bg-brand-500 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 disabled:cursor-wait disabled:opacity-60"
      >
        {{ auth.loading ? 'Signing in…' : 'Sign in' }}
      </button>
      <p class="mt-5 text-center text-[11px] text-slate-400">
        Local defaults are prefilled for development only.
      </p>
    </form>
  </AuthPageLayout>
</template>
