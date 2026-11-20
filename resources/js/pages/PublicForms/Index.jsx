import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Check, Copy, ExternalLink, Globe, Link2, Power, RefreshCw } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import { ActiveBadge } from '@/components/StatusBadge';
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

export default function Index({ branches }) {
    const confirm = useConfirm();

    const regenerate = async (b) => {
        if (await confirm({ title: `Reset URL for ${b.name}?`, message: 'A new secure URL will be issued and the current URL will stop working immediately. Existing leads are not affected.', confirmLabel: 'Reset URL', danger: true })) {
            router.post(`/public-forms/${b.form.id}/regenerate`, {}, { preserveScroll: true });
        }
    };
    const toggle = async (b) => {
        if (await confirm({ title: `${b.form.is_active ? 'Deactivate' : 'Activate'} public form?`, message: b.form.is_active ? 'Visitors will no longer be able to submit leads through this URL.' : 'The URL will accept submissions again.', confirmLabel: b.form.is_active ? 'Deactivate' : 'Activate', danger: b.form.is_active })) {
            router.patch(`/public-forms/${b.form.id}/toggle`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title="Public Lead Forms" breadcrumbs={[{ label: 'Public Lead Forms' }]}>
            <PageHeader title="Public lead forms" description="Each branch can have one public URL. Leads submitted through it are automatically assigned to that branch." />
            <div className="space-y-4">
                {branches.map((b) => (
                    <div key={b.id} className="card p-5">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link href={`/branches/${b.id}`} className="text-base font-semibold text-ink hover:text-primary-600">{b.name}</Link>
                                    <span className="rounded bg-subtle px-1.5 py-0.5 font-mono text-[11px] text-muted ring-1 ring-line">{b.code}</span>
                                    {!b.is_active && <span className="text-xs font-medium text-amber-600">Branch inactive — submissions blocked</span>}
                                    {b.form && <ActiveBadge active={b.form.is_active} activeLabel="Accepting submissions" inactiveLabel="Form disabled" />}
                                </div>
                                <p className="mt-1 text-sm text-muted">
                                    Public campaigns: {b.public_campaigns.length ? b.public_campaigns.join(', ') : <span className="text-amber-600">none — enable “Available on public forms” on a campaign assigned to this branch</span>}
                                </p>
                            </div>
                            {b.form && (
                                <div className="flex shrink-0 gap-2">
                                    <Button variant="secondary" size="sm" href={`/leads?branch_id=${b.id}&source=public`}>{formatNumber(b.form.submissions_count)} submissions</Button>
                                    <Button variant="secondary" size="sm" icon={RefreshCw} onClick={() => regenerate(b)}>Reset URL</Button>
                                    <Button variant={b.form.is_active ? 'danger-ghost' : 'secondary'} size="sm" icon={Power} onClick={() => toggle(b)}>{b.form.is_active ? 'Deactivate' : 'Activate'}</Button>
                                </div>
                            )}
                        </div>
                        <div className="mt-4">
                            {b.form ? (
                                <>
                                    <CopyField url={b.form.url} />
                                    <p className="mt-2 text-xs text-muted">Created {formatDateTime(b.form.created_at)}{b.form.last_reset_at ? ` · Last reset ${formatDateTime(b.form.last_reset_at)}` : ''}</p>
                                </>
                            ) : (
                                <div className="flex items-center justify-between gap-4 rounded-lg border border-dashed border-line bg-subtle/60 px-4 py-3">
                                    <p className="flex items-center gap-2 text-sm text-muted"><Link2 className="h-4 w-4" /> No public URL generated yet.</p>
                                    <Button size="sm" icon={Globe} onClick={() => router.post(`/public-forms/${b.id}`, {}, { preserveScroll: true })}>Generate URL</Button>
                                </div>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}
