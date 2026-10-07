import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Tags } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import ProductClassificationFields from '@/components/product-classification-fields';
import TemplateRequirementSummary from '@/components/template-requirement-summary';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { index as categoriesIndex } from '@/routes/categories';
import { index as productsIndex, store } from '@/routes/products';
import type {
    ProductCategoryOption,
    ProductRequirementOption,
    ProductTemplateOption,
    SupplierConnectionOption,
} from '@/types';

type Props = {
    availableCategories: ProductCategoryOption[];
    availableTemplates: ProductTemplateOption[];
    availableConnections: SupplierConnectionOption[];
    availableRequirements: ProductRequirementOption[];
    canAddSupplier: boolean;
};

/**
 * Everything a product cannot exist without, and nothing else.
 *
 * Who supplies it, which legal family it falls under, which of that
 * family's templates it is held to, and what it is called. The numbers on
 * the box, what it claims about its own safety and the papers behind it
 * are filled in on the product itself, usually later and often by the
 * other side of the trade -- asking for them here only stands between a
 * person and the record they came to open.
 */
export default function ProductsCreate({
    availableCategories,
    availableTemplates,
    availableConnections,
    availableRequirements,
    canAddSupplier,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const [dirty, setDirty] = useState(false);

    const [connectionId, setConnectionId] = useState<number | null>(null);
    const [categoryId, setCategoryId] = useState<number | null>(null);
    const [templateId, setTemplateId] = useState<number | null>(null);

    const template =
        availableTemplates.find((option) => option.id === templateId) ?? null;

    const templatesInCategory = availableTemplates.filter(
        (option) => option.product_category_id === categoryId,
    );

    const categoryLabel =
        availableCategories.find((category) => category.id === categoryId)
            ?.label ?? '';

    /**
     * A category with no template cannot take a product: the template is
     * required and there is nothing to choose. Saying so here, with the way
     * out, beats a form that rejects itself on submit.
     */
    const isDeadEnd = categoryId !== null && templatesInCategory.length === 0;

    const chooseCategory = (nextCategoryId: number) => {
        setCategoryId(nextCategoryId);
        setTemplateId(null);
    };

    return (
        <div className="workspace-page">
            <Head title="Add a product" />

            <div className="page-heading">
                <Link
                    href={productsIndex(organizationSlug)}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                    data-test="product-back-link"
                >
                    <ArrowLeft className="size-4" />
                    Products
                </Link>
                <h1 className="page-title">Add a product</h1>
                <p className="text-muted-foreground text-sm">
                    Say what kind of product it is and what it is called. The
                    rest is filled in on the product itself.
                </p>
            </div>

            <Form
                {...store.form(organizationSlug)}
                className="grid max-w-3xl gap-6"
                /**
                 * Typing raises input; the selects raise only change. Both
                 * are needed, or classifying a product and walking away
                 * would lose the edit without the guard ever asking.
                 */
                onInput={() => setDirty(true)}
                onChange={() => setDirty(true)}
                onSuccess={() => setDirty(false)}
            >
                {({ errors, processing }) => (
                    <>
                        <UnsavedChangesGuard dirty={dirty && !processing} />

                        <section
                            className="workspace-panel grid gap-6 p-6"
                            data-test="product-create-panel"
                        >
                            <div className="grid gap-1">
                                <h2 className="text-base font-semibold">
                                    Classify the product
                                </h2>
                                <p className="text-muted-foreground text-sm">
                                    Which supplier is responsible for it, which
                                    legal family it falls under, and which of
                                    that family's templates it is held to.
                                </p>
                            </div>

                            <ProductClassificationFields
                                errors={errors}
                                availableCategories={availableCategories}
                                availableTemplates={availableTemplates}
                                availableConnections={availableConnections}
                                viewerType="distributor"
                                categoryId={categoryId}
                                onCategoryChange={chooseCategory}
                                templateId={templateId}
                                onTemplateChange={setTemplateId}
                                connectionId={connectionId}
                                onConnectionChange={setConnectionId}
                                idPrefix="create-product"
                                addSupplier={
                                    canAddSupplier ? { organizationSlug } : null
                                }
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="create-product-name">
                                    Product name
                                </Label>
                                <Input
                                    id="create-product-name"
                                    name="name"
                                    data-test="product-name"
                                    placeholder="Organic oat milk 1L"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            {isDeadEnd ? (
                                <Alert data-test="product-template-dead-end">
                                    <Tags className="size-4" />
                                    <AlertTitle>
                                        {categoryLabel} has no templates yet
                                    </AlertTitle>
                                    <AlertDescription>
                                        <p>
                                            A template says which documents and
                                            data this kind of product needs.
                                            Every product is held to one, so add
                                            a template to {categoryLabel} before
                                            filing anything under it.
                                        </p>
                                        <Link
                                            href={categoriesIndex(
                                                organizationSlug,
                                            )}
                                            className="font-medium underline underline-offset-4"
                                            data-test="product-manage-categories-link"
                                        >
                                            Manage categories and templates
                                        </Link>
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            {template !== null ? (
                                <div className="bg-muted/40 grid gap-2 rounded-xl border p-4">
                                    <p className="text-sm font-medium">
                                        {template.label} asks for
                                    </p>
                                    <TemplateRequirementSummary
                                        requirements={template.requirements}
                                        availableRequirements={
                                            availableRequirements
                                        }
                                        detailed
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        None of it is required to save. You fill
                                        it in on the product, which keeps score
                                        of what is still outstanding.
                                    </p>
                                </div>
                            ) : null}

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    data-test="create-product-submit"
                                    disabled={processing}
                                >
                                    Add product
                                </Button>
                            </div>
                        </section>
                    </>
                )}
            </Form>
        </div>
    );
}

/**
 * Warn before leaving the form with edits that have not been saved.
 *
 * A component rather than a call in the page, because the dirty flag it
 * watches is only known inside the form's render prop.
 */
function UnsavedChangesGuard({ dirty }: { dirty: boolean }) {
    useUnsavedChanges(
        dirty,
        'This product has changes that have not been saved. Leave anyway?',
    );

    return null;
}
