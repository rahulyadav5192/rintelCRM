import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle, X } from 'lucide-react';

const ToastContext = createContext(() => {});

export function useToast() {
    return useContext(ToastContext);
}

let nextId = 1;

/** Shows server flash messages (success/error) and client-side toasts. */
export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);
    const { flash } = usePage().props;

    const push = useCallback((type, message) => {
        if (!message) return;
        const id = nextId++;
        setToasts((t) => [...t.slice(-3), { id, type, message }]);
        setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 4500);
    }, []);

    useEffect(() => {
        if (flash?.success) push('success', flash.success);
        if (flash?.error) push('error', flash.error);
    }, [flash, push]);

    // Network/HTTP errors on Inertia visits surface as a toast instead of a broken page.
    useEffect(
        () =>
            router.on('httpException', (event) => {
                const status = event.detail.response?.status;
                if (status === 403 || status === 419 || status >= 500) {
                    event.preventDefault();
                    push('error', status === 403 ? 'You are not authorized to perform this action.' : status === 419 ? 'Your session expired. Please refresh the page.' : 'Something went wrong. Please try again.');
                }
            }),
        [push],
    );

    return (
        <ToastContext.Provider value={push}>
            {children}
            <div className="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-full max-w-sm flex-col gap-2">
                {toasts.map((t) => (
                    <div key={t.id} className="animate-toast-in pointer-events-auto flex items-start gap-3 rounded-xl border border-line bg-white p-3.5 shadow-lg">
                        {t.type === 'success' ? <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-green-600" /> : <XCircle className="mt-0.5 h-5 w-5 shrink-0 text-red-600" />}
                        <p className="flex-1 text-sm text-ink">{t.message}</p>
                        <button type="button" onClick={() => setToasts((all) => all.filter((x) => x.id !== t.id))} className="text-muted hover:text-ink" aria-label="Dismiss">
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}
