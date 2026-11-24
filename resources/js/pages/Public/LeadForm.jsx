import { useEffect, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Loader2, ShieldCheck } from 'lucide-react';
import Button from '@/components/Button';
import FormInput from '@/components/FormInput';
import FormSelect from '@/components/FormSelect';
import FormTextarea from '@/components/FormTextarea';
import { getJson } from '@/lib/utils';

export default function LeadForm({ available, campaigns, titles, formToken, texts, submitted }) {
    const token = typeof window !== 'undefined' ? window.location.pathname.split('/').filter(Boolean).pop() : '';
    const [options, setOptions] = useState({ managers: [], lead_statuses: [], payment_statuses: [] });
    const [loading, setLoading] = useState(false);
    const form = useForm({ title: '', first_name: '', last_name: '', mobile: '', email: '', house_number: '', post_code: '', campaign_id: campaigns.length === 1 ? campaigns[0].id : '', assigned_to: '', lead_status_id: '', payment_status_id: '', notes: '', website: '', form_token: formToken });
    const { data, setData, errors } = form;
    const campaign = campaigns.find((c) => String(c.id) === String(data.campaign_id));

    useEffect(() => {
        if (!data.campaign_id) return setOptions({ managers: [], lead_statuses: [], payment_statuses: [] });
        setLoading(true);
        getJson(`/public/leads/${token}/campaigns/${data.campaign_id}`)
            .then(setOptions)
            .catch(() => setOptions({ managers: [], lead_statuses: [], payment_statuses: [] }))
            .finally(() => setLoading(false));
    }, [data.campaign_id]); // eslint-disable-line react-hooks/exhaustive-deps

    const submit = (e) => {
        e.preventDefault();
        form.post(`/public/leads/${token}`, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <div className="min-h-screen bg-subtle px-4 py-10 sm:py-16">
            <Head title={texts.heading} />
            <div className="mx-auto max-w-2xl">
                <div className="mb-8 flex items-center gap-2.5">
                    <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-500 text-base font-bold text-white">R</span>
                    <span className="text-base font-semibold text-ink">Rintel</span>
                </div>

                {!available ? (
                    <div className="card p-10 text-center">
                        <AlertCircle className="mx-auto h-10 w-10 text-gray-300" />
                        <h1 className="mt-4 text-lg font-semibold text-ink">This form is not available</h1>
                        <p className="mt-1 text-sm text-muted">The link may have expired or been replaced. Please contact us for an up-to-date link.</p>
                    </div>
                ) : submitted ? (
                    <div className="card p-10 text-center">
                        <CheckCircle2 className="mx-auto h-12 w-12 text-green-500" />
                        <h1 className="mt-4 text-lg font-semibold text-ink">Submission received</h1>
                        <p className="mx-auto mt-2 max-w-md text-sm text-muted">{texts.success}</p>
                        <Button variant="secondary" className="mt-6" onClick={() => window.location.reload()}>Submit another enquiry</Button>
                    </div>
                ) : campaigns.length === 0 ? (
                    <div className="card p-10 text-center">
                        <AlertCircle className="mx-auto h-10 w-10 text-gray-300" />
                        <h1 className="mt-4 text-lg font-semibold text-ink">No campaigns are currently available</h1>
                        <p className="mt-1 text-sm text-muted">Please check back later.</p>
                    </div>
                ) : (
                    <form onSubmit={submit} className="card overflow-hidden" noValidate>
                        <div className="border-b border-line px-6 py-6 sm:px-8">
                            <h1 className="text-xl font-semibold tracking-tight text-ink">{texts.heading}</h1>
                            {texts.intro && <p className="mt-1.5 text-sm text-muted">{texts.intro}</p>}
                        </div>
                        <div className="space-y-5 px-6 py-6 sm:px-8">
                            {errors.form && <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />{errors.form}</div>}
                            {/* Honeypot: hidden from people, tempting to bots. */}
                            <div aria-hidden="true" className="absolute -left-[9999px] h-0 w-0 overflow-hidden">
                                <label>Website<input type="text" tabIndex={-1} autoComplete="off" value={data.website} onChange={(e) => setData('website', e.target.value)} /></label>
                            </div>

                            <FormSelect label="Campaign" name="campaign_id" value={data.campaign_id} onChange={(e) => setData((d) => ({ ...d, campaign_id: e.target.value, assigned_to: '', lead_status_id: '', payment_status_id: '' }))} options={campaigns.map((c) => ({ value: c.id, label: c.name }))} placeholder="Choose a campaign" error={errors.campaign_id} required />
                            {campaign?.description && <p className="-mt-3 text-xs text-muted">{campaign.description}</p>}

                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-6">
                                <FormSelect label="Title" name="title" className="sm:col-span-1" value={data.title} onChange={(e) => setData('title', e.target.value)} options={titles.map((t) => ({ value: t, label: `${t}.` }))} placeholder="—" error={errors.title} />
                                <FormInput label="First name" name="first_name" className="sm:col-span-2" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} error={errors.first_name} required autoComplete="given-name" />
                                <FormInput label="Last name" name="last_name" className="sm:col-span-3" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} error={errors.last_name} required autoComplete="family-name" />
                                <FormInput label="Mobile number" name="mobile" type="tel" className="sm:col-span-3" value={data.mobile} onChange={(e) => setData('mobile', e.target.value)} error={errors.mobile} required autoComplete="tel" />
                                <FormInput label="Email" name="email" type="email" className="sm:col-span-3" value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} autoComplete="email" />
                                <FormInput label="House number" name="house_number" className="sm:col-span-3" value={data.house_number} onChange={(e) => setData('house_number', e.target.value)} error={errors.house_number} />
                                <FormInput label="Post code" name="post_code" className="sm:col-span-3" value={data.post_code} onChange={(e) => setData('post_code', e.target.value.toUpperCase())} error={errors.post_code} required autoComplete="postal-code" />
                            </div>

                            {data.campaign_id && (
                                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    <FormSelect label="Preferred advisor" name="assigned_to" value={data.assigned_to} onChange={(e) => setData('assigned_to', e.target.value)} options={options.managers.map((m) => ({ value: m.id, label: m.name }))} placeholder={loading ? 'Loading…' : options.managers.length ? 'No preference' : 'Any available advisor'} disabled={loading || !options.managers.length} error={errors.assigned_to} />
                                    {campaign?.show_statuses && options.lead_statuses.length > 0 && (
                                        <>
                                            <FormSelect label="Enquiry stage" name="lead_status_id" value={data.lead_status_id} onChange={(e) => setData('lead_status_id', e.target.value)} options={options.lead_statuses.map((s) => ({ value: s.id, label: s.name }))} placeholder="Default" error={errors.lead_status_id} />
                                            <FormSelect label="Payment status" name="payment_status_id" value={data.payment_status_id} onChange={(e) => setData('payment_status_id', e.target.value)} options={options.payment_statuses.map((s) => ({ value: s.id, label: s.name }))} placeholder="Default" error={errors.payment_status_id} />
                                        </>
                                    )}
                                </div>
                            )}

                            <FormTextarea label="Anything else we should know?" name="notes" rows={4} value={data.notes} onChange={(e) => setData('notes', e.target.value)} error={errors.notes} />
                        </div>
                        <div className="flex flex-col-reverse gap-3 border-t border-line bg-subtle/60 px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                            <p className="flex items-center gap-1.5 text-xs text-muted"><ShieldCheck className="h-3.5 w-3.5" /> Your details are sent securely and only used to contact you.</p>
                            <Button type="submit" size="lg" loading={form.processing}>{form.processing ? 'Sending…' : 'Submit enquiry'}</Button>
                        </div>
                    </form>
                )}
                {loading && <Loader2 className="sr-only" />}
            </div>
        </div>
    );
}
