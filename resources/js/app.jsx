import '../css/app.css';
import './bootstrap';

import en from "@shopify/polaris/locales/en.json";
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import "@/Assets/styles.css";
import { AppProvider } from '@shopify/polaris';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
window.appURL = import.meta.env.VITE_APP_URL;

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <AppProvider i18n={en}>
                <App {...props} />
            </AppProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
