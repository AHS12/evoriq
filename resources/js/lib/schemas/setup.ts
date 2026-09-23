import { z } from 'zod';

export const databaseFormSchema = z
    .object({
        driver: z.string().min(1, 'Select a database driver.'),
        host: z.string(),
        port: z
            .number()
            .int('Enter a valid port.')
            .min(1, 'Enter a valid port.')
            .max(65535, 'Enter a valid port.'),
        database: z.string().trim().min(1, 'Database name is required.'),
        username: z.string(),
        password: z.string(),
        create_database: z.boolean(),
    })
    .superRefine((values, ctx) => {
        if (values.driver === 'sqlite') {
            return;
        }

        if (values.host.trim() === '') {
            ctx.addIssue({
                code: 'custom',
                path: ['host'],
                message: 'Host is required.',
            });
        }

        if (values.username.trim() === '') {
            ctx.addIssue({
                code: 'custom',
                path: ['username'],
                message: 'Username is required.',
            });
        }

        if (!/^[A-Za-z0-9_]+$/.test(values.database)) {
            ctx.addIssue({
                code: 'custom',
                path: ['database'],
                message: 'Use only letters, numbers and underscores.',
            });
        }
    });

export type DatabaseFormValues = z.infer<typeof databaseFormSchema>;

export const driverFormSchema = z.object({
    session: z.string().min(1, 'Select a session driver.'),
    cache: z.string().min(1, 'Select a cache driver.'),
    queue: z.string().min(1, 'Select a queue driver.'),
    redis_host: z.string(),
    redis_port: z
        .number()
        .int('Enter a valid port.')
        .min(1, 'Enter a valid port.')
        .max(65535, 'Enter a valid port.'),
    redis_password: z.string(),
});

export type DriverFormValues = z.infer<typeof driverFormSchema>;

export function createAdminSchema(requireAccount: boolean) {
    return z
        .object({
            app_name: z
                .string()
                .trim()
                .min(1, 'Workspace name is required.')
                .max(255, 'Workspace name must be 255 characters or fewer.'),
            name: z.string().trim().max(255),
            email: z.string().trim().max(255),
            password: z.string(),
            password_confirmation: z.string(),
        })
        .superRefine((values, ctx) => {
            if (!requireAccount) {
                return;
            }

            if (values.name === '') {
                ctx.addIssue({
                    code: 'custom',
                    path: ['name'],
                    message: 'Name is required.',
                });
            }

            if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(values.email)) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['email'],
                    message: 'Enter a valid email address.',
                });
            }

            if (values.password.length < 8) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['password'],
                    message: 'Password must be at least 8 characters.',
                });
            }

            if (values.password !== values.password_confirmation) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['password_confirmation'],
                    message: 'Passwords do not match.',
                });
            }
        });
}
