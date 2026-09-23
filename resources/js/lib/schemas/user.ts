import { z } from 'zod';

export const userStatusSchema = z.enum(['invited', 'active', 'suspended']);

export const userFormSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, 'Name is required.')
        .max(255, 'Name must be 255 characters or fewer.'),
    email: z
        .string()
        .trim()
        .min(1, 'Email is required.')
        .email('Enter a valid email address.')
        .max(255, 'Email must be 255 characters or fewer.'),
    roles: z.array(z.string()),
    status: userStatusSchema,
});

export type UserFormValues = z.infer<typeof userFormSchema>;
