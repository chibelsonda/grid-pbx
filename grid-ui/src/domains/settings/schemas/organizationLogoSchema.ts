import { z } from 'zod'

const acceptedLogoExtensions = new Set(['png', 'jpg', 'jpeg', 'webp'])

export const organizationLogoSchema = z.object({
  logo: z
    .instanceof(File, { message: 'Choose a logo image.' })
    .refine((file) => file.size <= 2 * 1024 * 1024, 'The logo must not exceed 2 MB.')
    .refine(
      (file) => ['image/png', 'image/jpeg', 'image/webp'].includes(file.type),
      'Choose a PNG, JPEG, or WebP image.',
    )
    .refine(
      (file) => acceptedLogoExtensions.has(file.name.split('.').pop()?.toLowerCase() ?? ''),
      'Choose a file with a PNG, JPG, JPEG, or WebP extension.',
    ),
})
