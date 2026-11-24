import { Head, useForm } from '@inertiajs/react';
import { Lock, Mail } from 'lucide-react';
import Button from '@/components/Button';
import FormInput from '@/components/FormInput';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', password: '', remember: false });

    const submit = (e) => {
        e.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    };

    return (
        <div className="grid min-h-screen lg:grid-cols-2">
            <Head title="Sign in" />
            <div className="flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-20">
                <div className="mx-auto w-full max-w-sm">
                    <div className="mb-10 flex items-center gap-2.5">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-500 text-base font-bold text-white">R</span>
                        <span className="text-lg font-semibold text-ink">Rintel Lead CRM</span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">Sign in to your account</h1>
                    <p className="mt-2 text-sm text-muted">Use the credentials provided by your administrator.</p>

                    <form onSubmit={submit} className="mt-8 space-y-5">
                        <FormInput label="Email address" name="email" type="email" icon={Mail} value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} autoComplete="username" autoFocus required />
                        <FormInput label="Password" name="password" type="password" icon={Lock} value={data.password} onChange={(e) => setData('password', e.target.value)} error={errors.password} autoComplete="current-password" required />
                        <label className="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" className="h-4 w-4 rounded border-gray-300 accent-primary-500" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                            Keep me signed in
                        </label>
                        <Button type="submit" size="lg" className="w-full" loading={processing}>
                            Sign in
                        </Button>
                    </form>
                    <p className="mt-8 text-xs text-muted">Forgot your password? Contact your Super Admin to reset it.</p>
                </div>
            </div>
            <div className="hidden border-l border-line bg-subtle lg:flex lg:flex-col lg:justify-center lg:px-16">
                <div className="max-w-md">
                    <p className="text-sm font-medium text-primary-600">Lead management</p>
                    <h2 className="mt-3 text-3xl font-semibold tracking-tight text-ink">Every campaign and lead — in one place.</h2>
                    <ul className="mt-8 space-y-4 text-sm text-gray-600">
                        {['Campaign-specific statuses and payment tracking', 'Admins see only their own leads; managers work their campaigns', 'A secure public lead form for every admin', 'Full audit trail of every change'].map((t) => (
                            <li key={t} className="flex items-start gap-3">
                                <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500" />
                                {t}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}
