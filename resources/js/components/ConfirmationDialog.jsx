import { createContext, useCallback, useContext, useRef, useState } from 'react';
import { AlertTriangle } from 'lucide-react';
import Modal from './Modal';
import Button from './Button';

const ConfirmContext = createContext(null);

/**
 * Usage: const confirm = useConfirm();
 *        if (await confirm({ title, message, confirmLabel, danger: true })) { ... }
 */
export function useConfirm() {
    return useContext(ConfirmContext);
}

export function ConfirmProvider({ children }) {
    const [state, setState] = useState(null);
    const resolver = useRef(null);

    const confirm = useCallback((options) => {
        setState(options);
        return new Promise((resolve) => {
            resolver.current = resolve;
        });
    }, []);

    const close = (result) => {
        resolver.current?.(result);
        setState(null);
    };

    return (
        <ConfirmContext.Provider value={confirm}>
            {children}
            <ConfirmationDialog open={!!state} {...(state || {})} onCancel={() => close(false)} onConfirm={() => close(true)} />
        </ConfirmContext.Provider>
    );
}

export default function ConfirmationDialog({ open, title = 'Are you sure?', message, confirmLabel = 'Confirm', danger = false, onCancel, onConfirm }) {
    return (
        <Modal open={open} onClose={onCancel} size="sm">
            <div className="flex gap-4">
                <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${danger ? 'bg-red-50' : 'bg-primary-50'}`}>
                    <AlertTriangle className={`h-5 w-5 ${danger ? 'text-red-600' : 'text-primary-600'}`} />
                </div>
                <div>
                    <h3 className="text-base font-semibold text-ink">{title}</h3>
                    {message && <p className="mt-1 text-sm text-muted">{message}</p>}
                </div>
            </div>
            <div className="mt-6 flex justify-end gap-2">
                <Button variant="secondary" onClick={onCancel}>
                    Cancel
                </Button>
                <Button variant={danger ? 'danger' : 'primary'} onClick={onConfirm} autoFocus>
                    {confirmLabel}
                </Button>
            </div>
        </Modal>
    );
}
