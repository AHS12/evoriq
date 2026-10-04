import { z } from 'zod';

/**
 * PIPE-09 — the import range step. The English messages are translation keys
 * (translated by `useZodForm`); `StoreImportRequest` stays the server-side
 * source of truth.
 */
export const importRangeSchema = z
    .object({
        range_start: z.string().min(1, 'Choose a start date.'),
        range_end: z.string().min(1, 'Choose an end date.'),
    })
    .refine((values) => values.range_end >= values.range_start, {
        path: ['range_end'],
        message: 'The end date must be on or after the start date.',
    });

export type ImportRangeValues = z.infer<typeof importRangeSchema>;
