import { createRouter, createWebHistory } from 'vue-router'
import AppShell from '@/layouts/AppShell.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: AppShell,
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
        },
        {
          path: 'extensions',
          component: () => import('@/views/PlaceholderView.vue'),
          props: {
            title: 'People & Extensions',
            description: 'Manage people, extensions, devices, voicemail, and routing together.',
          },
        },
        {
          path: 'devices',
          component: () => import('@/views/PlaceholderView.vue'),
          props: {
            title: 'Devices',
            description: 'Manage desk phones, softphones, and SIP access.',
          },
        },
        {
          path: 'phone-numbers',
          component: () => import('@/views/PlaceholderView.vue'),
          props: { title: 'Phone Numbers', description: 'View number inventory and assignments.' },
        },
        {
          path: 'call-routing',
          component: () => import('@/views/PlaceholderView.vue'),
          props: {
            title: 'Call Routing',
            description: 'Build and understand incoming call paths.',
          },
        },
        {
          path: 'voicemail',
          component: () => import('@/views/PlaceholderView.vue'),
          props: {
            title: 'Voicemail & Media',
            description: 'Manage greetings, messages, and audio.',
          },
        },
        {
          path: 'call-history',
          component: () => import('@/views/PlaceholderView.vue'),
          props: { title: 'Call History', description: 'Search and inspect account call records.' },
        },
        {
          path: 'accounts',
          component: () => import('@/views/PlaceholderView.vue'),
          props: {
            title: 'Accounts',
            description: 'Manage accessible Kazoo accounts and context.',
          },
        },
        {
          path: 'settings',
          component: () => import('@/views/PlaceholderView.vue'),
          props: {
            title: 'Settings',
            description: 'Configure account and application preferences.',
          },
        },
      ],
    },
  ],
})

export default router
