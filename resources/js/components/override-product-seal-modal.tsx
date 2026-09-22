import { Form } from '@inertiajs/react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { update as updateSeal } from '@/routes/products/seal';
import type {
    ProductSealOption,
    ProductSealOverride,
    ProductSealStatus,
} from '@/types';

type Props = {
    organizationSlug: string;
    productId: number;
    availableSeals: ProductSealOption[];
    override: ProductSealOverride | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Radix refuses an empty string as a value, so "follow the review" travels
 * as a sentinel here and leaves as an absent field.
 */
const FOLLOWS_REVIEW = 'review';

/**
 * Set the public seal by hand, or hand it back to the review.
 *
 * The seal is on a page anybody can read, so setting one by hand is a claim
 * made in the distributor's own name. The reason is required and kept with
 * the product, and the public page says the seal was set rather than
 * earned -- which is what keeps a hand-set green from reading as a check
 * that passed.
 */
export default function OverrideProductSealModal({
    organizationSlug,
    productId,
    availableSeals,
    override,
    open,
    onOpenChange,
}: Props) {
    const [choice, setChoice] = useState<
        ProductSealStatus | typeof FOLLOWS_REVIEW
    >(override?.seal ?? FOLLOWS_REVIEW);

    const clearing = choice === FOLLOWS_REVIEW;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...updateSeal.form([organizationSlug, productId])}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Public seal</DialogTitle>
                                <DialogDescription>
                                    The seal normally follows this product's
                                    review. Setting one by hand overrides that
                                    on the public page, and says so.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="seal-choice">Show</Label>
                                <Select
                                    value={choice}
                                    onValueChange={(value) =>
                                        setChoice(
                                            value as
                                                | ProductSealStatus
                                                | typeof FOLLOWS_REVIEW,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="seal-choice"
                                        data-test="seal-choice"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={FOLLOWS_REVIEW}>
                                            Whatever the review says
                                        </SelectItem>
                                        {availableSeals.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                                data-test={`seal-option-${option.value}`}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {/*
                                 * Submitted as a plain field so the form
                                 * carries the select's value; absent means
                                 * the override is cleared.
                                 */}
                                {clearing ? null : (
                                    <input
                                        type="hidden"
                                        name="seal"
                                        value={choice}
                                    />
                                )}
                            </div>

                            <div
                                className={cn(
                                    'grid gap-2',
                                    clearing && 'hidden',
                                )}
                            >
                                <Label htmlFor="seal-reason">
                                    Why it is being set by hand
                                </Label>
                                <Textarea
                                    id="seal-reason"
                                    name="reason"
                                    rows={3}
                                    defaultValue={override?.reason ?? ''}
                                    data-test="seal-reason"
                                    placeholder="Certified under the previous article number; paperwork is with the test house."
                                />
                                <InputError message={errors.reason} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="seal-confirm"
                                    disabled={processing}
                                >
                                    {clearing
                                        ? 'Follow the review'
                                        : 'Set the seal'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
