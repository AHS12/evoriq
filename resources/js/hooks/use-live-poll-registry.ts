import { router } from '@inertiajs/react';
import type { ActiveVisit, Page, SharedPageProps } from '@inertiajs/core';

/**
 * PIPE-03 — shared live-polling transport (internal).
 *
 * `useLivePoll` keeps N subscribers that want the same page data in sync with
 * one timer and one in-flight request. The store lives at module scope so the
 * page, the sidebar badge and (later) the header indicator can share a poll
 * instead of each firing their own.
 *
 * The transport is deliberately Inertia-native: it re-issues partial reloads
 * of the *current* page (`router.reload` + `only`), never an ad-hoc JSON
 * endpoint. Swapping it for broadcasting later only means replacing `poll()`.
 */

export type LivePollStatus = 'paused' | 'idle' | 'polling' | 'error';

export type LivePollOptions = {
    url: string;
    only: string[];
    /** Host-driven: poll at `interval` only while there is active work. */
    enabled?: boolean;
    /** Active interval in ms (default 3000). */
    interval?: number;
    /** Slow poll while idle in ms (default 15000); 0 disables idle polling. */
    idleInterval?: number;
    /** Backoff cap in ms (default 60000). */
    maxInterval?: number;
};

export type LivePollState = {
    live: boolean;
    status: LivePollStatus;
    isRefreshing: boolean;
    lastRefreshedAt: number | null;
};

export type LivePollHandle = {
    update: (options: LivePollOptions) => void;
    refresh: () => void;
    pause: () => void;
    resume: () => void;
    getState: () => LivePollState;
    unsubscribe: () => void;
};

type Subscriber = {
    id: number;
    url: string;
    only: string[];
    enabled: boolean;
    interval: number;
    idleInterval: number;
    maxInterval: number;
    notify: (state: LivePollState) => void;
};

type Entry = {
    key: string;
    url: string;
    subscribers: Map<number, Subscriber>;
    timer: ReturnType<typeof setTimeout> | null;
    inFlight: boolean;
    failures: number;
    paused: boolean;
    lastRefreshedAt: number | null;
    lastRevision: string | null;
};

export const LIVE_POLL_DEFAULTS = {
    interval: 3000,
    idleInterval: 15000,
    maxInterval: 60000,
} as const;

const entries = new Map<string, Entry>();
let nextSubscriberId = 1;
let visibilityBound = false;

/** The registry entry key — one entry (and one request) per URL. */
export function livePollKey(url: string): string {
    return url;
}

/**
 * Exponential backoff: double the base delay per failure, capped at `max`.
 * Success resets `failures` to 0 (handled by the caller).
 */
export function backoffDelay(
    base: number,
    failures: number,
    max: number,
): number {
    if (failures <= 0) {
        return base;
    }

    return Math.min(base * 2 ** failures, max);
}

function isDocumentVisible(): boolean {
    if (typeof document === 'undefined') {
        return true;
    }

    return document.visibilityState !== 'hidden';
}

function normalize(
    options: LivePollOptions,
): Omit<Subscriber, 'id' | 'notify'> {
    return {
        url: options.url,
        only: [...options.only],
        enabled: options.enabled ?? true,
        interval: options.interval ?? LIVE_POLL_DEFAULTS.interval,
        idleInterval: options.idleInterval ?? LIVE_POLL_DEFAULTS.idleInterval,
        maxInterval: options.maxInterval ?? LIVE_POLL_DEFAULTS.maxInterval,
    };
}

function createEntry(key: string, url: string): Entry {
    return {
        key,
        url,
        subscribers: new Map(),
        timer: null,
        inFlight: false,
        failures: 0,
        paused: false,
        lastRefreshedAt: null,
        lastRevision: null,
    };
}

/** The interval a subscriber currently wants, or null when it wants none. */
function desiredDelay(subscriber: Subscriber): number | null {
    if (subscriber.enabled) {
        return Math.max(0, subscriber.interval);
    }

    if (subscriber.idleInterval > 0) {
        return subscriber.idleInterval;
    }

    return null;
}

/** The most eager delay across subscribers; null when nobody wants a poll. */
function entryDelay(entry: Entry): number | null {
    let min = Number.POSITIVE_INFINITY;

    entry.subscribers.forEach((subscriber) => {
        const delay = desiredDelay(subscriber);

        if (delay !== null) {
            min = Math.min(min, delay);
        }
    });

    return Number.isFinite(min) ? min : null;
}

/** The tightest backoff cap among subscribers that still want a poll. */
function entryCap(entry: Entry): number {
    let cap = Number.POSITIVE_INFINITY;

    entry.subscribers.forEach((subscriber) => {
        const delay = desiredDelay(subscriber);

        if (delay !== null) {
            cap = Math.min(cap, Math.max(subscriber.maxInterval, delay));
        }
    });

    return Number.isFinite(cap) ? cap : LIVE_POLL_DEFAULTS.maxInterval;
}

function entryHasActiveWork(entry: Entry): boolean {
    let active = false;

    entry.subscribers.forEach((subscriber) => {
        if (subscriber.enabled) {
            active = true;
        }
    });

    return active;
}

function entryStatus(entry: Entry): LivePollStatus {
    if (entry.paused || !isDocumentVisible()) {
        return 'paused';
    }

    if (entry.inFlight) {
        return 'polling';
    }

    if (entry.failures > 0) {
        return 'error';
    }

    return entryHasActiveWork(entry) ? 'polling' : 'idle';
}

function snapshot(entry: Entry): LivePollState {
    return {
        live: !entry.paused,
        status: entryStatus(entry),
        isRefreshing: entry.inFlight,
        lastRefreshedAt: entry.lastRefreshedAt,
    };
}

function notify(entry: Entry): void {
    const state = snapshot(entry);
    entry.subscribers.forEach((subscriber) => subscriber.notify(state));
}

function clearTimer(entry: Entry): void {
    if (entry.timer !== null) {
        clearTimeout(entry.timer);
        entry.timer = null;
    }
}

/** The union of the subscribers' `only` paths — what the shared request asks for. */
function unionOnly(entry: Entry): string[] {
    const only = new Set<string>();

    entry.subscribers.forEach((subscriber) => {
        subscriber.only.forEach((key) => only.add(key));
    });

    return Array.from(only);
}

function schedule(entry: Entry): void {
    clearTimer(entry);

    if (entry.subscribers.size === 0) {
        entries.delete(entry.key);

        return;
    }

    const delay = entryDelay(entry);

    if (!isDocumentVisible() || entry.paused || delay === null) {
        notify(entry);

        return;
    }

    notify(entry);

    entry.timer = setTimeout(
        () => {
            entry.timer = null;
            poll(entry);
        },
        backoffDelay(delay, entry.failures, entryCap(entry)),
    );
}

function wasCancelled(visit: ActiveVisit): boolean {
    return Boolean(visit.cancelled || visit.interrupted);
}

function applyRevision(entry: Entry, page: Page<SharedPageProps>): void {
    const revision = page.props.pipeline_revision;

    if (revision === entry.lastRevision) {
        return;
    }

    entry.lastRevision = revision;
    entry.lastRefreshedAt = Date.now();
}

function poll(entry: Entry): void {
    if (entry.inFlight || entry.subscribers.size === 0) {
        return;
    }

    if (!isDocumentVisible() || entry.paused || entryDelay(entry) === null) {
        schedule(entry);

        return;
    }

    entry.inFlight = true;
    notify(entry);

    let succeeded = false;

    // The request targets the current page (`router.reload`); the union of the
    // subscribers' `only` paths decides which shared/page props come back.
    router.reload({
        only: unionOnly(entry),
        showProgress: false,
        onSuccess: (page) => {
            succeeded = true;
            applyRevision(entry, page);
        },
        onFinish: (visit) => {
            entry.inFlight = false;

            if (succeeded) {
                entry.failures = 0;
            } else if (!wasCancelled(visit)) {
                entry.failures += 1;
            }

            schedule(entry);
        },
    });
}

/** Cancel the timer and poll again immediately (used on focus / manual refresh). */
function refresh(entry: Entry): void {
    clearTimer(entry);

    if (entry.subscribers.size === 0 || !isDocumentVisible() || entry.paused) {
        schedule(entry);

        return;
    }

    poll(entry);
}

function handleVisibilityChange(): void {
    if (isDocumentVisible()) {
        entries.forEach((entry) => refresh(entry));

        return;
    }

    entries.forEach((entry) => {
        clearTimer(entry);
        notify(entry);
    });
}

function bindVisibility(): void {
    if (visibilityBound || typeof document === 'undefined') {
        return;
    }

    visibilityBound = true;
    document.addEventListener('visibilitychange', handleVisibilityChange);
}

function unsubscribe(entry: Entry, id: number): void {
    entry.subscribers.delete(id);

    if (entry.subscribers.size === 0) {
        clearTimer(entry);
        entries.delete(entry.key);

        return;
    }

    schedule(entry);
}

/**
 * Register a subscriber. Returns a handle the hook uses to drive and release it.
 * Subscribers with the same `url` share one entry, one timer and one request.
 */
export function subscribeLivePoll(
    options: LivePollOptions,
    notifyCallback: (state: LivePollState) => void,
): LivePollHandle {
    const key = livePollKey(options.url);
    let entry = entries.get(key);

    if (!entry) {
        entry = createEntry(key, options.url);
        entries.set(key, entry);
    }

    const id = nextSubscriberId++;
    entry.subscribers.set(id, {
        id,
        ...normalize(options),
        notify: notifyCallback,
    });
    bindVisibility();

    notify(entry);
    schedule(entry);

    return {
        update: (next) => {
            const subscriber = entry?.subscribers.get(id);

            if (!entry || !subscriber) {
                return;
            }

            Object.assign(subscriber, normalize(next));
            schedule(entry);
        },
        refresh: () => {
            if (entry) {
                refresh(entry);
            }
        },
        pause: () => {
            if (!entry) {
                return;
            }

            entry.paused = true;
            clearTimer(entry);
            notify(entry);
        },
        resume: () => {
            if (!entry) {
                return;
            }

            entry.paused = false;
            refresh(entry);
        },
        getState: () =>
            entry
                ? snapshot(entry)
                : {
                      live: true,
                      status: 'idle',
                      isRefreshing: false,
                      lastRefreshedAt: null,
                  },
        unsubscribe: () => {
            if (entry) {
                unsubscribe(entry, id);
            }
        },
    };
}

/** Test-only: drop every entry and cancel their timers. */
export function resetLivePollRegistry(): void {
    entries.forEach((entry) => clearTimer(entry));
    entries.clear();
}
