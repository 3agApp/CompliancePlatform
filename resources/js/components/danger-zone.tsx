import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { t } from '@/lib/i18n';

type Props = {
    title: string;
    /** What happens, in one sentence, shown beside the button. */
    warning?: string;
    children: ReactNode;
};

/**
 * The red box at the bottom of a settings page for the one thing that
 * cannot be undone. Kept as one component so the account, shop and
 * organization pages all warn the same way.
 */
export default function DangerZone({
    title,
    warning = t('Please proceed with caution, this cannot be undone.'),
    children,
}: Props) {
    return (
        <div className="space-y-6">
            <Heading variant="small" title={title} />
            <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                <p className="min-w-0 text-sm text-red-600 dark:text-red-100">
                    {warning}
                </p>
                {children}
            </div>
        </div>
    );
}
