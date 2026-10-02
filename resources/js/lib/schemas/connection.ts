import { z } from 'zod';

/**
 * CONN-04 — the connect key step. The English messages are translation keys
 * (translated by `useZodForm`); the Laravel `FormRequest` stays the server-side
 * source of truth.
 */
export const connectionKeySchema = z.object({
    name: z.string().trim().max(255, 'Name must be 255 characters or fewer.'),
    api_key: z
        .string()
        .trim()
        .min(1, 'API key is required.')
        .max(255, 'API key must be 255 characters or fewer.'),
    region: z.string().min(1, 'Choose a region.'),
});

export type ConnectionKeyValues = z.infer<typeof connectionKeySchema>;
