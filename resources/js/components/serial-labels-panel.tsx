import { Form, router } from '@inertiajs/react';
import { Ban, FileText, Printer } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { destroy, pdf, store } from '@/routes/products/label-batches';
import type { LabelBatchSummary } from '@/types';

type Props = {
    organizationSlug: string;
    productId: number;
    batches: LabelBatchSummary[];
};

function onDay(timestamp: string | null): string {
    return timestamp
        ? new Date(timestamp).toLocaleDateString(undefined, {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '';
}

/**
 * Labels with a serial for every packet in a shipment.
 *
 * One label per box: the serial and a code that leads to it. A copied
 * label gives itself away the moment a second buyer finds its serial
 * was checked before.
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
            <div className="space-y-1">
                <h2 className="text-sm font-medium">Serialised labels</h2>
                <p className="text-muted-foreground text-xs">
                    One label per box, each with its own serial, so buyers can
                    check theirs is genuine.
                </p>
            </div>

            <Form
                {...store.form([organizationSlug, productId])}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="space-y-2"
            >
                {({ errors, processing: issuing }) => (
                    <>
                        <Label htmlFor="label-quantity">Packets</Label>
                        <div className="flex gap-2">
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
                            <Button
                                type="submit"
                                size="sm"
                                className="h-9"
                                disabled={issuing}
                                data-test="label-issue"
                            >
                                <Printer className="h-4 w-4" /> Issue
                            </Button>
                        </div>
                        <InputError message={errors.quantity} />
                    </>
                )}
            </Form>

            {batches.length > 0 ? (
                <ul className="divide-y text-sm" data-test="label-batches">
                    {batches.map((batch) => (
                        <li
                            key={batch.id}
                            className="flex items-center justify-between gap-2 py-2"
                            data-test="label-batch"
                        >
                            <div className="min-w-0">
                                <p
                                    className={
                                        batch.revokedAt
                                            ? 'text-muted-foreground line-through'
                                            : 'font-medium'
                                    }
                                >
                                    {batch.quantity} packets
                                </p>
                                <p className="text-muted-foreground truncate text-xs">
                                    {batch.revokedAt
                                        ? `Withdrawn ${onDay(batch.revokedAt)}`
                                        : `${batch.checked} checked · ${onDay(batch.createdAt)}${batch.createdBy ? ` · ${batch.createdBy}` : ''}`}
                                </p>
                            </div>

                            {batch.revokedAt ? null : (
                                <div className="flex shrink-0 gap-1">
                                    <Button variant="ghost" size="sm" asChild>
                                        <a
                                            href={
                                                pdf([
                                                    organizationSlug,
                                                    productId,
                                                    batch.id,
                                                ]).url
                                            }
                                            aria-label={`Download labels for ${batch.quantity} packets`}
                                            data-test="label-batch-pdf"
                                        >
                                            <FileText className="h-4 w-4" /> PDF
                                        </a>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setWithdrawing(batch)}
                                        aria-label={`Withdraw labels for ${batch.quantity} packets`}
                                        data-test="label-batch-withdraw"
                                    >
                                        <Ban className="h-4 w-4" />
                                    </Button>
                                </div>
                            )}
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
                        <DialogTitle>Withdraw labels</DialogTitle>
                        <DialogDescription>
                            Every one of these {withdrawing?.quantity} serials
                            will read as withdrawn to anyone who checks it,
                            including packets already sold. Use this for a roll
                            that went missing or was printed by mistake. It
                            cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={withdraw}
                            data-test="label-batch-withdraw-confirm"
                        >
                            Withdraw labels
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
