import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Field } from './FormInput';

/** options: [{ value, label, disabled? }] */
export default function FormSelect({ label, name, error, hint, required, options = [], placeholder, className, selectClassName, ...props }) {
    return (
        <Field label={label} htmlFor={name} error={error} hint={hint} required={required} className={className}>
            <div className="relative">
                <select id={name} name={name} className={cn('input appearance-none pr-9', error && 'input-error', selectClassName)} aria-invalid={!!error} {...props}>
                    {placeholder !== undefined && <option value="">{placeholder}</option>}
                    {options.map((o) => (
                        <option key={o.value} value={o.value} disabled={o.disabled}>
                            {o.label}
                        </option>
                    ))}
                </select>
                <ChevronDown className="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
            </div>
        </Field>
    );
}
