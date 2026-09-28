import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite-plus';

/**
 * Frontend test runner (FND-09).
 *
 * Vitest is bundled with Vite+ and runs through `vp test`. A dedicated config
 * keeps the Laravel/Inertia/Wayfinder Vite plugins out of unit tests.
 */
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./resources/js/test/setup.ts'],
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
        clearMocks: true,
        restoreMocks: true,
        css: false,
    },
});
