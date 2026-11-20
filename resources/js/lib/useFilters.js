import { useCallback, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Keeps listing filters in the query string. Every change triggers a server round-trip —
 * filtering, sorting and pagination are always done by the backend.
 */
export default function useFilters(url, initial = {}) {
    const [filters, setFilters] = useState(initial);
    const [loading, setLoading] = useState(false);
    const timer = useRef();

    useEffect(() => setFilters(initial), [JSON.stringify(initial)]); // eslint-disable-line react-hooks/exhaustive-deps

    const visit = useCallback(
        (next) => {
            const clean = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '' && v !== null && v !== undefined));
            router.get(url, clean, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            });
        },
        [url],
    );

    const set = useCallback(
        (key, value, { debounce = 0 } = {}) => {
            setFilters((prev) => {
                const next = { ...prev, [key]: value };
                delete next.page;
                clearTimeout(timer.current);
                if (debounce) timer.current = setTimeout(() => visit(next), debounce);
                else visit(next);
                return next;
            });
        },
        [visit],
    );

    const setMany = useCallback(
        (values) => {
            setFilters((prev) => {
                const next = { ...prev, ...values };
                delete next.page;
                visit(next);
                return next;
            });
        },
        [visit],
    );

    const sort = useCallback(
        (key) => {
            const direction = filters.sort === key && filters.direction !== 'desc' ? 'desc' : 'asc';
            setMany({ sort: key, direction });
        },
        [filters, setMany],
    );

    const reset = useCallback(() => {
        setFilters({});
        visit({});
    }, [visit]);

    const activeCount = Object.entries(filters).filter(([k, v]) => !['sort', 'direction', 'page', 'per_page'].includes(k) && v !== '' && v != null).length;

    return { filters, set, setMany, sort, reset, loading, activeCount };
}
