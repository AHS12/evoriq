import type {
    FormDataKeys,
    FormDataType,
    FormDataValues,
} from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ZodType } from 'zod';
import { formatZodErrors, type ValidationErrors } from '@/lib/validation';

/**
 * Inertia's `useForm` plus Zod client-side validation.
 *
 * The server (Laravel FormRequest) stays the source of truth; this hook gives
 * instant feedback and blocks obviously invalid submissions. Client and server
 * errors are merged, so server-side messages still surface after a request.
 *
 * Zod messages are English source text; pass `translate` (e.g. `t` from
 * `useTranslation`) to render them in the active locale.
 */
export function useZodForm<TForm extends FormDataType<TForm>>(
    initialValues: TForm,
    schema: ZodType<TForm>,
    translate?: (message: string) => string,
) {
    const form = useForm<TForm>(initialValues);
    const [clientErrors, setClientErrors] = useState<ValidationErrors>({});

    const validate = (): boolean => {
        const result = schema.safeParse(form.data);

        if (result.success) {
            setClientErrors({});
            return true;
        }

        const messages = formatZodErrors(result.error);
        const translated = translate
            ? Object.fromEntries(
                  Object.entries(messages).map(([field, message]) => [
                      field,
                      translate(message),
                  ]),
              )
            : messages;

        setClientErrors(translated);

        return false;
    };

    const setField = <TKey extends FormDataKeys<TForm>>(
        key: TKey,
        value: FormDataValues<TForm, TKey>,
    ): void => {
        form.setData(key, value);

        setClientErrors((previous) => {
            const field = String(key);

            if (!(field in previous)) {
                return previous;
            }

            const next = { ...previous };
            delete next[field];

            return next;
        });
    };

    const errors: ValidationErrors = {
        ...clientErrors,
        ...(form.errors as ValidationErrors),
    };

    return { form, errors, validate, setField };
}
