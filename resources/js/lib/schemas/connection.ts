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

/**
 * CONN-05 — rename a connection. The region/subdomain stay server-resolved and
 * are not part of the rename form.
 */
export const connectionRenameSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, 'Name is required.')
        .max(255, 'Name must be 255 characters or fewer.'),
});

export type ConnectionRenameValues = z.infer<typeof connectionRenameSchema>;

/**
 * CONN-05 — rotate the API key. The new key is verified server-side before it
 * replaces the stored credential.
 */
export const connectionRotateKeySchema = z.object({
    api_key: z
        .string()
        .trim()
        .min(1, 'API key is required.')
        .max(255, 'API key must be 255 characters or fewer.'),
});

export type ConnectionRotateKeyValues = z.infer<
    typeof connectionRotateKeySchema
>;
