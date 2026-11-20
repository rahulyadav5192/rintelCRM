import { useCallback, useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import Dropdown from '@/components/Dropdown';
import LoadingState from '@/components/LoadingState';
import { cn, getJson, timeAgo } from '@/lib/utils';

function csrf() {
    return decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
}

export default function NotificationBell() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);

    const load = useCallback(() => {
        setLoading(true);
        getJson('/notifications')
            .then(setData)
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        load();
        const id = setInterval(load, 120000);
        return () => clearInterval(id);
    }, [load]);

    const onOpenChange = (open) => {
        if (open) {
            load();
        } else if (data?.unread) {
            fetch('/notifications/read', { method: 'POST', credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': csrf(), Accept: 'application/json' } }).then(() => setData((d) => ({ ...d, unread: 0, items: d.items.map((i) => ({ ...i, unread: false })) })));
        }
    };

    return (
        <Dropdown
            width="w-80 sm:w-96"
            onOpenChange={onOpenChange}
            trigger={() => (
                <button type="button" className="relative rounded-lg p-2 text-muted transition-colors hover:bg-subtle hover:text-ink" aria-label="Notifications">
                    <Bell className="h-5 w-5" />
                    {data?.unread > 0 && <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary-500 px-1 text-[10px] font-semibold text-white">{data.unread > 9 ? '9+' : data.unread}</span>}
                </button>
            )}
        >
            <div className="flex items-center justify-between border-b border-line px-4 py-3">
                <p className="text-sm font-semibold text-ink">Notifications</p>
                {data?.unread > 0 && <span className="text-xs text-muted">{data.unread} new</span>}
            </div>
            <div className="max-h-96 overflow-y-auto">
                {loading && !data ? (
                    <LoadingState />
                ) : !data?.items?.length ? (
                    <p className="px-4 py-10 text-center text-sm text-muted">You're all caught up.</p>
                ) : (
                    data.items.map((n) => (
                        <Link key={n.id} href={`/leads/${n.lead_id}`} className={cn('flex gap-3 border-b border-line/70 px-4 py-3 last:border-0 hover:bg-subtle', n.unread && 'bg-primary-50/40')}>
                            <span className={cn('mt-1.5 h-2 w-2 shrink-0 rounded-full', n.unread ? 'bg-primary-500' : 'bg-transparent')} />
                            <span className="min-w-0">
                                <span className="block text-sm text-ink">{n.description}</span>
                                <span className="mt-0.5 block text-xs text-muted">
                                    {n.lead_number} · {n.user} · {timeAgo(n.created_at)}
                                </span>
                            </span>
                        </Link>
                    ))
                )}
            </div>
        </Dropdown>
    );
}
