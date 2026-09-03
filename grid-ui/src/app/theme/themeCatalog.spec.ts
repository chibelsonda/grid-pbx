import { describe, expect, it } from 'vitest'
import { applicationThemes } from './themeCatalog'

function relativeLuminance(hex: string): number {
  const channels = hex
    .slice(1)
    .match(/.{2}/g)
    ?.map((channel) => Number.parseInt(channel, 16) / 255)
    .map((channel) =>
      channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4,
    )

  if (!channels || channels.length !== 3) return 0

  return 0.2126 * channels[0]! + 0.7152 * channels[1]! + 0.0722 * channels[2]!
}

function contrastRatio(foreground: string, background: string): number {
  const foregroundLuminance = relativeLuminance(foreground)
  const backgroundLuminance = relativeLuminance(background)

  return (
    (Math.max(foregroundLuminance, backgroundLuminance) + 0.05) /
    (Math.min(foregroundLuminance, backgroundLuminance) + 0.05)
  )
}

describe('application theme typography contrast', () => {
  it.each(applicationThemes)('$label keeps primary and secondary text readable', (theme) => {
    expect(
      contrastRatio(theme.tokens.foreground, theme.tokens.surface),
      `${theme.id} foreground`,
    ).toBeGreaterThanOrEqual(4.5)
    expect(
      contrastRatio(theme.tokens.muted, theme.tokens.surface),
      `${theme.id} muted text`,
    ).toBeGreaterThanOrEqual(4.5)
  })
})
