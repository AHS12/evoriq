import { beforeEach, describe, expect, it } from 'vitest';
import {
    FORMAT_PLACEHOLDER,
    formatBytes,
    formatCurrency,
    formatDuration,
    formatHours,
    formatMonth,
    formatNumber,
    formatPercent,
    formatThroughput,
} from '@/lib/format';

beforeEach(() => {
    document.documentElement.lang = 'en';
});

describe('formatNumber', () => {
    it('groups numbers and falls back to the placeholder', () => {
        expect(formatNumber(1284)).toBe('1,284');
        expect(formatNumber(null)).toBe(FORMAT_PLACEHOLDER);
        expect(formatNumber(undefined)).toBe(FORMAT_PLACEHOLDER);
        expect(formatNumber(Number.NaN)).toBe(FORMAT_PLACEHOLDER);
    });

    it('compacts large numbers', () => {
        expect(formatNumber(1284, { compact: true })).toBe('1.3K');
    });
});

describe('formatDuration', () => {
    it('formats seconds into human durations', () => {
        expect(formatDuration(0)).toBe('0m');
        expect(formatDuration(90)).toBe('1m');
        expect(formatDuration(3600)).toBe('1h 0m');
        expect(formatDuration(90000)).toBe('1d 1h');
        expect(formatDuration(null)).toBe(FORMAT_PLACEHOLDER);
    });
});

describe('formatHours', () => {
    it('converts seconds to hours and honours an hours input', () => {
        expect(formatHours(3600)).toBe('1 h');
        expect(formatHours(5400)).toBe('1.5 h');
        expect(formatHours(2, { from: 'hours' })).toBe('2 h');
    });
});

describe('formatPercent', () => {
    it('formats percentages with a configurable precision', () => {
        expect(formatPercent(78)).toBe('78%');
        expect(formatPercent(78.44, { digits: 1 })).toBe('78.4%');
        expect(formatPercent(null)).toBe(FORMAT_PLACEHOLDER);
    });
});

describe('formatBytes', () => {
    it('formats byte sizes with units', () => {
        expect(formatBytes(0)).toBe('0 B');
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(1536)).toBe('1.5 KB');
        expect(formatBytes(2048)).toBe('2 KB');
    });
});

describe('formatCurrency', () => {
    it('formats an amount with the given currency', () => {
        expect(formatCurrency(1234.5)).toBe('$1,234.50');
    });
});

describe('formatMonth', () => {
    it('formats a month label, optionally with the year', () => {
        const september = new Date(2026, 8, 1);

        expect(formatMonth(september)).toBe('Sep');
        expect(formatMonth(september, { year: true })).toBe('Sep 2026');
    });
});

describe('formatThroughput', () => {
    it('derives a per-second rate and guards invalid input', () => {
        expect(formatThroughput(1000, 2)).toBe('500 rows/s');
        expect(formatThroughput(1000, 0)).toBe(FORMAT_PLACEHOLDER);
    });
});
