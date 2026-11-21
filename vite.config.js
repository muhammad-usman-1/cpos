import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny, google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                // Downloaded at build time and served from the shop PC, so they work without internet.
                google('Noto Naskh Arabic', {
                    weights: [400, 500, 600, 700],
                    subsets: ['arabic', 'latin'],
                }),
                google('Inconsolata', {
                    weights: [400, 600, 800],
                    subsets: ['latin'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
