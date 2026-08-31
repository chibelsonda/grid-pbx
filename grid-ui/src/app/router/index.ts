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
          children: [
            {
              path: 'new',
              name: 'device-create',
              component: () => import('@/domains/devices/pages/DeviceFormPage.vue'),
            },
            {
              path: ':deviceId/edit',
              name: 'device-edit',
              component: () => import('@/domains/devices/pages/DeviceFormPage.vue'),
            },
          ],
        },
        {
          path: 'devices/:deviceId',
          name: 'device-detail',
          component: () => import('@/domains/devices/pages/DeviceDetailPage.vue'),
        },
        {
          path: 'line-keys',
          name: 'line-keys',
          component: () => import('@/domains/line-keys/pages/LineKeysPage.vue'),
        },
        {
          path: 'voicemail',
          name: 'voicemail',
          component: () => import('@/domains/voicemail/pages/VoicemailBoxesPage.vue'),
        },
        {
          path: 'media',
          name: 'media',
          component: () => import('@/domains/media/pages/MediaPage.vue'),
        },
        {
          path: 'directories',
          name: 'directories',
          component: () => import('@/domains/directories/pages/DirectoriesPage.vue'),
        },
        {
          path: 'groups',
          name: 'groups',
          component: () => import('@/domains/groups/pages/GroupsPage.vue'),
        },
        {
          path: 'queues',
          name: 'queues',
          component: () => import('@/domains/queues/pages/QueuesPage.vue'),
        },
        {
          path: 'menus',
          name: 'menus',
          component: () => import('@/domains/menus/pages/MenusPage.vue'),
        },
        {
          path: 'business-hours',
          name: 'business-hours',
          component: () => import('@/domains/temporal-routing/pages/TemporalRoutingPage.vue'),
        },
        {
          path: 'blacklists',
          name: 'blacklists',
          component: () => import('@/domains/blacklists/pages/BlacklistsPage.vue'),
        },
        {
          path: 'caller-id-lists',
          name: 'caller-id-lists',
          component: () => import('@/domains/caller-id-lists/pages/CallerIdListsPage.vue'),
        },
        {
          path: 'recordings',
          name: 'recordings',
          component: () => import('@/domains/recordings/pages/RecordingsPage.vue'),
        },
        {
          path: 'conferences',
          name: 'conferences',
          component: () => import('@/domains/conferences/pages/ConferencesPage.vue'),
        },
        {
          path: 'faxes',
          name: 'faxes',
          component: () => import('@/domains/faxes/pages/FaxesPage.vue'),
        },
        {
          path: 'services',
          name: 'services',
          component: () => import('@/domains/services/pages/ServicesPage.vue'),
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
          path: 'feature-codes',
          name: 'feature-codes',
          component: () => import('@/domains/call-routing/pages/FeatureCodesPage.vue'),
        },
        {
          path: 'call-history',
          name: 'call-history',
          component: () => import('@/domains/call-detail-records/pages/CallDetailRecordsPage.vue'),
        },
        {
          path: 'accounts',
          name: 'accounts',
          component: () => import('@/domains/accounts/pages/AccountsPage.vue'),
        },
        {
          path: 'reseller',
          name: 'reseller-administration',
          component: () => import('@/domains/reseller/pages/ResellerAdministrationPage.vue'),
        },
        {
          path: 'system-status',
          name: 'system-status',
          component: () => import('@/domains/system-status/pages/SystemStatusPage.vue'),
        },
        ...(
          [['settings', 'Settings', 'Configure account and application preferences.']] as const
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
