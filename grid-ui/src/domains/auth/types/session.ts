export type SessionUser = {
  id: number
  name: string
  email: string
}

export type Session = {
  user: SessionUser
}

export type LoginCredentials = {
  email: string
  password: string
  remember: boolean
}
