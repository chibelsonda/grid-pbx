import { describe, expect, it } from 'vitest'
import { organizationLogoSchema } from './organizationLogoSchema'

describe('organizationLogoSchema', () => {
  it('accepts only bounded raster logo files', () => {
    expect(
      organizationLogoSchema.safeParse({
        logo: new File(['png'], 'brand.png', { type: 'image/png' }),
      }).success,
    ).toBe(true)
    expect(
      organizationLogoSchema.safeParse({
        logo: new File(['svg'], 'brand.svg', { type: 'image/svg+xml' }),
      }).success,
    ).toBe(false)
    expect(
      organizationLogoSchema.safeParse({
        logo: new File([new Uint8Array(2 * 1024 * 1024 + 1)], 'large.webp', {
          type: 'image/webp',
        }),
      }).success,
    ).toBe(false)
  })
})
