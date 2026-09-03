export type AppNotificationTone = 'info' | 'success' | 'warning' | 'error'

export type AppNotificationInput = {
  title: string
  message: string
  tone?: AppNotificationTone
}

export type AppNotificationState = Omit<AppNotificationInput, 'tone'> & {
  id: number
  tone: AppNotificationTone
}
