import type { ZodError, ZodType } from 'zod';

export type ValidationErrors = Record<string, string>;

/**
 * Flatten a Zod error into an `{ field: message }` map compatible with
 * Inertia's `errors` prop (first issue per field wins).
 */
export function formatZodErrors(error: ZodError): ValidationErrors {
    const errors: ValidationErrors = {};

    for (const issue of error.issues) {
        const key = issue.path.map(String).join('.');

        if (key !== '' && !(key in errors)) {
            errors[key] = issue.message;
        }
    }

    return errors;
}

/**
 * Validate `data` against a Zod schema, returning either the parsed value or a
 * field-to-message error map.
 */
export function validateWithZod<TData>(
    schema: ZodType<TData>,
    data: unknown,
):
    | { success: true; data: TData }
    | { success: false; errors: ValidationErrors } {
    const result = schema.safeParse(data);

    if (result.success) {
        return { success: true, data: result.data };
    }

    return { success: false, errors: formatZodErrors(result.error) };
}
