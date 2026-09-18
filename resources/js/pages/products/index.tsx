import { Head, Link, usePage } from '@inertiajs/react';
import { Package, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CreateProductModal from '@/components/create-product-modal';
import DeleteProductModal from '@/components/delete-product-modal';
import ProductFilterBar from '@/components/product-filter-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit } from '@/routes/products';
import { index as suppliersIndex } from '@/routes/suppliers';
import type {
    CountryOption,
    OrganizationType,
    Product,
    ProductCounterparty,
    ProductFilters,
    ProductPermissions,
    SupplierConnectionOption,
} from '@/types';

type Props = {
    products: Product[];
    permissions: ProductPermissions;
    availableCountries: CountryOption[];
    availableConnections: SupplierConnectionOption[];
    counterparties: ProductCounterparty[];
    filters: ProductFilters;
    hasProducts: boolean;
    viewerType: OrganizationType;
};

export default function ProductsIndex({
    products,
    permissions,
    availableCountries,
    availableConnections,
    counterparties,
    filters,
    hasProducts,
    viewerType,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const isSupplier = viewerType === 'supplier';
    const counterpartyLabel = isSupplier ? 'Distributor' : 'Supplier';

    const isFiltered = filters.connection !== null || filters.search !== null;

    /**
     * A filter bar over a catalogue that is empty for want of products, not
     * for want of a match, is noise.
     */
    const showFilters = hasProducts || isFiltered;

    /**
     * Every product needs a supplier, so a distributor with no suppliers yet
     * is pointed at the suppliers page rather than at a form they cannot
     * submit.
     */
    const canAddProducts =
        permissions.canCreateProduct && availableConnections.length > 0;

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [productToDelete, setProductToDelete] = useState<Product | null>(
        null,
    );

    const confirmDelete = (product: Product) => {
        setProductToDelete(product);
        setDeleteDialogOpen(true);
    };

    return (
        <>
            <Head title="Products" />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <p className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                            {isSupplier
                                ? 'Assigned to you'
                                : 'Organization catalog'}
                        </p>
                        <h1 className="page-title">Products</h1>
                        <p className="text-muted-foreground text-sm">
                            {isSupplier
                                ? `Products distributors have assigned to ${currentOrganization?.name}.`
                                : `The products ${currentOrganization?.name} is responsible for.`}
                        </p>
                    </div>

                    {canAddProducts ? (
                        <CreateProductModal
                            organizationSlug={organizationSlug}
                            availableCountries={availableCountries}
                            availableConnections={availableConnections}
                        >
                            <Button data-test="products-new-product-button">
                                <Plus /> New product
                            </Button>
                        </CreateProductModal>
                    ) : permissions.canCreateProduct ? (
                        <Button variant="outline" asChild>
                            <Link
                                href={suppliersIndex(organizationSlug)}
                                data-test="products-invite-supplier-button"
                            >
                                <Plus /> Invite a supplier
                            </Link>
                        </Button>
                    ) : null}
                </div>

                {showFilters ? (
                    <ProductFilterBar
                        organizationSlug={organizationSlug}
                        filters={filters}
                        counterparties={counterparties}
                        counterpartyLabel={counterpartyLabel}
                    />
                ) : null}

                {products.length > 0 ? (
                    <div className="workspace-table">
                        <div className="min-w-0 overflow-x-auto">
                            <table className="w-full min-w-xl text-left text-sm">
                                <thead>
                                    <tr className="text-muted-foreground">
                                        <th className="px-6 font-medium">
                                            Name
                                        </th>
                                        <th className="px-6 font-medium">
                                            {counterpartyLabel}
                                        </th>
                                        <th className="px-6 font-medium">
                                            EAN / barcode
                                        </th>
                                        <th className="px-6 font-medium">
                                            Country of origin
                                        </th>
                                        <th className="px-6 font-medium">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {products.map((product) => (
                                        <tr
                                            key={product.uuid}
                                            data-test="product-row"
                                            className="border-t"
                                        >
                                            <td className="px-6 font-medium break-words">
                                                {product.name}
                                            </td>
                                            <td
                                                className="px-6"
                                                data-test="product-counterparty"
                                            >
                                                <span className="text-muted-foreground">
                                                    {product.counterparty ??
                                                        '—'}
                                                </span>
                                                {!isSupplier &&
                                                product.connection_status &&
                                                product.connection_status !==
                                                    'active' ? (
                                                    <Badge
                                                        variant="secondary"
                                                        className="ml-2"
                                                    >
                                                        {product.connection_status ===
                                                        'pending'
                                                            ? 'Pending'
                                                            : 'Revoked'}
                                                    </Badge>
                                                ) : null}
                                            </td>
                                            <td className="text-muted-foreground px-6 font-mono text-xs">
                                                {product.ean ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-6">
                                                {product.country_of_origin_label ??
                                                    '—'}
                                            </td>
                                            <td className="px-6">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                data-test="product-edit-button"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={edit([
                                                                        organizationSlug,
                                                                        product.uuid,
                                                                    ])}
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        {permissions.canUpdateProduct
                                                                            ? 'Edit product'
                                                                            : 'View product'}
                                                                    </span>
                                                                </Link>
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>
                                                                {permissions.canUpdateProduct
                                                                    ? 'Edit product'
                                                                    : 'View product'}
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>

                                                    {permissions.canDeleteProduct ? (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    data-test="product-delete-button"
                                                                    onClick={() =>
                                                                        confirmDelete(
                                                                            product,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Delete
                                                                        product
                                                                    </span>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>
                                                                    Delete
                                                                    product
                                                                </p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : isFiltered ? (
                    <div
                        className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center"
                        data-test="products-no-matches"
                    >
                        <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <SearchX className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">
                                No products match these filters
                            </h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {filters.search === null
                                    ? `Nothing is assigned to this ${counterpartyLabel.toLowerCase()} yet.`
                                    : 'Try a different name or barcode, or clear the filters.'}
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                        <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <Package className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">No products yet</h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {isSupplier
                                    ? 'Products a distributor assigns to you will show up here.'
                                    : canAddProducts
                                      ? 'Add your first product to start tracking it.'
                                      : permissions.canCreateProduct
                                        ? 'Invite a supplier first — every product is assigned to one.'
                                        : 'Products added to this organization will show up here.'}
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <DeleteProductModal
                organizationSlug={organizationSlug}
                product={productToDelete}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}
