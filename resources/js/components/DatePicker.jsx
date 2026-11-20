import FormInput from './FormInput';
import { CalendarDays } from 'lucide-react';

/** Native date input styled to match the design system (value format: YYYY-MM-DD). */
export default function DatePicker(props) {
    return <FormInput type="date" icon={CalendarDays} {...props} />;
}
