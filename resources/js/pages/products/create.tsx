import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Tags } from 'lucide-react';
import { useState } from 'react';
import ProductClassificationFields from '@/components/product-classification-fields';
import ProductComplianceFields from '@/components/product-compliance-fields';
import ProductFormFields from '@/components/product-form-fields';
import TemplateRequirementSummary from '@/components/template-requirement-summary';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { TabPanel, TabStrip } from '@/components/ui/tabs';
import { index as categoriesIndex } from '@/routes/categories';
import { index as productsIndex, store } from '@/routes/products';
import type {
    BrandOption,
    CountryOption,
    ProductCategoryOption,
    ProductRequirementOption,
    ProductTemplateOption,
    SupplierConnectionOption,
} from '@/types';

type Props = {
    availableCountries: CountryOption[];
    availableCategories: ProductCategoryOption[];
    availableTemplates: ProductTemplateOption[];
    availableBrands: BrandOption[];
    availableConnections: SupplierConnectionOption[];
    availableRequirements: ProductRequirementOption[];
    canCreateBrand: boolean;
};

export default function ProductsCreate({
    availableCountries,
    availableCategories,
    availableTemplates,
    availableBrands,
    availableConnections,
    availableRequirements,
    canCreateBrand,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const [connectionId, setConnectionId] = useState<number | null>(null);
    const [categoryId, setCategoryId] = useState<number | null>(null);
    const [templateId, setTemplateId] = useState<number | null>(null);

    /**
     * Both panels stay mounted and the step only decides which is shown. A
     * field the person filled in on the other one therefore survives the
     * move, and a validation error on any of them still reaches the server
     * on submit -- it can be sent from whichever step they happen to be on.
     */
    const [step, setStep] = useState<1 | 2>(1);

    /**
     * The details step is two groups of very different questions -- what
     * the product is, and what it claims about its own safety -- so they
     * are tabs rather than one long scroll. Both panels stay mounted, so
     * whatever is typed on either is submitted together.
     */
    const [detailsTab, setDetailsTab] = useState<
        'identification' | 'compliance'
    >('identification');

    const template =
        availableTemplates.find((option) => option.id === templateId) ?? null;

    const templatesInCategory = availableTemplates.filter(
        (option) => option.product_category_id === categoryId,
    );

    const supplierLabel =
        availableConnections.find(
            (connection) => connection.id === connectionId,
        )?.label ?? null;

    const categoryLabel =
        availableCategories.find((category) => category.id === categoryId)
            ?.label ?? '';

    /**
     * A category with no template cannot take a product: the template is
     * required and there is nothing to choose. Saying so here, with the way
     * out, beats a form that rejects itself on submit.
     */
    const isDeadEnd = categoryId !== null && templatesInCategory.length === 0;

    const isClassified =
        connectionId !== null && categoryId !== null && templateId !== null;

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
                    First say what kind of product it is, then fill in what its
                    template asks for.
                </p>
            </div>

            <Form
                {...store.form(organizationSlug)}
                className="grid max-w-3xl gap-6"
            >
                {({ errors, processing }) => (
                    <>
                        <Steps step={step} />

                        <section
                            className="workspace-panel grid gap-6 p-6"
                            hidden={step !== 1}
                            data-test="product-create-step-classify"
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
                            />

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
                                        None of it is required to save. The
                                        product keeps score of what is still
                                        outstanding.
                                    </p>
                                </div>
                            ) : null}

                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    data-test="create-product-continue"
                                    disabled={!isClassified}
                                    onClick={() => setStep(2)}
                                >
                                    Continue
                                    <ArrowRight className="size-4" />
                                </Button>
                            </div>
                        </section>

                        <section
                            className="workspace-panel grid gap-6 p-6"
                            hidden={step !== 2}
                            data-test="product-create-step-details"
                        >
                            <div className="grid gap-1">
                                <h2 className="text-base font-semibold">
                                    Product details
                                </h2>
                                <p className="text-muted-foreground text-sm">
                                    What {template?.label ?? 'the template'}{' '}
                                    asks for is marked. You can save without it
                                    and fill the rest in later.
                                </p>
                            </div>

                            <TabStrip
                                idPrefix="create-product"
                                tabs={[
                                    {
                                        value: 'identification',
                                        label: 'Identification',
                                    },
                                    {
                                        value: 'compliance',
                                        label: 'Compliance',
                                    },
                                ]}
                                value={detailsTab}
                                onValueChange={setDetailsTab}
                            />

                            <TabPanel
                                value="identification"
                                active={detailsTab}
                                idPrefix="create-product"
                            >
                                <ProductFormFields
                                    errors={errors}
                                    availableCountries={availableCountries}
                                    availableBrands={availableBrands}
                                    supplierConnectionId={connectionId}
                                    supplierLabel={supplierLabel}
                                    organizationSlug={organizationSlug}
                                    canCreateBrand={canCreateBrand}
                                    requirements={template?.requirements ?? []}
                                    idPrefix="create-product"
                                />
                            </TabPanel>

                            <TabPanel
                                value="compliance"
                                active={detailsTab}
                                idPrefix="create-product"
                            >
                                <ProductComplianceFields
                                    errors={errors}
                                    requirements={template?.requirements ?? []}
                                    idPrefix="create-product"
                                />
                            </TabPanel>

                            <div className="flex justify-between gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-test="create-product-back"
                                    onClick={() => setStep(1)}
                                >
                                    <ArrowLeft className="size-4" />
                                    Back
                                </Button>

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

function Steps({ step }: { step: 1 | 2 }) {
    const steps = ['Classify', 'Details'];

    return (
        <ol className="text-muted-foreground flex items-center gap-3 text-sm">
            {steps.map((label, index) => {
                const number = index + 1;
                const isCurrent = number === step;

                return (
                    <li key={label} className="flex items-center gap-3">
                        <span
                            className={
                                isCurrent
                                    ? 'text-foreground font-medium'
                                    : undefined
                            }
                        >
                            <span className="bg-muted mr-2 inline-flex size-5 items-center justify-center rounded-full text-xs tabular-nums">
                                {number}
                            </span>
                            {label}
                        </span>
                        {number < steps.length ? (
                            <span className="bg-border h-px w-8" />
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}
