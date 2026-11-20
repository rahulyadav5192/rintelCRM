import { cn } from '@/lib/utils';

export function Field({ label, htmlFor, error, hint, required, className, children }) {
    return (
        <div className={className}>
            {label && (
                <label htmlFor={htmlFor} className="label">
                    {label}
                    {required && <span className="ml-0.5 text-red-500">*</span>}
                </label>
            )}
            {children}
            {error ? <p className="mt-1.5 text-xs text-red-600">{error}</p> : hint ? <p className="mt-1.5 text-xs text-muted">{hint}</p> : null}
        </div>
    );
}

export default function FormInput({ label, name, error, hint, required, className, inputClassName, icon: Icon, ...props }) {
    return (
        <Field label={label} htmlFor={name} error={error} hint={hint} required={required} className={className}>
            <div className="relative">
                {Icon && <Icon className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />}
                <input id={name} name={name} className={cn('input', Icon && 'pl-9', error && 'input-error', inputClassName)} aria-invalid={!!error} {...props} />
            </div>
        </Field>
    );
}
