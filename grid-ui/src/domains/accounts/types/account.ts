export type Account = {
  id: string
  name: string
  realm: string | null
  organization: {
    id: string
    name: string
  }
  organization_role: string | null
  permissions: {
    can_manage_extensions: boolean
    can_manage_devices: boolean
    can_manage_voicemail: boolean
    can_manage_call_routing: boolean
    can_manage_media: boolean
    can_sync_call_detail_records: boolean
  }
}
