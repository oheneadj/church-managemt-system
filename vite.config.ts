import path from 'node:path';
import { gzipSync } from 'node:zlib';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, type Plugin } from 'vitest/config';

/** The largest any single script may be once gzipped, in kilobytes. A bigger one fails the build (React addendum, section 10). */
const MAX_GZIPPED_CHUNK_KB = 180;

/**
 * Fails the production build when a script grows past the budget, so a heavy dependency is noticed the day it is added.
 */
function bundleBudget(): Plugin {
    return {
        name: 'bundle-budget',
        apply: 'build',
        generateBundle(_options, bundle) {
            for (const file of Object.values(bundle)) {
                if (file.type !== 'chunk') continue;

                const gzippedKb = gzipSync(file.code).length / 1024;

                if (gzippedKb > MAX_GZIPPED_CHUNK_KB) {
                    this.error(`${file.fileName} is ${gzippedKb.toFixed(0)} kB gzipped; the budget is ${MAX_GZIPPED_CHUNK_KB} kB.`);
                }
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/main.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
        bundleBudget(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.{ts,tsx}'],
        setupFiles: ['resources/js/test/setup.ts'],
        restoreMocks: true,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
