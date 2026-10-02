/**
 * The one sanctioned JSON POST helper. Inertia's `router` only understands
 * Inertia responses, so JSON endpoints (e.g. `connections.verify`) use `fetch`
 * with the `XSRF-TOKEN` cookie mirrored into the `X-XSRF-TOKEN` header — the
 * same contract Inertia's own client uses.
 */

export class JsonRequestError extends Error {
    constructor(
        public readonly status: number,
        public readonly body: unknown,
    ) {
        super(`Request failed with status ${status}`);
        this.name = 'JsonRequestError';
    }
}

function readCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const prefix = `${name}=`;

    for (const part of document.cookie.split('; ')) {
        if (part.startsWith(prefix)) {
            return decodeURIComponent(part.slice(prefix.length));
        }
    }

    return null;
}

export async function postJson<T>(url: string, data: unknown): Promise<T> {
    const token = readCookie('XSRF-TOKEN');

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify(data),
    });

    const body = await response.json().catch(() => null);

    if (!response.ok) {
        throw new JsonRequestError(response.status, body);
    }

    return body as T;
}
