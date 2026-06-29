import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Separate from vite.config.js so the Laravel plugin doesn't run during tests.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.spec.js'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'text-summary'],
            include: ['resources/js/**/*.{vue,js}'],
            // Entry/bootstrap and the specs themselves aren't unit-testable targets.
            exclude: ['resources/js/**/*.spec.js', 'resources/js/app.js', 'resources/js/bootstrap.js'],
            // Guard line coverage against regressions (we're at ~98%). Branches/
            // functions aren't gated — some handlers/OCR paths stay UI-only.
            thresholds: { lines: 90, statements: 90 },
        },
    },
});
