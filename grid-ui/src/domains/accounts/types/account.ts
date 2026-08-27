export type Account = {
  id: string
  name: string
  realm: string | null
  organization: {
    id: string
    name: string
  }
}
