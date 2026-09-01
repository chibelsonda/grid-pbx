export function accountRoleLabel(role: string | null | undefined): string {
  if (!role) return 'No account role'

  return role
    .split('_')
    .filter(Boolean)
    .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
    .join(' ')
}
