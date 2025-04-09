import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    server: {
        host: '127.0.0.1', // or use 'localhost'
        port: 5173,        // you can change this if needed
        hmr: {
            host: 'localhost', // for HMR to work correctly on local
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
});
