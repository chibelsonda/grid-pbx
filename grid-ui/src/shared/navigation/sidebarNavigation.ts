import type { Component } from 'vue'
import {
  ArrowPathRoundedSquareIcon,
  Bars3BottomLeftIcon,
  BanknotesIcon,
  BoltIcon,
  BookOpenIcon,
  BuildingOffice2Icon,
  CalendarDaysIcon,
  ChartBarSquareIcon,
  ChatBubbleLeftRightIcon,
  CircleStackIcon,
  ClockIcon,
  Cog6ToothIcon,
  DevicePhoneMobileIcon,
  HashtagIcon,
  IdentificationIcon,
  MicrophoneIcon,
  MusicalNoteIcon,
  PhoneArrowUpRightIcon,
  PrinterIcon,
  QueueListIcon,
  RectangleGroupIcon,
  SignalIcon,
  ShieldExclamationIcon,
  SquaresPlusIcon,
  UserGroupIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'

export type SidebarItem = {
  label: string
  to: string
  icon: Component
}

export type SidebarGroup = {
  id: 'people-endpoints' | 'numbers-routing' | 'call-applications' | 'activity'
  label: string
  icon: Component
  items: SidebarItem[]
}

export const overviewItems: SidebarItem[] = [
  { label: 'Dashboard', to: '/', icon: ChartBarSquareIcon },
]

export const cloudPhoneGroups: SidebarGroup[] = [
  {
    id: 'people-endpoints',
    label: 'People & Endpoints',
    icon: DevicePhoneMobileIcon,
    items: [
      { label: 'People & Extensions', to: '/extensions', icon: UserGroupIcon },
      { label: 'Devices', to: '/devices', icon: DevicePhoneMobileIcon },
      { label: 'Line Keys', to: '/line-keys', icon: WrenchScrewdriverIcon },
    ],
  },
  {
    id: 'numbers-routing',
    label: 'Numbers & Routing',
    icon: PhoneArrowUpRightIcon,
    items: [
      { label: 'Phone Numbers', to: '/phone-numbers', icon: HashtagIcon },
      { label: 'Call Routing', to: '/call-routing', icon: ArrowPathRoundedSquareIcon },
      { label: 'Feature Codes', to: '/feature-codes', icon: BoltIcon },
      { label: 'Business Hours', to: '/business-hours', icon: CalendarDaysIcon },
      { label: 'Blacklists', to: '/blacklists', icon: ShieldExclamationIcon },
      { label: 'Caller-ID Lists', to: '/caller-id-lists', icon: IdentificationIcon },
    ],
  },
  {
    id: 'call-applications',
    label: 'Call Applications',
    icon: SquaresPlusIcon,
    items: [
      { label: 'Directories', to: '/directories', icon: BookOpenIcon },
      { label: 'Groups & Ring Groups', to: '/groups', icon: UserGroupIcon },
      { label: 'Queues & Agents', to: '/queues', icon: QueueListIcon },
      { label: 'Menus & IVR', to: '/menus', icon: Bars3BottomLeftIcon },
      { label: 'Voicemail', to: '/voicemail', icon: MicrophoneIcon },
      { label: 'Media & Music on Hold', to: '/media', icon: MusicalNoteIcon },
      { label: 'Conferences', to: '/conferences', icon: ChatBubbleLeftRightIcon },
      { label: 'Fax', to: '/faxes', icon: PrinterIcon },
    ],
  },
  {
    id: 'activity',
    label: 'Activity',
    icon: ClockIcon,
    items: [
      { label: 'Call History', to: '/call-history', icon: ClockIcon },
      { label: 'Recordings', to: '/recordings', icon: MicrophoneIcon },
    ],
  },
]

export const businessItems: SidebarItem[] = [
  { label: 'Services & Limits', to: '/services', icon: CircleStackIcon },
  { label: 'Billing', to: '/billing', icon: BanknotesIcon },
]

export const workspaceItems: SidebarItem[] = [
  { label: 'Accounts', to: '/accounts', icon: BuildingOffice2Icon },
  { label: 'Reseller administration', to: '/reseller', icon: RectangleGroupIcon },
  { label: 'System status', to: '/system-status', icon: SignalIcon },
  { label: 'Settings', to: '/settings', icon: Cog6ToothIcon },
]
