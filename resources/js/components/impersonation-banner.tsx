import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { t, tn } from '@/lib/i18n';
import { destroy as stopImpersonating } from '@/routes/impersonation';

export function ImpersonationBanner() {
    const { auth, impersonating } = usePage().props;

    if (!impersonating) {
        return null;
    }

    return (
        <div
            data-test="impersonation-banner"
            className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-100 px-4 py-2 text-sm text-amber-900 dark:bg-amber-500/15 dark:text-amber-200"
        >
            <span>
                {tn('You are signed in as :name (:email).', {
                    name: <span className="font-medium">{auth.user.name}</span>,
                    email: auth.user.email,
                })}
            </span>
            <Button
                size="sm"
                variant="outline"
                className="h-7"
                data-test="impersonation-stop"
                onClick={() => router.visit(stopImpersonating())}
            >
                {t('Return to admin')}
            </Button>
        </div>
    );
}
