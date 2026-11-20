import { cn } from '@/lib/utils';
import { Field } from './FormInput';

export default function FormTextarea({ label, name, error, hint, required, className, rows = 4, ...props }) {
    return (
        <Field label={label} htmlFor={name} error={error} hint={hint} required={required} className={className}>
            <textarea id={name} name={name} rows={rows} className={cn('input resize-y', error && 'input-error')} aria-invalid={!!error} {...props} />
        </Field>
    );
}
