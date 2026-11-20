import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ToastProvider } from '@/components/ToastNotifications';
import { ConfirmProvider } from '@/components/ConfirmationDialog';

const appName = import.meta.env.VITE_APP_NAME || 'Rintel Lead CRM';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.jsx', { eager: false });
        const page = pages[`./pages/${name}.jsx`];
        if (!page) throw new Error(`Page not found: ${name}`);
        return page();
    },
    setup({ el, App, props }) {
        // Providers sit above every page so pages themselves can use useConfirm()/useToast().
        createRoot(el).render(
            <App {...props}>
                {({ Component, props: pageProps, key }) => (
                    <ToastProvider>
                        <ConfirmProvider>
                            <Component key={key} {...pageProps} />
                        </ConfirmProvider>
                    </ToastProvider>
                )}
            </App>,
        );
    },
    progress: { color: '#1695E6', showSpinner: false },
});
