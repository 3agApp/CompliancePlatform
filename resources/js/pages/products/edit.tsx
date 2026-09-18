import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DeleteProductModal from '@/components/delete-product-modal';
import ProductFormFields from '@/components/product-form-fields';
import { Button } from '@/components/ui/button';
import { index, update } from '@/routes/products';
import type {
    CountryOption,
    OrganizationType,
    Product,
    ProductCategoryOption,
    ProductPermissions,
    SupplierConnectionOption,
} from '@/types';

type Props = {
    product: Product;
    permissions: ProductPermissions;
    availableCountries: CountryOption[];
    availableCategories: ProductCategoryOption[];
    availableConnections: SupplierConnectionOption[];
    viewerType: OrganizationType;
};

export default function ProductEdit({
    product,
    permissions,
    availableCountries,
    availableCategories,
    availableConnections,
    viewerType,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    return (
        <>
            <Head title={product.name} />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="-ml-2"
                            asChild
                        >
                            <Link href={index(organizationSlug)}>
                                <ArrowLeft className="h-4 w-4" /> Products
                            </Link>
                        </Button>
                        <h1 className="page-title break-words">
                            {product.name}
                        </h1>
                        {product.counterparty ? (
                            <p className="text-muted-foreground text-sm">
                                {viewerType === 'supplier'
                                    ? 'Assigned by '
                                    : 'Supplied by '}
                                <span className="text-foreground font-medium">
                                    {product.counterparty}
                                </span>
                            </p>
                        ) : null}
                    </div>

                    {permissions.canDeleteProduct ? (
                        <Button
                            variant="outline"
                            data-test="product-delete-button"
                            onClick={() => setDeleteDialogOpen(true)}
                        >
                            <Trash2 className="h-4 w-4" /> Delete product
                        </Button>
                    ) : null}
                </div>

                <div className="workspace-panel max-w-2xl p-6">
                    <Form
                        {...update.form([organizationSlug, product.uuid])}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ errors, processing }) => (
                            <>
                                <ProductFormFields
                                    errors={errors}
                                    availableCountries={availableCountries}
                                    availableCategories={availableCategories}
                                    availableConnections={availableConnections}
                                    viewerType={viewerType}
                                    product={product}
                                    disabled={!permissions.canUpdateProduct}
                                    idPrefix="edit-product"
                                />

                                {permissions.canUpdateProduct ? (
                                    <div className="flex items-center gap-3">
                                        <Button
                                            type="submit"
                                            data-test="update-product-submit"
                                            disabled={processing}
                                        >
                                            Save changes
                                        </Button>
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        You do not have permission to edit this
                                        product.
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                </div>
            </div>

            <DeleteProductModal
                organizationSlug={organizationSlug}
                product={product}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}
