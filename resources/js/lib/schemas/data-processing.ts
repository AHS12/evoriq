import { z } from 'zod';

export const exportJobSchema = z.object({
    entity_type: z.string().min(1, 'Choose an entity.'),
    format: z.enum(['csv', 'xlsx']),
});

export type ExportJobValues = z.infer<typeof exportJobSchema>;

export const importJobSchema = z.object({
    entity_type: z.string().min(1, 'Choose an entity.'),
    file: z.custom<File>(
        (value) => value instanceof File,
        'Choose a file to import.',
    ),
    send_invitations: z.boolean(),
    default_role: z.string(),
});

export type ImportJobValues = z.infer<typeof importJobSchema>;
