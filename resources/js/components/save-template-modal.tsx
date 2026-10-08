import { Form } from '@inertiajs/react';
import { Info } from 'lucide-react';
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
import { t, tc } from '@/lib/i18n';
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
            <DialogContent className="sm:max-w-3xl">
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

                            <RequirementPicker
                                availableRequirements={availableRequirements}
                                template={template}
                            />

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

/**
 * The requirements that are answers about the product's safety rather
 * than the numbers it is known by, matching the product page's sections.
 */
const COMPLIANCE_REQUIREMENTS: ProductRequirementKey[] = [
    'requires_age_grading',
    'requires_safety_notice',
    'requires_warning_text',
    'requires_material_information',
    'requires_usage_restrictions',
    'requires_safety_instructions',
    'requires_additional_notes',
];

/**
 * Every requirement a template can ask for, in the three groups a product
 * page answers them in, with what is ticked counted as it changes.
 *
 * The ticks live here rather than in each checkbox, so a group can be
 * ticked or cleared in one go and the totals stay true while the person
 * works through the list.
 */
function RequirementPicker({
    availableRequirements,
    template,
}: {
    availableRequirements: ProductRequirementOption[];
    template?: ProductTemplate;
}) {
    const [checked, setChecked] = useState<Set<ProductRequirementKey>>(
        () => new Set(template?.requirements ?? []),
    );

    const setMany = (values: ProductRequirementKey[], on: boolean) =>
        setChecked((current) => {
            const next = new Set(current);

            for (const value of values) {
                if (on) {
                    next.add(value);
                } else {
                    next.delete(value);
                }
            }

            return next;
        });

    const groups = [
        {
            key: 'documents',
            title: t('Documents to upload'),
            options: availableRequirements.filter(
                (requirement) => requirement.group === 'document',
            ),
        },
        {
            key: 'identification',
            title: t('Identification'),
            options: availableRequirements.filter(
                (requirement) =>
                    requirement.group === 'data' &&
                    !COMPLIANCE_REQUIREMENTS.includes(requirement.value),
            ),
        },
        {
            key: 'compliance',
            title: t('Compliance claims'),
            options: availableRequirements.filter((requirement) =>
                COMPLIANCE_REQUIREMENTS.includes(requirement.value),
            ),
        },
    ];

    return (
        <div className="grid gap-4">
            {template !== undefined && template.products_count > 0 ? (
                <p
                    className="flex items-start gap-2 rounded-lg bg-sky-500/10 px-3 py-2.5 text-sm text-sky-900 dark:text-sky-200"
                    data-test="template-impact"
                >
                    <Info className="mt-0.5 size-4 shrink-0" />
                    <span>
                        <span className="font-medium">
                            {tc(
                                '1 product uses this template.|:count products use this template.',
                                template.products_count,
                            )}
                        </span>{' '}
                        {t(
                            'Their checklists and scores change as soon as you save.',
                        )}
                    </span>
                </p>
            ) : null}

            <p
                className="text-muted-foreground text-sm"
                data-test="template-requirement-total"
            >
                {t(':checked of :total things asked for', {
                    checked: checked.size,
                    total: availableRequirements.length,
                })}
            </p>

            <div className="grid max-h-[50vh] auto-rows-max gap-3 overflow-y-auto pr-1">
                {groups.map((group) => {
                    const values = group.options.map((option) => option.value);
                    const ticked = values.filter((value) =>
                        checked.has(value),
                    ).length;

                    return (
                        <fieldset
                            key={group.key}
                            className="overflow-hidden rounded-xl border"
                            data-test={`template-group-${group.key}`}
                        >
                            <legend className="sr-only">{group.title}</legend>
                            <div className="bg-muted/40 flex items-center justify-between gap-3 border-b px-4 py-2.5">
                                <span className="flex items-center gap-2 text-sm font-medium">
                                    {group.title}
                                    <span className="bg-muted-foreground/15 text-muted-foreground rounded-full px-1.5 text-xs tabular-nums">
                                        {t(':checked of :total', {
                                            checked: ticked,
                                            total: values.length,
                                        })}
                                    </span>
                                </span>
                                <span className="text-muted-foreground flex gap-3 text-xs">
                                    <button
                                        type="button"
                                        className="hover:text-foreground underline underline-offset-2"
                                        onClick={() => setMany(values, true)}
                                    >
                                        {t('All')}
                                    </button>
                                    <button
                                        type="button"
                                        className="hover:text-foreground underline underline-offset-2"
                                        onClick={() => setMany(values, false)}
                                    >
                                        {t('None')}
                                    </button>
                                </span>
                            </div>

                            <div className="grid sm:grid-cols-2">
                                {group.options.map((option) => (
                                    <RequirementCheckbox
                                        key={option.value}
                                        option={option}
                                        checked={checked.has(option.value)}
                                        onCheckedChange={(on) =>
                                            setMany([option.value], on)
                                        }
                                    />
                                ))}
                            </div>
                        </fieldset>
                    );
                })}
            </div>
        </div>
    );
}

function RequirementCheckbox({
    option,
    checked,
    onCheckedChange,
}: {
    option: ProductRequirementOption;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    const id = `template-${option.value}`;

    /**
     * What the tick does to the score, said only where it is unusual: the
     * papers that weigh more than a field, and the one that weighs nothing.
     */
    const weightHint =
        option.weight === 0
            ? t('Not scored')
            : option.weight > 1
              ? t('Counts ×:weight', { weight: option.weight })
              : null;

    return (
        <div className="hover:bg-muted/40 flex items-center gap-2.5 border-t px-4 py-2.5 max-sm:first:border-t-0 sm:[&:nth-child(-n+2)]:border-t-0">
            <Checkbox
                id={id}
                data-test={`template-${toHandle(option.value)}`}
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(value === true)}
            />
            <input
                type="hidden"
                name={option.value}
                value={checked ? '1' : '0'}
            />
            <Label
                htmlFor={id}
                className="flex-1 cursor-pointer text-sm font-normal"
            >
                {option.label}
            </Label>
            {weightHint ? (
                <span
                    className={
                        option.weight === 0
                            ? 'text-muted-foreground text-xs'
                            : 'text-xs text-sky-700 dark:text-sky-400'
                    }
                >
                    {weightHint}
                </span>
            ) : null}
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
