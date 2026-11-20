import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

const widths = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' };

export default function Modal({ open, onClose, title, description, size = 'md', children, footer }) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose?.();
        document.addEventListener('keydown', onKey);
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = prev;
        };
    }, [open, onClose]);

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
            <div className="fixed inset-0 bg-gray-950/40 backdrop-blur-[1px]" onClick={onClose} />
            <div className={cn('relative w-full rounded-xl border border-line bg-white shadow-xl', widths[size])}>
                {title && (
                    <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                        <div>
                            <h3 className="text-base font-semibold text-ink">{title}</h3>
                            {description && <p className="mt-0.5 text-sm text-muted">{description}</p>}
                        </div>
                        <button type="button" onClick={onClose} className="rounded-md p-1 text-muted hover:bg-subtle hover:text-ink" aria-label="Close">
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                )}
                <div className="max-h-[70vh] overflow-y-auto px-5 py-4">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-line bg-subtle/60 px-5 py-3">{footer}</div>}
            </div>
        </div>,
        document.body,
    );
}
