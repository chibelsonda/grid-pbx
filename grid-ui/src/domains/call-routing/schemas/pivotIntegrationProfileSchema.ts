import { z } from 'zod'

const httpsUrl = z
  .string()
  .trim()
  .max(2048, 'Use a URL with 2,048 characters or fewer.')
  .url('Enter a valid URL.')
  .refine((value) => value.startsWith('https://'), 'Use an HTTPS URL.')

export const pivotIntegrationProfileSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a profile name.').max(100),
    is_active: z.boolean(),
    voice_url: httpsUrl,
    cdr_url: z.union([httpsUrl, z.literal('')]),
    methods: z
      .array(z.enum(['get', 'post']))
      .min(1, 'Select at least one request method.')
      .max(2),
    formats: z
      .array(z.enum(['kazoo', 'twiml']))
      .min(1, 'Select at least one response format.')
      .max(2),
    req_body_format: z.enum(['form', 'json']),
    req_timeout_ms: z.coerce
      .number()
      .int('Use a whole number of milliseconds.')
      .min(1, 'Use at least 1 millisecond.')
      .max(5000, 'Use no more than 5,000 milliseconds.'),
    headers: z
      .array(
        z.object({
          name: z
            .string()
            .trim()
            .regex(/^X-[A-Za-z0-9-]{1,62}$/, 'Use an X- prefixed header name.'),
          value: z
            .string()
            .max(1024, 'Use 1,024 characters or fewer.')
            .refine(
              (value) => !/[\0\r\n]/.test(value),
              'Header values cannot contain line breaks.',
            ),
        }),
      )
      .max(20)
      .superRefine((headers, context) => {
        const names = new Set<string>()
        headers.forEach((header, index) => {
          const normalized = header.name.toLowerCase()
          if (names.has(normalized)) {
            context.addIssue({
              code: 'custom',
              path: [index, 'name'],
              message: 'Header names must be unique.',
            })
          }
          names.add(normalized)
        })
      }),
  })
  .strict()

export type PivotIntegrationProfileForm = z.input<typeof pivotIntegrationProfileSchema>
export type ValidPivotIntegrationProfileForm = z.output<typeof pivotIntegrationProfileSchema>
