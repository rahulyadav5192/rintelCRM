import { Head, Link } from '@inertiajs/react';

const MESSAGES = {
    403: ['Access denied', "You don't have permission to view this page."],
    404: ['Page not found', "The page you're looking for doesn't exist or may have been removed."],
    419: ['Session expired', 'Your session has expired. Please refresh the page and try again.'],
    500: ['Something went wrong', 'An unexpected error occurred. Please try again in a moment.'],
    503: ['Down for maintenance', "We're making some improvements. Please check back shortly."],
};

export default function Error({ status }) {
    const [title, description] = MESSAGES[status] ?? MESSAGES[500];
    return (
        <div className="flex min-h-screen items-center justify-center bg-subtle px-6">
            <Head title={title} />
            <div className="max-w-md text-center">
                <p className="text-sm font-semibold text-primary-600">{status}</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-ink">{title}</h1>
                <p className="mt-2 text-sm text-muted">{description}</p>
                <div className="mt-6 flex justify-center gap-2">
                    <button type="button" onClick={() => window.history.back()} className="inline-flex h-9 items-center rounded-lg border border-line bg-white px-3.5 text-sm font-medium text-ink shadow-xs hover:bg-subtle">
                        Go back
                    </button>
                    <Link href="/dashboard" className="inline-flex h-9 items-center rounded-lg bg-primary-500 px-3.5 text-sm font-medium text-white shadow-xs hover:bg-primary-600">
                        Dashboard
                    </Link>
                </div>
            </div>
        </div>
    );
}
