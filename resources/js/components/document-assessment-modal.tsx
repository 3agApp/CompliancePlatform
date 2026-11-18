import { router, useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AssessmentRun } from '@/components/product-assessment-panel';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { t } from '@/lib/i18n';
import { show } from '@/routes/products/assessments';
import type {
    ProductAssessmentDetail,
    ProductAssessmentSummary,
    ProductDocument,
} from '@/types';

type Props = {
    organizationSlug: string;
    productId: number;
    document: ProductDocument | null;
    /** The paper's latest run, once the page has it. */
    summary: ProductAssessmentSummary | null;
    onOpenChange: (open: boolean) => void;
};

/**
 * How often to look again while the run is going, in milliseconds.
 */
const POLL_INTERVAL = 4000;

/**
 * The AI reading of one paper on its own.
 *
 * Fetched when opened, and fetched again while the run is going. Once it
 * finishes, the page's own copy of the runs is refreshed too, so the badge
 * on the paper's row catches up without a reload.
 */
export default function DocumentAssessmentModal({
    organizationSlug,
    productId,
    document,
    summary,
    onOpenChange,
}: Props) {
    const [run, setRun] = useState<ProductAssessmentDetail | null>(null);
    const request = useHttp<Record<string, never>, ProductAssessmentDetail>({});
    const runId = summary?.id ?? null;
    const open = document !== null;

    useEffect(() => {
        if (!open || runId === null) {
            setRun(null);

            return;
        }

        let timer: number | undefined;
        let cancelled = false;

        const load = () => {
            void request.get(show.url([organizationSlug, productId, runId]), {
                onSuccess: (response: ProductAssessmentDetail) => {
                    if (cancelled) {
                        return;
                    }

                    setRun(response);

                    if (response.is_pending) {
                        timer = window.setTimeout(load, POLL_INTERVAL);
                    } else {
                        router.reload({ only: ['assessment'] });
                    }
                },
            });
        };

        load();

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [open, runId, organizationSlug, productId]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl"
                data-test="document-assessment-modal"
            >
                <DialogHeader>
                    <DialogTitle className="truncate pr-6">
                        {document?.name}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'AI check of this document on its own. Advisory only: a person decides.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                {run !== null && run.id === runId ? (
                    <AssessmentRun
                        run={run}
                        organizationSlug={organizationSlug}
                        productId={productId}
                        actions={null}
                    />
                ) : (
                    <Skeleton className="h-24 w-full" />
                )}
            </DialogContent>
        </Dialog>
    );
}
