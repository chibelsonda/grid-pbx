const unsupportedConstruct = /\(\?(?:R|0|&|P>|\{|\?)|\(\*/

export function isSafeSwitchRegex(pattern: string): boolean {
  if (pattern.includes('\u001f') || unsupportedConstruct.test(pattern)) return false

  try {
    new RegExp(pattern, 'u')
    return true
  } catch {
    return false
  }
}
