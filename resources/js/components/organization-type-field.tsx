import InputError from '@/components/input-error';
import type { OrganizationType, OrganizationTypeOption } from '@/types';

type Props = {
    options: OrganizationTypeOption[];
    defaultValue?: OrganizationType;
    error?: string;
    idPrefix?: string;
};

/**
 * Picks which side of the supply chain an organization works on.
 *
 * The choice is permanent, so the copy describes what each side does rather
 * than naming the role alone.
 */
export default function OrganizationTypeField({
    options,
    defaultValue = 'distributor',
    error,
    idPrefix = 'organization-type',
}: Props) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-2 text-sm font-medium">
                What does your company do?
            </legend>

            <div className="grid gap-3 sm:grid-cols-2">
                {options.map((option) => (
                    <label
                        key={option.value}
                        htmlFor={`${idPrefix}-${option.value}`}
                        data-test={`organization-type-${option.value}`}
                        className="border-input has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:focus-visible]:ring-ring cursor-pointer rounded-xl border p-4 transition has-[:focus-visible]:ring-2"
                    >
                        <input
                            type="radio"
                            id={`${idPrefix}-${option.value}`}
                            name="type"
                            value={option.value}
                            defaultChecked={option.value === defaultValue}
                            className="sr-only"
                        />
                        <span className="block text-sm font-medium">
                            {option.label}
                        </span>
                        <span className="text-muted-foreground mt-1 block text-xs leading-relaxed">
                            {option.description}
                        </span>
                    </label>
                ))}
            </div>

            <p className="text-muted-foreground text-xs">
                This cannot be changed later. A company that both imports and
                supplies can create one organization of each type.
            </p>

            <InputError message={error} />
        </fieldset>
    );
}
