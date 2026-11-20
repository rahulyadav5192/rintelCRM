import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function LoadingState({ label = 'Loading…', className }) {
    return (
        <div className={cn('flex items-center justify-center gap-2 py-10 text-sm text-muted', className)}>
            <Loader2 className="h-4 w-4 animate-spin text-primary-500" />
            {label}
        </div>
    );
}

export function Skeleton({ className }) {
    return <div className={cn('animate-pulse rounded-md bg-gray-100', className)} />;
}
