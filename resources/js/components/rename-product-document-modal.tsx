import { Form } from '@inertiajs/react';
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
import { t } from '@/lib/i18n';
import { update } from '@/routes/products/documents';
import type { ProductDocument } from '@/types';

type Props = {
    organizationSlug: string;
    productId: number;
    document: ProductDocument | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Give a document the title it is shown under.
 *
 * The uploaded filename stays as it was for the team. The public page shows
 * the title instead, or the kind of document when it is left empty.
 */
export default function RenameProductDocumentModal({
    organizationSlug,
    productId,
    document,
    open,
    onOpenChange,
}: Props) {
    if (document === null) {
        return null;
    }

    const form = update.form([organizationSlug, productId, document.id]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={`${document.id}-${String(open)}`}
                    {...form}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>{t('Document title')}</DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'Shown on the public page instead of the file name. Leave it empty to show the kind of document.',
                                    )}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="document-title">
                                    {t('Title')}
                                </Label>
                                <Input
                                    id="document-title"
                                    name="title"
                                    data-test="document-title"
                                    defaultValue={document.title ?? ''}
                                    placeholder={document.type_label}
                                    maxLength={255}
                                    autoComplete="off"
                                />
                                <p className="text-muted-foreground truncate text-xs">
                                    {t('File: :name', { name: document.name })}
                                </p>
                                <InputError message={errors.title} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="save-document-title"
                                    disabled={processing}
                                >
                                    {t('Save changes')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
