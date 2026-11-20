import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/** Minimal accessible popover menu (closes on outside click / Escape). */
export default function Dropdown({ trigger, children, align = 'right', width = 'w-56', onOpenChange }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    const update = (v) => {
        setOpen(v);
        onOpenChange?.(v);
    };

    useEffect(() => {
        if (!open) return;
        const onClick = (e) => ref.current && !ref.current.contains(e.target) && update(false);
        const onKey = (e) => e.key === 'Escape' && update(false);
        document.addEventListener('mousedown', onClick);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onClick);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div className="relative" ref={ref}>
            <div onClick={() => update(!open)}>{trigger(open)}</div>
            {open && (
                <div className={cn('absolute z-40 mt-2 overflow-hidden rounded-xl border border-line bg-white shadow-lg', width, align === 'right' ? 'right-0' : 'left-0')} onClick={(e) => e.target.closest('a,button[data-close]') && update(false)}>
                    {children}
                </div>
            )}
        </div>
    );
}
