import InputError from '@/components/input-error';
import { FieldMarker } from '@/components/product-form-fields';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { ProductComplianceDetails, ProductRequirementKey } from '@/types';

type FieldName = keyof ProductComplianceDetails;

type Props = {
    errors: Partial<Record<FieldName, string>>;
    /** What the product's template asks for, which is what marks the labels. */
    requirements?: ProductRequirementKey[];
    product?: ProductComplianceDetails;
    disabled?: boolean;
    idPrefix?: string;
};

/**
 * What a product claims about its own safety: what the package must warn, who
 * it is for, what it is made of, and how it may and may not be used.
 *
 * Every field is optional. A product is added long before anyone has these
 * answers, and the supplier who fills them in may only ever hold some of
 * them — a half-filled record is still worth keeping.
 */
export default function ProductComplianceFields({
    errors,
    requirements = [],
    product,
    disabled = false,
    idPrefix = 'product',
}: Props) {
    const needs = (requirement: ProductRequirementKey) =>
        requirements.includes(requirement);

    return (
        <div className="grid gap-4">
            <div className="grid gap-2 sm:max-w-3xs">
                <Label htmlFor={`${idPrefix}-age-grading`}>
                    Age grading{' '}
                    <FieldMarker required={needs('requires_age_grading')} />
                </Label>
                <Input
                    id={`${idPrefix}-age-grading`}
                    name="age_grading"
                    data-test="product-age-grading"
                    defaultValue={product?.age_grading ?? ''}
                    placeholder="3+"
                    autoComplete="off"
                    disabled={disabled}
                />
                <p className="text-muted-foreground text-xs">
                    Who the product is for.
                </p>
                <InputError message={errors.age_grading} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-safety-notice`}>
                    Safety notice{' '}
                    <FieldMarker required={needs('requires_safety_notice')} />
                </Label>
                <Textarea
                    id={`${idPrefix}-safety-notice`}
                    name="safety_notice"
                    data-test="product-safety-notice"
                    defaultValue={product?.safety_notice ?? ''}
                    placeholder="Keep the packaging until the product has been checked."
                    rows={3}
                    disabled={disabled}
                />
                <InputError message={errors.safety_notice} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-warning-text`}>
                    Warning text{' '}
                    <FieldMarker required={needs('requires_warning_text')} />
                </Label>
                <Textarea
                    id={`${idPrefix}-warning-text`}
                    name="warning_text"
                    data-test="product-warning-text"
                    defaultValue={product?.warning_text ?? ''}
                    placeholder="Not suitable for children under 3 years. Small parts."
                    rows={3}
                    disabled={disabled}
                />
                <p className="text-muted-foreground text-xs">
                    Word for word as it appears on the packaging.
                </p>
                <InputError message={errors.warning_text} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-material-information`}>
                    Material information{' '}
                    <FieldMarker
                        required={needs('requires_material_information')}
                    />
                </Label>
                <Textarea
                    id={`${idPrefix}-material-information`}
                    name="material_information"
                    data-test="product-material-information"
                    defaultValue={product?.material_information ?? ''}
                    placeholder="ABS plastic, neodymium magnets, water based paint."
                    rows={3}
                    disabled={disabled}
                />
                <InputError message={errors.material_information} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-usage-restrictions`}>
                    Usage restrictions{' '}
                    <FieldMarker
                        required={needs('requires_usage_restrictions')}
                    />
                </Label>
                <Textarea
                    id={`${idPrefix}-usage-restrictions`}
                    name="usage_restrictions"
                    data-test="product-usage-restrictions"
                    defaultValue={product?.usage_restrictions ?? ''}
                    placeholder="Indoor use only. Not for use in water."
                    rows={3}
                    disabled={disabled}
                />
                <InputError message={errors.usage_restrictions} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-safety-instructions`}>
                    Safety instructions{' '}
                    <FieldMarker
                        required={needs('requires_safety_instructions')}
                    />
                </Label>
                <Textarea
                    id={`${idPrefix}-safety-instructions`}
                    name="safety_instructions"
                    data-test="product-safety-instructions"
                    defaultValue={product?.safety_instructions ?? ''}
                    placeholder="Inspect for damage before each use and replace broken parts."
                    rows={3}
                    disabled={disabled}
                />
                <InputError message={errors.safety_instructions} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-additional-notes`}>
                    Additional notes{' '}
                    <FieldMarker
                        required={needs('requires_additional_notes')}
                    />
                </Label>
                <Textarea
                    id={`${idPrefix}-additional-notes`}
                    name="additional_notes"
                    data-test="product-additional-notes"
                    defaultValue={product?.additional_notes ?? ''}
                    placeholder="Anything else the other side of the trade should know."
                    rows={3}
                    disabled={disabled}
                />
                <InputError message={errors.additional_notes} />
            </div>
        </div>
    );
}
