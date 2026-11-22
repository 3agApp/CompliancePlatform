import { Form, Link, router } from '@inertiajs/react';
import { Ban, BarChart3, Printer, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PrintLabelsButton from '@/components/print-labels-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDay } from '@/lib/format';
import { t, tc } from '@/lib/i18n';
import { destroy, show, store } from '@/routes/products/label-batches';
import type { LabelBatchSummary } from '@/types';

type Props = {
    organizationSlug: string;
    productId: number;
    batches: LabelBatchSummary[];
};

/**
 * Labels with a serial for every box in a shipment.
 *
 * Each run says who or what it was printed for, so a serial that turns up
 * checked far more often than one buyer would check it can be traced back
 * to where its labels went.
 */
export default function SerialLabelsPanel({
    organizationSlug,
    productId,
    batches,
}: Props) {
    const [withdrawing, setWithdrawing] = useState<LabelBatchSummary | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    const withdraw = () => {
        if (!withdrawing) {
            return;
        }

        router.visit(destroy([organizationSlug, productId, withdrawing.id]), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setWithdrawing(null),
        });
    };

    return (
        <div
            className="workspace-panel space-y-4 p-5"
            data-test="serial-labels-panel"
        >
            <h2 className="text-sm font-medium">{t('Serialised labels')}</h2>

            <Form
                {...store.form([organizationSlug, productId])}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="space-y-3"
            >
                {({ errors, processing: issuing }) => (
                    <>
                        <div className="space-y-1.5">
                            <Label htmlFor="label-issued-for">
                                {t('Issued for')}
                            </Label>
                            <Input
                                id="label-issued-for"
                                name="issued_for"
                                placeholder={t('Customer, shipment or order')}
                                aria-invalid={!!errors.issued_for}
                                data-test="label-issued-for"
                            />
                            <InputError message={errors.issued_for} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="label-quantity">{t('Boxes')}</Label>
                            <Input
                                id="label-quantity"
                                name="quantity"
                                type="number"
                                min={1}
                                max={400}
                                placeholder="200"
                                aria-invalid={!!errors.quantity}
                                data-test="label-quantity"
                            />
                            <InputError message={errors.quantity} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="label-note">{t('Note')}</Label>
                            <Textarea
                                id="label-note"
                                name="note"
                                rows={2}
                                placeholder={t(
                                    'Anything worth remembering about this run',
                                )}
                                data-test="label-note"
                            />
                            <InputError message={errors.note} />
                        </div>

                        <Button
                            type="submit"
                            size="sm"
                            className="w-full"
                            disabled={issuing}
                            data-test="label-issue"
                        >
                            <Printer className="h-4 w-4" /> {t('Issue labels')}
                        </Button>
                    </>
                )}
            </Form>

            {batches.length > 0 ? (
                <ul className="divide-y text-sm" data-test="label-batches">
                    {batches.map((batch) => (
                        <li
                            key={batch.id}
                            className="space-y-1.5 py-3"
                            data-test="label-batch"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <Link
                                    href={show([
                                        organizationSlug,
                                        productId,
                                        batch.id,
                                    ])}
                                    className="min-w-0 hover:underline"
                                    data-test="label-batch-overview"
                                >
                                    <p
                                        className={
                                            batch.revokedAt
                                                ? 'text-muted-foreground truncate line-through'
                                                : 'truncate font-medium'
                                        }
                                    >
                                        {batch.issuedFor}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {batch.revokedAt
                                            ? t('Withdrawn :date', {
                                                  date: formatDay(
                                                      batch.revokedAt,
                                                  ),
                                              })
                                            : [
                                                  tc(
                                                      '1 box|:count boxes',
                                                      batch.quantity,
                                                  ),
                                                  formatDay(batch.createdAt),
                                                  batch.createdBy,
                                              ]
                                                  .filter(Boolean)
                                                  .join(' · ')}
                                    </p>
                                </Link>

                                <div className="flex shrink-0 gap-1">
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link
                                            href={show([
                                                organizationSlug,
                                                productId,
                                                batch.id,
                                            ])}
                                            aria-label={t(
                                                'Check overview for :name',
                                                {
                                                    name: batch.issuedFor,
                                                },
                                            )}
                                        >
                                            <BarChart3 className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    {batch.revokedAt ? null : (
                                        <>
                                            <PrintLabelsButton
                                                organizationSlug={
                                                    organizationSlug
                                                }
                                                productId={productId}
                                                batchId={batch.id}
                                                variant="ghost"
                                                size="sm"
                                                aria-label={t(
                                                    'Print labels for :name',
                                                    {
                                                        name: batch.issuedFor,
                                                    },
                                                )}
                                            />
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setWithdrawing(batch)
                                                }
                                                aria-label={t(
                                                    'Withdraw labels for :name',
                                                    { name: batch.issuedFor },
                                                )}
                                                data-test="label-batch-withdraw"
                                            >
                                                <Ban className="h-4 w-4" />
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-1.5 text-xs">
                                <span className="bg-muted rounded-full px-2 py-0.5">
                                    {t(':checked / :quantity checked', {
                                        checked: batch.checked,
                                        quantity: batch.quantity,
                                    })}
                                </span>
                                <span className="bg-muted rounded-full px-2 py-0.5">
                                    {tc('1 check|:count checks', batch.checks)}
                                </span>
                                {batch.unusual > 0 ? (
                                    <span
                                        className="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 font-medium text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                        data-test="label-batch-unusual"
                                    >
                                        <TriangleAlert className="h-3 w-3" />
                                        {t(':count unusual', {
                                            count: batch.unusual,
                                        })}
                                    </span>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            ) : null}

            <Dialog
                open={withdrawing !== null}
                onOpenChange={(open) => !open && setWithdrawing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Withdraw labels')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Every one of the :quantity serials issued for :name will read as withdrawn to anyone who checks it, including boxes already sold. Use this for sheets that went missing or were printed by mistake. It cannot be undone.',
                                {
                                    quantity: withdrawing?.quantity,
                                    name: withdrawing?.issuedFor,
                                },
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">{t('Cancel')}</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={withdraw}
                            data-test="label-batch-withdraw-confirm"
                        >
                            {t('Withdraw labels')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
