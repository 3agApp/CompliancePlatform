import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/categories';
import type { ProductCategory } from '@/types';

type Props = PropsWithChildren<{
    organizationSlug: string;
    category?: ProductCategory;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}>;

/**
 * One dialog for adding and for renaming. A category is a name and nothing
 * else, so the two forms would otherwise be the same markup twice.
 */
export default function SaveCategoryModal({
    organizationSlug,
    category,
    open,
    onOpenChange,
    children,
}: Props) {
    const isEditing = category !== undefined;

    const form = isEditing
        ? update.form([organizationSlug, category.uuid])
        : store.form(organizationSlug);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}

            <DialogContent>
                <Form
                    key={`${category?.uuid ?? 'new'}-${String(open)}`}
                    {...form}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {isEditing
                                        ? 'Rename category'
                                        : 'Add a category'}
                                </DialogTitle>
                                <DialogDescription>
                                    A category is the legal family a product is
                                    regulated under, such as a toy or a magnetic
                                    toy.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="category-name">
                                    Category name
                                </Label>
                                <Input
                                    id="category-name"
                                    name="name"
                                    data-test="category-name"
                                    defaultValue={category?.name ?? ''}
                                    placeholder="Magnetic toy"
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="save-category-submit"
                                    disabled={processing}
                                >
                                    {isEditing
                                        ? 'Save changes'
                                        : 'Add category'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
