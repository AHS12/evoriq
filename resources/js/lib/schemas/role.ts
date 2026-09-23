import { z } from 'zod';

export const roleFormSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, 'Name is required.')
        .max(255, 'Name must be 255 characters or fewer.'),
    permissions: z.array(z.string()),
});

export type RoleFormValues = z.infer<typeof roleFormSchema>;
