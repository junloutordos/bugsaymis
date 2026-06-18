import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // Scoped to .jsx only — used by a single isolated React island
        // (AWS Amplify Face Liveness has no Vue SDK). The rest of the
        // app is plain Vue 3.
        react({
            include: '**/*.jsx',
        }),
    ],
});
