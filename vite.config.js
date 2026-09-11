import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const devHost = process.env.FEROSA_DEV_HOST;

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin/dashboard.js',
                'resources/js/admin/notifications.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Without this the dev server binds to localhost only, and the `hot`
        // file it writes tells Laravel to emit http://[::1]:5173 asset URLs.
        // A phone or emulator resolves [::1] to itself, so the whole site
        // loads with no CSS and no JS. Set FEROSA_DEV_HOST to your machine's
        // LAN IP when developing against the Android app.
        //
        // For plain browser work, leave it unset - nothing changes.
        ...(devHost ? { host: devHost, hmr: { host: devHost } } : {}),
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
