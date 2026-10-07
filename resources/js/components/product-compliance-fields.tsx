import { Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import {
    FieldMarker,
    neededControlClass,
} from '@/components/product-form-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { ProductComplianceDetails, ProductRequirementKey } from '@/types';
import { t } from '@/lib/i18n';

type FieldName = keyof ProductComplianceDetails;

type Props = {
    errors: Partial<Record<FieldName, string>>;
    /** What the product's template asks for, which is what marks the labels. */
    requirements?: ProductRequirementKey[];
    /** What the template asks for and the saved product does not have yet. */
    outstanding?: ProductRequirementKey[];
    product?: ProductComplianceDetails;
    disabled?: boolean;
    idPrefix?: string;
};

type TextField = {
    name: Exclude<FieldName, 'age_grading'>;
    /** The kebab-case form of the name, for ids and test hooks. */
    slug: string;
    label: string;
    requirement: ProductRequirementKey;
    placeholder: string;
    hint?: string;
};

/**
 * What a product claims about its own safety: what the package must warn, who
 * it is for, what it is made of, and how it may and may not be used.
 *
 * Every field is optional. A product is added long before anyone has these
 * answers, and the supplier who fills them in may only ever hold some of
 * them — a half-filled record is still worth keeping.
 *
 * The long answers are shown only where they mean something: answered
 * already, asked for by the template, or asked for by the person. The rest
 * wait behind a button each, so seven empty boxes do not stand between the
 * person and the one they came to fill.
 */
export default function ProductComplianceFields({
    errors,
    requirements = [],
    outstanding = [],
    product,
    disabled = false,
    idPrefix = 'product',
}: Props) {
    const needs = (requirement: ProductRequirementKey) =>
        requirements.includes(requirement);

    /** Asked for by the template on screen and still empty when saved. */
    const owes = (requirement: ProductRequirementKey) =>
        needs(requirement) && outstanding.includes(requirement);

    const fields: TextField[] = [
        {
            name: 'safety_notice',
            slug: 'safety-notice',
            label: t('Safety notice'),
            requirement: 'requires_safety_notice',
            placeholder: t(
                'Keep the packaging until the product has been checked.',
            ),
        },
        {
            name: 'warning_text',
            slug: 'warning-text',
            label: t('Warning text'),
            requirement: 'requires_warning_text',
            placeholder: t(
                'Not suitable for children under 3 years. Small parts.',
            ),
            hint: t('Word for word as it appears on the packaging.'),
        },
        {
            name: 'material_information',
            slug: 'material-information',
            label: t('Material information'),
            requirement: 'requires_material_information',
            placeholder: t(
                'ABS plastic, neodymium magnets, water based paint.',
            ),
        },
        {
            name: 'usage_restrictions',
            slug: 'usage-restrictions',
            label: t('Usage restrictions'),
            requirement: 'requires_usage_restrictions',
            placeholder: t('Indoor use only. Not for use in water.'),
        },
        {
            name: 'safety_instructions',
            slug: 'safety-instructions',
            label: t('Safety instructions'),
            requirement: 'requires_safety_instructions',
            placeholder: t(
                'Inspect for damage before each use and replace broken parts.',
            ),
        },
        {
            name: 'additional_notes',
            slug: 'additional-notes',
            label: t('Additional notes'),
            requirement: 'requires_additional_notes',
            placeholder: t(
                'Anything else the other side of the trade should know.',
            ),
        },
    ];

    /**
     * Fields kept on screen whatever the template says: asked for by the
     * person, or typed into. A field shown because the template wanted it
     * stays once something is in it, so changing the template before
     * saving never takes typed text off the form unseen.
     */
    const [revealed, setRevealed] = useState<FieldName[]>([]);

    const keep = (name: FieldName) =>
        setRevealed((current) =>
            current.includes(name) ? current : [...current, name],
        );

    /** The field just asked for, which takes the focus once it is drawn. */
    const [justRevealed, setJustRevealed] = useState<FieldName | null>(null);

    const fieldRefs = useRef<Partial<Record<FieldName, HTMLTextAreaElement>>>(
        {},
    );

    useEffect(() => {
        if (justRevealed !== null) {
            fieldRefs.current[justRevealed]?.focus();
        }
    }, [justRevealed]);

    const isShown = (field: TextField) =>
        needs(field.requirement) ||
        Boolean(product?.[field.name]) ||
        Boolean(errors[field.name]) ||
        revealed.includes(field.name);

    const hidden = fields.filter((field) => !isShown(field));

    const reveal = (name: FieldName) => {
        keep(name);
        setJustRevealed(name);
    };

    return (
        <div className="grid gap-4">
            <div className="grid gap-2 sm:max-w-3xs">
                <Label htmlFor={`${idPrefix}-age-grading`}>
                    {t('Age grading')}{' '}
                    <FieldMarker
                        required={needs('requires_age_grading')}
                        missing={owes('requires_age_grading')}
                    />
                </Label>
                <Input
                    id={`${idPrefix}-age-grading`}
                    name="age_grading"
                    data-test="product-age-grading"
                    className={neededControlClass(owes('requires_age_grading'))}
                    defaultValue={product?.age_grading ?? ''}
                    placeholder="3+"
                    autoComplete="off"
                    disabled={disabled}
                />
                <p className="text-muted-foreground text-xs">
                    {t('Who the product is for.')}
                </p>
                <InputError message={errors.age_grading} />
            </div>

            {fields.filter(isShown).map((field) => (
                <div key={field.name} className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-${field.slug}`}>
                        {field.label}{' '}
                        <FieldMarker
                            required={needs(field.requirement)}
                            missing={owes(field.requirement)}
                        />
                    </Label>
                    <Textarea
                        ref={(element) => {
                            if (element !== null) {
                                fieldRefs.current[field.name] = element;
                            }
                        }}
                        id={`${idPrefix}-${field.slug}`}
                        name={field.name}
                        onInput={() => keep(field.name)}
                        data-test={`product-${field.slug}`}
                        className={neededControlClass(owes(field.requirement))}
                        defaultValue={product?.[field.name] ?? ''}
                        placeholder={field.placeholder}
                        rows={3}
                        disabled={disabled}
                    />
                    {field.hint ? (
                        <p className="text-muted-foreground text-xs">
                            {field.hint}
                        </p>
                    ) : null}
                    <InputError message={errors[field.name]} />
                </div>
            ))}

            {hidden.length > 0 && !disabled ? (
                <div className="grid gap-2 border-t pt-4">
                    <p className="text-muted-foreground text-xs">
                        {t('Add more if the packaging carries it')}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {hidden.map((field) => (
                            <Button
                                key={field.name}
                                type="button"
                                variant="outline"
                                size="sm"
                                className="rounded-full border-dashed"
                                data-test={`product-add-${field.slug}`}
                                onClick={() => reveal(field.name)}
                            >
                                <Plus className="h-3.5 w-3.5" /> {field.label}
                            </Button>
                        ))}
                    </div>
                </div>
            ) : null}
        </div>
    );
}
