export const invalidControlClasses =
  '!border-red-400 bg-red-50/40 ring-2 ring-red-100 focus:!border-red-500 focus:ring-red-100'

export function validationControlClass(error: unknown): string {
  return error ? invalidControlClasses : ''
}
