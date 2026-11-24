import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Check, Copy, ExternalLink, Globe, Link2, Plus, Power, RefreshCw } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import { ActiveBadge } from '@/components/StatusBadge';
import EmptyState from '@/components/EmptyState';
import { useConfirm } from '@/components/ConfirmationDialog';
import { useToast } from '@/components/ToastNotifications';
import { formatDateTime, formatNumber } from '@/lib/utils';

function CopyField({ url }) {
    const [copied, setCopied] = useState(false);
    const toast = useToast();
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            setTimeout(() => setCopied(false), 1800);
        } catch {
            toast('error', 'Copy failed — select the URL and copy it manually.');
        }
    };
    return (
        <div className="flex items-center gap-2">
            <input readOnly value={url} onFocus={(e) => e.target.select()} className="input h-9 font-mono text-xs" />
            <Button variant="secondary" size="icon" className="h-9 w-9 shrink-0" onClick={copy} aria-label="Copy URL" title="Copy URL">
                {copied ? <Check className="h-4 w-4 text-green-600" /> : <Copy className="h-4 w-4" />}
            </Button>
            <Button variant="secondary" size="icon" className="h-9 w-9 shrink-0" as="a" href={url} target="_blank" rel="noopener noreferrer" aria-label="Open form" title="Open form">
                <ExternalLink className="h-4 w-4" />
            </Button>
        </div>
    );
}

export default function Index({ admins, canManage }) {
    const confirm = useConfirm();

    const regenerate = async (a) => {
        if (await confirm({ title: `Reset URL for ${a.name}?`, message: 'A new secure URL will be issued and the current URL will stop working immediately. Existing leads are not affected.', confirmLabel: 'Reset URL', danger: true })) {
            router.post(`/public-forms/${a.form.id}/regenerate`, {}, { preserveScroll: true });
        }
    };
    const toggle = async (a) => {
        if (await confirm({ title: `${a.form.is_active ? 'Deactivate' : 'Activate'} public form?`, message: a.form.is_active ? 'Visitors will no longer be able to submit leads through this URL.' : 'The URL will accept submissions again.', confirmLabel: a.form.is_active ? 'Deactivate' : 'Activate', danger: a.form.is_active })) {
            router.patch(`/public-forms/${a.form.id}/toggle`, {}, { preserveScroll: true });
        }
    };

    const title = canManage ? 'Public lead forms' : 'My public lead form';

    return (
        <AppLayout title={title} breadcrumbs={[{ label: title }]}>
            <PageHeader
                title={title}
                description={canManage ? 'Every admin has one public URL. Leads submitted through it are owned by that admin.' : 'Share this link with customers. Every lead submitted through it is added to your leads.'}
                actions={canManage && <Button variant="secondary" href="/users/create?role=admin" icon={Plus}>New admin</Button>}
            />
            {admins.length === 0 ? (
                <div className="card">
                    <EmptyState icon={Globe} title="No admins yet" description="Create an admin and a public URL is generated for them automatically." action={canManage && <Button href="/users/create?role=admin" icon={Plus}>New admin</Button>} />
                </div>
            ) : (
                <div className="space-y-4">
                    {admins.map((a) => (
                        <div key={a.id} className="card p-5">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        {canManage ? (
                                            <Link href={`/users/${a.id}`} className="text-base font-semibold text-ink hover:text-primary-600">{a.name}</Link>
                                        ) : (
                                            <span className="text-base font-semibold text-ink">{a.name}</span>
                                        )}
                                        <span className="text-sm text-muted">{a.email}</span>
                                        {!a.is_active && <span className="text-xs font-medium text-amber-600">Admin inactive — submissions blocked</span>}
                                        {a.form && <ActiveBadge active={a.form.is_active} activeLabel="Accepting submissions" inactiveLabel="Form disabled" />}
                                    </div>
                                    <p className="mt-1 text-sm text-muted">
                                        Campaigns on this form:{' '}
                                        {a.public_campaigns.length ? a.public_campaigns.join(', ') : <span className="text-amber-600">none — the admin needs an active campaign with “Available on public lead forms” enabled</span>}
                                    </p>
                                </div>
                                {a.form && (
                                    <div className="flex shrink-0 flex-wrap gap-2">
                                        <Button variant="secondary" size="sm" href={`/leads?source=public${canManage ? `&admin_id=${a.id}` : ''}`}>{formatNumber(a.form.submissions_count)} submissions</Button>
                                        {canManage && (
                                            <>
                                                <Button variant="secondary" size="sm" icon={RefreshCw} onClick={() => regenerate(a)}>Reset URL</Button>
                                                <Button variant={a.form.is_active ? 'danger-ghost' : 'secondary'} size="sm" icon={Power} onClick={() => toggle(a)}>{a.form.is_active ? 'Deactivate' : 'Activate'}</Button>
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>
                            <div className="mt-4">
                                {a.form ? (
                                    <>
                                        <CopyField url={a.form.url} />
                                        <p className="mt-2 text-xs text-muted">Created {formatDateTime(a.form.created_at)}{a.form.last_reset_at ? ` · Last reset ${formatDateTime(a.form.last_reset_at)}` : ''}</p>
                                    </>
                                ) : (
                                    <div className="flex items-center justify-between gap-4 rounded-lg border border-dashed border-line bg-subtle/60 px-4 py-3">
                                        <p className="flex items-center gap-2 text-sm text-muted"><Link2 className="h-4 w-4" /> No public URL generated yet.</p>
                                        {canManage && <Button size="sm" icon={Globe} onClick={() => router.post(`/public-forms/admins/${a.id}`, {}, { preserveScroll: true })}>Generate URL</Button>}
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
