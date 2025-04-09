import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    server: {
        host: 'localhost',    // Use 'localhost' instead of '[::1]' or '0.0.0.0'
        port: 5173,           // Default Vite port
        cors: true,           // Enable CORS handling
    },
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
});
