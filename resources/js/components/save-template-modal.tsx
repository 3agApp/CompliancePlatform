import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { store, update } from '@/routes/categories/templates';
import type {
    ProductCategory,
    ProductRequirementKey,
    ProductRequirementOption,
    ProductTemplate,
} from '@/types';

type Props = {
    organizationSlug: string;
    category: ProductCategory | null;
    template?: ProductTemplate;
    availableRequirements: ProductRequirementOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * One dialog for adding and for editing. A template is a name and a set of
 * ticks, so the two forms would otherwise be the same markup twice.
 *
 * The ticks are mirrored into hidden inputs rather than left to the Radix
 * checkbox, which submits nothing at all when it is clear. The server needs
 * to hear "no" as loudly as "yes": that is what lets an edit turn a
 * requirement back off.
 */
export default function SaveTemplateModal({
    organizationSlug,
    category,
    template,
    availableRequirements,
    open,
    onOpenChange,
}: Props) {
    const isEditing = template !== undefined;

    if (category === null) {
        return null;
    }

    const form = isEditing
        ? update.form([organizationSlug, category.id, template.id])
        : store.form([organizationSlug, category.id]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <Form
                    key={`${template?.id ?? 'new'}-${String(open)}`}
                    {...form}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {isEditing
                                        ? t('Edit template')
                                        : t('Add a template to :category', {
                                              category: category.name,
                                          })}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'A template says which documents and which details a product of this kind is expected to carry. Nothing here blocks a product from being saved — it is a checklist, not a gate.',
                                    )}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="template-name">
                                    {t('Template name')}
                                </Label>
                                <Input
                                    id="template-name"
                                    name="name"
                                    data-test="template-name"
                                    defaultValue={template?.name ?? ''}
                                    placeholder={t('EU toy safety')}
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid max-h-[50vh] gap-6 overflow-y-auto pr-1">
                                <RequirementGroup
                                    title={t('Documents')}
                                    description={t(
                                        'Papers that have to be filed against the product.',
                                    )}
                                    options={availableRequirements.filter(
                                        (requirement) =>
                                            requirement.group === 'document',
                                    )}
                                    template={template}
                                />

                                <RequirementGroup
                                    title={t('Product data')}
                                    description={t(
                                        'Fields on the product that have to be filled in.',
                                    )}
                                    options={availableRequirements.filter(
                                        (requirement) =>
                                            requirement.group === 'data',
                                    )}
                                    template={template}
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => onOpenChange(false)}
                                >
                                    {t('Cancel')}
                                </Button>

                                <Button
                                    type="submit"
                                    data-test="save-template-submit"
                                    disabled={processing}
                                >
                                    {isEditing
                                        ? t('Save changes')
                                        : t('Add template')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RequirementGroup({
    title,
    description,
    options,
    template,
}: {
    title: string;
    description: string;
    options: ProductRequirementOption[];
    template?: ProductTemplate;
}) {
    return (
        <fieldset className="grid gap-3">
            <legend className="grid gap-0.5">
                <span className="text-sm font-medium">{title}</span>
                <span className="text-muted-foreground text-xs">
                    {description}
                </span>
            </legend>

            <div className="grid gap-2 sm:grid-cols-2">
                {options.map((option) => (
                    <RequirementCheckbox
                        key={option.value}
                        option={option}
                        defaultChecked={
                            template?.requirements.includes(option.value) ??
                            false
                        }
                    />
                ))}
            </div>
        </fieldset>
    );
}

function RequirementCheckbox({
    option,
    defaultChecked,
}: {
    option: ProductRequirementOption;
    defaultChecked: boolean;
}) {
    const [checked, setChecked] = useState(defaultChecked);
    const id = `template-${option.value}`;

    return (
        <div className="flex items-center gap-2">
            <Checkbox
                id={id}
                data-test={`template-${toHandle(option.value)}`}
                checked={checked}
                onCheckedChange={(value) => setChecked(value === true)}
            />
            <input
                type="hidden"
                name={option.value}
                value={checked ? '1' : '0'}
            />
            <Label htmlFor={id} className="text-sm font-normal">
                {option.label}
            </Label>
        </div>
    );
}

/**
 * Turn a column name into a test handle: requires_test_report reads as
 * test-report, which is what a browser test would think to look for.
 */
function toHandle(requirement: ProductRequirementKey): string {
    return requirement.replace(/^requires_/, '').replace(/_/g, '-');
}
