import { describe, expect, it } from 'vitest';
import { importRangeSchema } from '@/lib/schemas/import';

describe('importRangeSchema', () => {
    it('accepts a valid range', () => {
        const result = importRangeSchema.safeParse({
            range_start: '2024-01-01',
            range_end: '2024-12-31',
        });

        expect(result.success).toBe(true);
    });

    it('requires both dates', () => {
        const result = importRangeSchema.safeParse({
            range_start: '',
            range_end: '',
        });

        expect(result.success).toBe(false);
    });

    it('rejects an end before the start', () => {
        const result = importRangeSchema.safeParse({
            range_start: '2024-12-31',
            range_end: '2024-01-01',
        });

        expect(result.success).toBe(false);
    });
});
