import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import Sidebar from './Sidebar';
import Header from './Header';
import { cn } from '@/lib/utils';

const STORAGE_KEY = 'rintel.sidebar.collapsed';

function readCollapsed() {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

export default function AppLayout({ title, breadcrumbs, children }) {
    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        } catch {
            /* storage unavailable */
        }
    }, [collapsed]);

    return (
        <>
            <Head title={title} />
                <div className="min-h-screen bg-subtle">
                    <Sidebar collapsed={collapsed} onToggleCollapse={() => setCollapsed(!collapsed)} mobileOpen={mobileOpen} onCloseMobile={() => setMobileOpen(false)} />
                    <div className={cn('transition-[padding] duration-200', collapsed ? 'lg:pl-[72px]' : 'lg:pl-64')}>
                        <Header title={title} breadcrumbs={breadcrumbs} onOpenMobile={() => setMobileOpen(true)} />
                        <main className="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">{children}</main>
                    </div>
                </div>
        </>
    );
}
