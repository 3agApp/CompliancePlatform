import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    OrganizationType,
    ProductCategoryOption,
    ProductTemplateOption,
    SupplierConnectionOption,
} from '@/types';

type FieldName =
    | 'product_category_id'
    | 'product_template_id'
    | 'supplier_connection_id';

type Props = {
    errors: Partial<Record<FieldName, string>>;
    availableCategories: ProductCategoryOption[];
    availableTemplates: ProductTemplateOption[];
    availableConnections: SupplierConnectionOption[];
    viewerType: OrganizationType;
    categoryId: number | null;
    onCategoryChange: (categoryId: number) => void;
    templateId: number | null;
    onTemplateChange: (templateId: number) => void;
    connectionId: number | null;
    onConnectionChange: (connectionId: number) => void;
    disabled?: boolean;
    idPrefix?: string;
};

/**
 * Who supplies the product, which legal family it belongs to, and which of
 * that family's templates it is held to.
 *
 * These three are pulled out of the rest of the form because the template
 * has a say in everything below it: which fields are marked, what the
 * checklist lists. They are also the only fields with a dependency between
 * them -- a template only exists under a category -- which is why the state
 * lives with the page rather than here.
 */
export default function ProductClassificationFields({
    errors,
    availableCategories,
    availableTemplates,
    availableConnections,
    viewerType,
    categoryId,
    onCategoryChange,
    templateId,
    onTemplateChange,
    connectionId,
    onConnectionChange,
    disabled = false,
    idPrefix = 'product',
}: Props) {
    /**
     * Only the distributor that owns a product chooses its supplier. The
     * field is not rendered for a supplier, and the server does not accept
     * it from them either.
     */
    const canAssignSupplier = viewerType === 'distributor';

    const templates = availableTemplates.filter(
        (template) => template.product_category_id === categoryId,
    );

    const hasCategory = categoryId !== null;
    const categoryLabel =
        availableCategories.find((category) => category.id === categoryId)
            ?.label ?? 'this category';

    return (
        <div className="grid gap-4">
            {canAssignSupplier ? (
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-supplier`}>Supplier</Label>
                    <Select
                        value={
                            connectionId === null
                                ? undefined
                                : String(connectionId)
                        }
                        onValueChange={(value) =>
                            onConnectionChange(Number(value))
                        }
                        disabled={disabled || availableConnections.length === 0}
                    >
                        <SelectTrigger
                            id={`${idPrefix}-supplier`}
                            data-test="product-supplier"
                            className="w-full"
                        >
                            <SelectValue placeholder="Select a supplier" />
                        </SelectTrigger>
                        <SelectContent>
                            {availableConnections.map((connection) => (
                                <SelectItem
                                    key={connection.id}
                                    value={String(connection.id)}
                                >
                                    {connection.label}
                                    {connection.isPending
                                        ? ' (invitation pending)'
                                        : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="supplier_connection_id"
                        value={connectionId ?? ''}
                    />
                    {availableConnections.length === 0 ? (
                        <p className="text-muted-foreground text-xs">
                            Invite a supplier first — every product needs one.
                        </p>
                    ) : null}
                    <InputError message={errors.supplier_connection_id} />
                </div>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-category`}>Category</Label>
                    <Select
                        value={
                            categoryId === null ? undefined : String(categoryId)
                        }
                        onValueChange={(value) =>
                            onCategoryChange(Number(value))
                        }
                        disabled={disabled || availableCategories.length === 0}
                    >
                        <SelectTrigger
                            id={`${idPrefix}-category`}
                            data-test="product-category"
                            className="w-full"
                        >
                            <SelectValue placeholder="Select a category" />
                        </SelectTrigger>
                        <SelectContent>
                            {availableCategories.map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={String(category.id)}
                                >
                                    {category.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="product_category_id"
                        value={categoryId ?? ''}
                    />
                    <p className="text-muted-foreground text-xs">
                        {availableCategories.length === 0
                            ? 'No categories yet — add them under Categories.'
                            : 'Its legal family.'}
                    </p>
                    <InputError message={errors.product_category_id} />
                </div>

                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-template`}>Template</Label>
                    <Select
                        value={
                            templateId === null ? undefined : String(templateId)
                        }
                        onValueChange={(value) =>
                            onTemplateChange(Number(value))
                        }
                        disabled={disabled || templates.length === 0}
                    >
                        <SelectTrigger
                            id={`${idPrefix}-template`}
                            data-test="product-template"
                            className="w-full"
                        >
                            <SelectValue
                                placeholder={
                                    hasCategory
                                        ? 'Select a template'
                                        : 'Pick a category first'
                                }
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {templates.map((template) => (
                                <SelectItem
                                    key={template.id}
                                    value={String(template.id)}
                                >
                                    {template.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="product_template_id"
                        value={templateId ?? ''}
                    />
                    <p className="text-muted-foreground text-xs">
                        {hasCategory && templates.length === 0
                            ? `${categoryLabel} has no templates yet.`
                            : 'Which documents and data this product is expected to carry.'}
                    </p>
                    <InputError message={errors.product_template_id} />
                </div>
            </div>
        </div>
    );
}
