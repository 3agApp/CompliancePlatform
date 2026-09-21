import { FileText, ListChecks } from 'lucide-react';
import type { ProductRequirementKey, ProductRequirementOption } from '@/types';

type Props = {
    requirements: ProductRequirementKey[];
    availableRequirements: ProductRequirementOption[];
    /** Spell every item out, rather than counting them. */
    detailed?: boolean;
};

/**
 * What a template asks for, in one line or in full.
 *
 * Read in two places that both want the same sentence: the categories page,
 * where a template is one row among several, and the product form, where
 * choosing a template should say what you have just signed up for before
 * you reach the fields.
 */
export default function TemplateRequirementSummary({
    requirements,
    availableRequirements,
    detailed = false,
}: Props) {
    const asked = availableRequirements.filter((requirement) =>
        requirements.includes(requirement.value),
    );

    if (asked.length === 0) {
        return (
            <span className="text-muted-foreground text-xs">
                Asks for nothing yet
            </span>
        );
    }

    const documents = asked.filter(
        (requirement) => requirement.group === 'document',
    );
    const fields = asked.filter((requirement) => requirement.group === 'data');

    if (!detailed) {
        return (
            <span className="text-muted-foreground text-xs">
                {[
                    documents.length > 0
                        ? `${documents.length} ${documents.length === 1 ? 'document' : 'documents'}`
                        : null,
                    fields.length > 0
                        ? `${fields.length} ${fields.length === 1 ? 'field' : 'fields'}`
                        : null,
                ]
                    .filter(Boolean)
                    .join(' · ')}
            </span>
        );
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <Group
                icon={<FileText className="size-3.5" />}
                title="Documents"
                items={documents}
            />
            <Group
                icon={<ListChecks className="size-3.5" />}
                title="Product data"
                items={fields}
            />
        </div>
    );
}

function Group({
    icon,
    title,
    items,
}: {
    icon: React.ReactNode;
    title: string;
    items: ProductRequirementOption[];
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="grid content-start gap-1.5">
            <p className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                {icon}
                {title}
            </p>
            <ul className="text-muted-foreground grid gap-1 text-xs">
                {items.map((item) => (
                    <li key={item.value}>{item.label}</li>
                ))}
            </ul>
        </div>
    );
}
