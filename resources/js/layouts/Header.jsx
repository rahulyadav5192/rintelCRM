import { Fragment } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, LogOut, Menu, Settings, UserRound } from 'lucide-react';
import Dropdown from '@/components/Dropdown';
import NotificationBell from './NotificationBell';
import { initials } from '@/lib/utils';

export default function Header({ title, breadcrumbs = [], onOpenMobile }) {
    const { auth } = usePage().props;
    const user = auth.user;

    return (
        <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button type="button" onClick={onOpenMobile} className="-ml-1 rounded-md p-1.5 text-muted hover:bg-subtle lg:hidden" aria-label="Open menu">
                <Menu className="h-5 w-5" />
            </button>

            <div className="min-w-0 flex-1">
                {breadcrumbs.length > 0 ? (
                    <nav className="flex items-center gap-1 overflow-hidden text-sm" aria-label="Breadcrumb">
                        {breadcrumbs.map((b, i) => (
                            <Fragment key={i}>
                                {i > 0 && <ChevronRight className="h-3.5 w-3.5 shrink-0 text-gray-300" />}
                                {b.href && i < breadcrumbs.length - 1 ? (
                                    <Link href={b.href} className="truncate text-muted hover:text-ink">
                                        {b.label}
                                    </Link>
                                ) : (
                                    <span className="truncate font-medium text-ink">{b.label}</span>
                                )}
                            </Fragment>
                        ))}
                    </nav>
                ) : (
                    <p className="truncate text-sm font-medium text-ink">{title}</p>
                )}
            </div>

            <div className="flex items-center gap-1">
                <NotificationBell />
                <Dropdown
                    trigger={(open) => (
                        <button type="button" className="flex items-center gap-2.5 rounded-lg py-1 pr-2 pl-1 hover:bg-subtle">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-gray-900 text-xs font-semibold text-white">{initials(user.name)}</span>
                            <span className="hidden text-left leading-tight md:block">
                                <span className="block text-sm font-medium text-ink">{user.name}</span>
                                <span className="block text-xs text-muted">{user.role_label}{user.branch ? ` · ${user.branch.name}` : ''}</span>
                            </span>
                            <ChevronDown className={`hidden h-4 w-4 text-muted transition-transform md:block ${open ? 'rotate-180' : ''}`} />
                        </button>
                    )}
                >
                    <div className="border-b border-line px-4 py-3">
                        <p className="truncate text-sm font-medium text-ink">{user.name}</p>
                        <p className="truncate text-xs text-muted">{user.email}</p>
                    </div>
                    <div className="p-1.5">
                        <Link href="/profile" className="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-gray-700 hover:bg-subtle">
                            <UserRound className="h-4 w-4 text-muted" /> My profile
                        </Link>
                        {auth.can.manageSettings && (
                            <Link href="/settings" className="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-gray-700 hover:bg-subtle">
                                <Settings className="h-4 w-4 text-muted" /> Settings
                            </Link>
                        )}
                        <button type="button" data-close onClick={() => router.post('/logout')} className="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-red-600 hover:bg-red-50">
                            <LogOut className="h-4 w-4" /> Sign out
                        </button>
                    </div>
                </Dropdown>
            </div>
        </header>
    );
}
