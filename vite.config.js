import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/galaxy.js', 'resources/js/observing/workspace.js', 'resources/js/observing/journal.js', 'resources/js/observing/sync.js', 'resources/js/observing/night.js', 'resources/js/observing/shortlist.js'],
            refresh: true,
            // Fonts are vendored via @fontsource and imported in app.css, so
            // they bundle deterministically with no build-time network fetch.
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
