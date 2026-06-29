import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Separate from vite.config.js so the Laravel plugin doesn't run during tests.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.spec.js'],
    },
});
