import { Link } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

const variants = {
    primary: 'bg-primary-500 text-white hover:bg-primary-600 active:bg-primary-700 shadow-xs',
    secondary: 'bg-white text-ink border border-line hover:bg-subtle shadow-xs',
    ghost: 'text-muted hover:text-ink hover:bg-subtle',
    danger: 'bg-red-600 text-white hover:bg-red-700 shadow-xs',
    'danger-ghost': 'text-red-600 hover:bg-red-50',
};

const sizes = {
    sm: 'h-8 px-3 text-xs gap-1.5',
    md: 'h-9 px-3.5 text-sm gap-2',
    lg: 'h-10 px-4 text-sm gap-2',
    icon: 'h-8 w-8 justify-center',
};

export default function Button({ as, href, variant = 'primary', size = 'md', loading = false, icon: Icon, className, children, disabled, ...props }) {
    const classes = cn(
        'inline-flex items-center justify-center rounded-lg font-medium whitespace-nowrap transition-colors disabled:pointer-events-none disabled:opacity-50',
        variants[variant],
        sizes[size],
        className,
    );
    const content = (
        <>
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : Icon ? <Icon className="h-4 w-4 shrink-0" /> : null}
            {children}
        </>
    );
    if (href && as === 'a') {
        return (
            <a href={href} className={classes} {...props}>
                {content}
            </a>
        );
    }
    if (href) {
        return (
            <Link href={href} className={classes} {...props}>
                {content}
            </Link>
        );
    }
    return (
        <button type="button" className={classes} disabled={disabled || loading} {...props}>
            {content}
        </button>
    );
}
