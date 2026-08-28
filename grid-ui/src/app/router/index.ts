import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/domains/auth/stores/authStore'
import AppShell from '@/app/layouts/AppShell.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/domains/auth/pages/LoginPage.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/',
      component: AppShell,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/domains/dashboard/pages/DashboardPage.vue'),
        },
        {
          path: 'extensions',
          name: 'extensions',
          component: () => import('@/domains/extensions/pages/ExtensionsPage.vue'),
        },
        {
          path: 'extensions/:extensionId',
          name: 'extension-detail',
          component: () => import('@/domains/extensions/pages/ExtensionDetailPage.vue'),
        },
        {
          path: 'devices',
          name: 'devices',
          component: () => import('@/domains/devices/pages/DevicesPage.vue'),
        },
        {
          path: 'devices/new',
          name: 'device-create',
          component: () => import('@/domains/devices/pages/DeviceFormPage.vue'),
        },
        {
          path: 'devices/:deviceId/edit',
          name: 'device-edit',
          component: () => import('@/domains/devices/pages/DeviceFormPage.vue'),
        },
        {
          path: 'devices/:deviceId',
          name: 'device-detail',
          component: () => import('@/domains/devices/pages/DeviceDetailPage.vue'),
        },
        {
          path: 'voicemail',
          name: 'voicemail',
          component: () => import('@/domains/voicemail/pages/VoicemailBoxesPage.vue'),
        },
        {
          path: 'voicemail/new',
          name: 'voicemail-create',
          component: () => import('@/domains/voicemail/pages/VoicemailBoxFormPage.vue'),
        },
        {
          path: 'voicemail/:voicemailBoxId/edit',
          name: 'voicemail-edit',
          component: () => import('@/domains/voicemail/pages/VoicemailBoxFormPage.vue'),
        },
        {
          path: 'voicemail/:voicemailBoxId',
          name: 'voicemail-detail',
          component: () => import('@/domains/voicemail/pages/VoicemailBoxDetailPage.vue'),
        },
        {
          path: 'phone-numbers',
          name: 'phone-numbers',
          component: () => import('@/domains/phone-numbers/pages/PhoneNumbersPage.vue'),
        },
        {
          path: 'call-routing',
          name: 'call-routing',
          component: () => import('@/domains/call-routing/pages/CallRoutingPage.vue'),
        },
        {
          path: 'call-history',
          name: 'call-history',
          component: () => import('@/domains/call-detail-records/pages/CallDetailRecordsPage.vue'),
        },
        ...(
          [
            ['accounts', 'Accounts', 'Manage accessible Switch accounts and context.'],
            ['settings', 'Settings', 'Configure account and application preferences.'],
          ] as const
        ).map(([path, title, description]) => ({
          path,
          component: () => import('@/shared/components/PlaceholderPage.vue'),
          props: { title, description },
        })),
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.restore()

  if (to.meta.requiresAuth && !auth.authenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.authenticated) return { name: 'dashboard' }
})

export default router
