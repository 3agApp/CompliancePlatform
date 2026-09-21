import { Head, Link, usePage } from '@inertiajs/react';
import { Package, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CompletenessMeter from '@/components/completeness-meter';
import DeleteProductModal from '@/components/delete-product-modal';
import ProductFilterBar from '@/components/product-filter-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index as categoriesIndex } from '@/routes/categories';
import { create, edit } from '@/routes/products';
import { index as suppliersIndex } from '@/routes/suppliers';
import type {
    BrandOption,
    OrganizationType,
    Product,
    ProductCategoryOption,
    ProductCounterparty,
    ProductFilters,
    ProductPermissions,
    SupplierConnectionOption,
} from '@/types';

type Props = {
    products: Product[];
    permissions: ProductPermissions;
    availableConnections: SupplierConnectionOption[];
    hasTemplates: boolean;
    counterparties: ProductCounterparty[];
    filterableCategories: ProductCategoryOption[];
    filterableBrands: BrandOption[];
    filters: ProductFilters;
    hasProducts: boolean;
    viewerType: OrganizationType;
};

export default function ProductsIndex({
    products,
    permissions,
    availableConnections,
    hasTemplates,
    counterparties,
    filterableCategories,
    filterableBrands,
    filters,
    hasProducts,
    viewerType,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const isSupplier = viewerType === 'supplier';
    const counterpartyLabel = isSupplier ? 'Distributor' : 'Supplier';

    const isFiltered =
        filters.connection !== null ||
        filters.category !== null ||
        filters.brand !== null ||
        filters.search !== null;

    /**
     * A filter bar over a catalogue that is empty for want of products, not
     * for want of a match, is noise.
     */
    const showFilters = hasProducts || isFiltered;

    /**
     * Every product needs a supplier and a template, so a distributor
     * missing either is pointed at the page that fixes it rather than at a
     * form they cannot submit. Suppliers come first: without one there is
     * nothing to file a product against at all.
     */
    const needsSupplier =
        permissions.canCreateProduct && availableConnections.length === 0;

    const needsTemplate =
        permissions.canCreateProduct && !needsSupplier && !hasTemplates;

    const canAddProducts =
        permissions.canCreateProduct && !needsSupplier && !needsTemplate;

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
                        <h1 className="page-title">Products</h1>
                        <p className="text-muted-foreground text-sm">
                            {isSupplier
                                ? `Products distributors have assigned to ${currentOrganization?.name}.`
                                : `The products ${currentOrganization?.name} is responsible for.`}
                        </p>
                    </div>

                    {canAddProducts ? (
                        <Button asChild>
                            <Link
                                href={create(organizationSlug)}
                                data-test="products-new-product-button"
                            >
                                <Plus /> New product
                            </Link>
                        </Button>
                    ) : needsSupplier ? (
                        <Button variant="outline" asChild>
                            <Link
                                href={suppliersIndex(organizationSlug)}
                                data-test="products-invite-supplier-button"
                            >
                                <Plus /> Invite a supplier
                            </Link>
                        </Button>
                    ) : needsTemplate ? (
                        <Button variant="outline" asChild>
                            <Link
                                href={categoriesIndex(organizationSlug)}
                                data-test="products-add-template-button"
                            >
                                <Plus /> Add a template
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
                        filterableCategories={filterableCategories}
                        filterableBrands={filterableBrands}
                    />
                ) : null}

                {products.length > 0 ? (
                    <div className="workspace-table">
                        <div className="min-w-0 overflow-x-auto">
                            <table className="w-full min-w-3xl text-left text-sm">
                                <thead>
                                    <tr className="text-muted-foreground">
                                        <th className="px-6 font-medium">
                                            Name
                                        </th>
                                        <th className="px-6 font-medium">
                                            Brand
                                        </th>
                                        <th className="px-6 font-medium">
                                            Category
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
                                            Complete
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
                                            key={product.id}
                                            data-test="product-row"
                                            className="border-t"
                                        >
                                            <td className="px-6 font-medium break-words">
                                                {product.name}
                                                {product.internal_article_number ? (
                                                    <span
                                                        className="text-muted-foreground block font-mono text-xs font-normal"
                                                        data-test="product-list-article-number"
                                                    >
                                                        {
                                                            product.internal_article_number
                                                        }
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="text-muted-foreground px-6 break-words">
                                                {product.brand_label ?? '—'}
                                            </td>
                                            <td
                                                className="text-muted-foreground px-6"
                                                data-test="product-list-category"
                                            >
                                                {product.category_label}
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
                                                <CompletenessMeter
                                                    score={
                                                        product.completeness_score
                                                    }
                                                />
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
                                                                        product.id,
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
                                {filters.search !== null
                                    ? 'Try a different name, barcode or article number, or clear the filters.'
                                    : 'Nothing matches the filters you have set. Try clearing one.'}
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
                                      : needsSupplier
                                        ? 'Invite a supplier first — every product is assigned to one.'
                                        : needsTemplate
                                          ? 'Add a template to one of your categories first — every product is held to one.'
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
