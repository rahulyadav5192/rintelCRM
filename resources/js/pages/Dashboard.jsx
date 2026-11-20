import { Link, usePage } from '@inertiajs/react';
import { Activity, Building2, CalendarClock, CalendarDays, CalendarRange, Megaphone, Plus, UserCheck, UserRoundX, Users, UsersRound, AlertCircle } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Card from '@/components/Card';
import Button from '@/components/Button';
import FilterDropdown from '@/components/FilterDropdown';
import StatusBadge from '@/components/StatusBadge';
import EmptyState from '@/components/EmptyState';
import { BarList, StatCard, TrendChart } from '@/components/Charts';
import useFilters from '@/lib/useFilters';
import { formatDate, timeAgo, withQuery } from '@/lib/utils';

export default function Dashboard({ role, cards, charts, recentActivity, followUps, filters: initial, range, filterOptions }) {
    const { auth } = usePage().props;
    const { filters, set, reset, loading, activeCount } = useFilters('/dashboard', initial);
    const isSuper = role === 'super_admin';
    const isManager = role === 'manager';
    const leadsUrl = (extra = {}) => withQuery('/leads', { campaign_id: filters.campaign_id, branch_id: filters.branch_id, date_from: filters.date_from, date_to: filters.date_to, ...extra });
    const today = new Date().toISOString().slice(0, 10);
    const monthStart = today.slice(0, 8) + '01';

    const statCards = [
        { label: isSuper ? 'Total leads' : isManager ? 'Accessible leads' : 'Total branch leads', value: cards.total, icon: UsersRound, href: leadsUrl() },
        { label: 'Leads today', value: cards.today, icon: CalendarDays, href: leadsUrl({ date_from: today, date_to: today }) },
        { label: 'Leads this month', value: cards.month, icon: CalendarRange, href: leadsUrl({ date_from: monthStart, date_to: today }) },
        ...(isSuper
            ? [
                  { label: 'Branches', value: cards.branches, icon: Building2, hint: `${cards.active_branches} active`, href: '/branches' },
                  { label: 'Campaigns', value: cards.campaigns, icon: Megaphone, hint: `${cards.active_campaigns} active`, href: '/campaigns' },
                  { label: 'Users', value: cards.users, icon: Users, hint: `${cards.active_managers} active managers`, href: '/users' },
              ]
            : isManager
              ? [
                    { label: 'Assigned to me', value: cards.assigned, icon: UserCheck, href: leadsUrl({ assigned_to: auth.user.id }) },
                    { label: 'Follow-ups due', value: cards.follow_ups, icon: CalendarClock, hint: 'Due today or overdue' },
                ]
              : [
                    { label: 'Unassigned leads', value: cards.unassigned, icon: UserRoundX, href: leadsUrl({ assigned_to: 'unassigned' }) },
                    { label: 'Active managers', value: cards.managers, icon: Users },
                ]),
    ];

    return (
        <AppLayout title="Dashboard">
            <PageHeader
                title={`Welcome back, ${auth.user.name.split(' ')[0]}`}
                description={isSuper ? 'Overview of lead activity across all branches and campaigns.' : isManager ? 'Your campaigns and assigned leads at a glance.' : `Lead activity for ${auth.user.branch?.name ?? 'your branch'}.`}
                actions={
                    auth.can.createLeads && (
                        <Button href="/leads/create" icon={Plus}>
                            New lead
                        </Button>
                    )
                }
            />

            <div className="mb-6 flex flex-wrap items-center gap-2">
                <input type="date" aria-label="From date" title="From date" value={filters.date_from ?? ''} max={filters.date_to || today} onChange={(e) => set('date_from', e.target.value)} className="input h-9 w-auto" />
                <span className="text-sm text-muted">to</span>
                <input type="date" aria-label="To date" title="To date" value={filters.date_to ?? ''} min={filters.date_from || undefined} max={today} onChange={(e) => set('date_to', e.target.value)} className="input h-9 w-auto" />
                {isSuper && <FilterDropdown className="w-44" label="Branches" value={filters.branch_id} onChange={(v) => set('branch_id', v)} options={filterOptions.branches.map((b) => ({ value: b.id, label: b.name }))} />}
                {filterOptions.campaigns.length > 1 && <FilterDropdown className="w-44" label="Campaigns" value={filters.campaign_id} onChange={(v) => set('campaign_id', v)} options={filterOptions.campaigns.map((c) => ({ value: c.id, label: c.name }))} />}
                {activeCount > 0 && (
                    <Button variant="ghost" size="sm" onClick={reset}>
                        Reset
                    </Button>
                )}
                {loading && <span className="text-xs text-muted">Updating…</span>}
            </div>

            <div className={`grid grid-cols-1 gap-4 sm:grid-cols-2 ${statCards.length > 5 ? 'xl:grid-cols-6' : 'xl:grid-cols-5'}`}>
                {statCards.map((c) => (
                    <StatCard key={c.label} {...c} />
                ))}
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
                <Card title="Daily lead generation" description={`${formatDate(range.from)} – ${formatDate(range.to)}`} className="xl:col-span-2">
                    <TrendChart data={charts.daily.map((d) => ({ ...d, tooltipLabel: formatDate(d.date) }))} />
                </Card>
                <Card title="Leads by campaign">
                    <BarList data={charts.byCampaign} onSelect={(r) => (window.location.href = leadsUrl({ campaign_id: r.id }))} />
                </Card>
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
                <Card title="Leads by status" description="Statuses with the same name are combined across campaigns">
                    <BarList data={charts.byStatus} />
                </Card>
                <Card title="Payment status summary">
                    <BarList data={charts.byPaymentStatus} />
                </Card>
                {isSuper ? (
                    <Card title="Leads by branch">
                        <BarList data={charts.byBranch} onSelect={(r) => (window.location.href = leadsUrl({ branch_id: r.id }))} />
                    </Card>
                ) : (
                    <Card title={isManager ? 'Advisor distribution' : 'Manager-wise distribution'}>
                        <BarList data={charts.byManager} />
                    </Card>
                )}
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
                {isSuper && (
                    <Card title="Monthly lead generation" description="Last 12 months" className="xl:col-span-2">
                        <TrendChart data={charts.monthly} />
                    </Card>
                )}
                {isManager && (
                    <Card title="Upcoming follow-ups" description="Due within the next 7 days" className="xl:col-span-2" padded={false}>
                        {followUps.length === 0 ? (
                            <EmptyState icon={CalendarClock} title="No follow-ups due" description="Set a follow-up date on a lead to see it here." />
                        ) : (
                            <ul className="divide-y divide-line">
                                {followUps.map((f) => (
                                    <li key={f.id}>
                                        <Link href={`/leads/${f.id}`} className="flex items-center justify-between gap-4 px-5 py-3 hover:bg-subtle">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-ink">{f.name}</p>
                                                <p className="text-xs text-muted">
                                                    {f.lead_number} · {f.campaign}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-3">
                                                <StatusBadge color={f.lead_status?.color}>{f.lead_status?.name}</StatusBadge>
                                                <span className={`inline-flex items-center gap-1 text-xs font-medium ${f.overdue ? 'text-red-600' : 'text-gray-600'}`}>
                                                    {f.overdue && <AlertCircle className="h-3.5 w-3.5" />}
                                                    {formatDate(f.follow_up_date)}
                                                </span>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                )}
                <Card title="Recent lead activity" padded={false} className={isSuper || isManager ? '' : 'xl:col-span-3'}>
                    {recentActivity.length === 0 ? (
                        <EmptyState icon={Activity} title="No recent activity" />
                    ) : (
                        <ul className="divide-y divide-line">
                            {recentActivity.map((a) => (
                                <li key={a.id}>
                                    <Link href={`/leads/${a.lead_id}`} className="block px-5 py-3 hover:bg-subtle">
                                        <p className="text-sm text-ink">{a.description}</p>
                                        <p className="mt-0.5 text-xs text-muted">
                                            {a.lead_number} {a.lead_name && `· ${a.lead_name}`} · {a.user} · {timeAgo(a.created_at)}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
