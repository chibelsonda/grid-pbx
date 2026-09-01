<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LockClosedIcon, Squares2X2Icon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
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

async function submit(): Promise<void> {
  const result = validateForm(loginFormSchema, credentials)
  validationErrors.value = result.errors
  if (!result.success) return

  try {
    await auth.login(result.data)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    await router.push(redirect)
  } catch {
    // The store exposes a user-safe error message in the form.
  }
}
</script>

<template>
  <main
    class="relative grid min-h-screen place-items-center overflow-hidden bg-[#f1f4f6] px-4 py-10"
  >
    <div
      class="absolute inset-y-0 left-0 w-[42%] -skew-x-6 bg-gradient-to-br from-[#3f6ad8] to-[#16aaff] opacity-95"
    />
    <section
      class="relative grid w-full max-w-4xl overflow-hidden rounded-lg bg-white shadow-[0_1rem_3rem_rgb(31_45_61/18%)] md:grid-cols-[0.9fr_1.1fr]"
    >
      <div
        class="hidden bg-gradient-to-br from-brand-700 to-brand-500 p-10 text-white md:flex md:flex-col md:justify-between"
      >
        <div class="flex items-center gap-3">
          <span class="grid size-11 place-items-center rounded-lg bg-white/15"
            ><Squares2X2Icon class="size-6"
          /></span>
          <div>
            <p class="text-lg font-bold">GridPBX</p>
            <p class="text-xs text-white/60">Simpler phone administration</p>
          </div>
        </div>
        <div>
          <h1 class="text-3xl leading-tight font-semibold">
            Your Switch data,<br />made practical.
          </h1>
          <p class="mt-4 text-sm leading-6 text-white/65">
            A focused operations console backed by Laravel and a searchable MySQL projection.
          </p>
        </div>
        <p class="text-[11px] text-white/45">Secure first-party session authentication</p>
      </div>

      <form class="p-7 sm:p-10" novalidate @submit.prevent="submit">
        <span class="grid size-11 place-items-center rounded-full bg-brand-50 text-brand-500"
          ><LockClosedIcon class="size-5"
        /></span>
        <h2 class="mt-6 text-2xl font-semibold tracking-tight text-slate-800">Welcome back</h2>
        <p class="mt-2 text-sm text-slate-500">Sign in to manage your mapped PBX accounts.</p>

        <div
          v-if="auth.error"
          class="mt-6 rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
        >
          {{ auth.error }}
        </div>

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
        <FormInput
          v-model="credentials.password"
          label="Password"
          class="mt-5"
          type="password"
          name="password"
          autocomplete="current-password"
          required
          :error="validationErrors.password"
          input-class="h-11 px-3.5 text-sm"
        />
        <ToggleSwitch v-model="credentials.remember" label="Remember me" class="mt-5" />

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
    </section>
  </main>
</template>
