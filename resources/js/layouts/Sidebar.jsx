import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Activity, BarChart3, ChevronDown, ListChecks, ChevronsLeft, ChevronsRight, Globe, LayoutDashboard, Megaphone, Settings, Users, UsersRound, X, FolderKanban } from 'lucide-react';
import { cn } from '@/lib/utils';

function Logo({ collapsed }) {
    return (
        <Link href="/dashboard" className="flex items-center gap-2.5 px-1">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-500 text-sm font-bold text-white">R</span>
            {!collapsed && (
                <span className="leading-tight">
                    <span className="block text-sm font-semibold text-ink">Rintel</span>
                    <span className="block text-[11px] text-muted">Lead CRM</span>
                </span>
            )}
        </Link>
    );
}

function NavItem({ href, icon: Icon, label, active, collapsed, onNavigate }) {
    return (
        <Link
            href={href}
            onClick={onNavigate}
            title={collapsed ? label : undefined}
            className={cn(
                'group flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors',
                active ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-subtle hover:text-ink',
                collapsed && 'justify-center px-0',
            )}
        >
            <Icon className={cn('h-[18px] w-[18px] shrink-0', active ? 'text-primary-600' : 'text-gray-400 group-hover:text-gray-600')} />
            {!collapsed && <span className="truncate">{label}</span>}
        </Link>
    );
}

export default function Sidebar({ collapsed, onToggleCollapse, mobileOpen, onCloseMobile }) {
    const { auth } = usePage().props;
    const url = usePage().url;
    const path = url.split('?')[0];
    const query = new URLSearchParams(url.split('?')[1] ?? '');
    const can = auth.can;
    const campaigns = auth.campaigns ?? [];
    const [campaignsOpen, setCampaignsOpen] = useState(true);

    const isActive = (prefix) => path === prefix || path.startsWith(`${prefix}/`);
    const leadsActive = isActive('/leads') && !(path === '/leads' && query.get('campaign_id') && campaigns.length);

    const main = [
        { href: '/dashboard', label: 'Dashboard', icon: LayoutDashboard, show: true },
        { href: '/leads', label: 'Leads', icon: UsersRound, show: true, active: leadsActive },
        { href: '/campaigns', label: 'Campaigns', icon: Megaphone, show: can.viewCampaigns },
        { href: '/statuses', label: 'Status Management', icon: ListChecks, show: can.manageStatuses },
        { href: '/users', label: 'Users', icon: Users, show: can.viewUsers },
        { href: '/public-forms', label: can.managePublicForms ? 'Public Lead Forms' : 'My Public Form', icon: Globe, show: can.managePublicForms || can.viewOwnPublicForm },
        { href: '/reports', label: 'Reports', icon: BarChart3, show: can.viewReports },
        { href: '/activity-logs', label: 'Activity Logs', icon: Activity, show: can.viewActivityLogs },
        { href: '/settings', label: 'Settings', icon: Settings, show: can.manageSettings },
    ].filter((i) => i.show);

    const content = (
        <div className="flex h-full flex-col">
            <div className={cn('flex h-16 items-center border-b border-line px-4', collapsed ? 'justify-center' : 'justify-between')}>
                <Logo collapsed={collapsed} />
                <button type="button" onClick={onCloseMobile} className="rounded-md p-1 text-muted hover:bg-subtle lg:hidden" aria-label="Close menu">
                    <X className="h-5 w-5" />
                </button>
            </div>

            <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                {main.map((item) => (
                    <NavItem key={item.href} {...item} active={item.active ?? isActive(item.href)} collapsed={collapsed} onNavigate={onCloseMobile} />
                ))}

                {campaigns.length > 0 && (
                    <div className="pt-4">
                        {collapsed ? (
                            <div className="mx-auto my-2 h-px w-6 bg-line" />
                        ) : (
                            <button type="button" onClick={() => setCampaignsOpen(!campaignsOpen)} className="flex w-full items-center justify-between px-2.5 pb-1.5 text-[11px] font-semibold tracking-wider text-gray-400 uppercase hover:text-gray-600">
                                My campaigns
                                <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', !campaignsOpen && '-rotate-90')} />
                            </button>
                        )}
                        {(campaignsOpen || collapsed) &&
                            campaigns.map((c) => {
                                const active = path === '/leads' && query.get('campaign_id') === String(c.id);
                                return collapsed ? (
                                    <NavItem key={c.id} href={`/leads?campaign_id=${c.id}`} icon={FolderKanban} label={c.name} active={active} collapsed onNavigate={onCloseMobile} />
                                ) : (
                                    <Link
                                        key={c.id}
                                        href={`/leads?campaign_id=${c.id}`}
                                        onClick={onCloseMobile}
                                        className={cn('flex items-center gap-3 rounded-lg py-1.5 pr-2.5 pl-3 text-sm transition-colors', active ? 'bg-primary-50 font-medium text-primary-700' : 'text-gray-600 hover:bg-subtle hover:text-ink')}
                                    >
                                        <span className={cn('flex h-5 min-w-5 items-center justify-center rounded px-1 text-[10px] font-semibold', active ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-500')}>{c.code?.slice(0, 3)}</span>
                                        <span className="truncate">{c.name}</span>
                                    </Link>
                                );
                            })}
                    </div>
                )}
            </nav>

            <div className="hidden border-t border-line p-3 lg:block">
                <button type="button" onClick={onToggleCollapse} className={cn('flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-muted hover:bg-subtle hover:text-ink', collapsed && 'justify-center')}>
                    {collapsed ? <ChevronsRight className="h-4 w-4" /> : <ChevronsLeft className="h-4 w-4" />}
                    {!collapsed && 'Collapse'}
                </button>
            </div>
        </div>
    );

    return (
        <>
            <aside className={cn('fixed inset-y-0 left-0 z-30 hidden border-r border-line bg-white transition-[width] duration-200 lg:block', collapsed ? 'w-[72px]' : 'w-64')}>{content}</aside>
            {mobileOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-gray-950/40" onClick={onCloseMobile} />
                    <aside className="absolute inset-y-0 left-0 w-72 bg-white shadow-xl">{content}</aside>
                </div>
            )}
        </>
    );
}
