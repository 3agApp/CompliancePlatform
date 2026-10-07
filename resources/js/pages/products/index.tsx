import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Package,
    Pencil,
    Plus,
    SearchX,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import CompletenessMeter from '@/components/completeness-meter';
import DeleteProductModal from '@/components/delete-product-modal';
import ProductFilterBar from '@/components/product-filter-bar';
import ProductReviewStatusBadge from '@/components/product-review-status-badge';
import { Badge } from '@/components/ui/badge';
import PaginationArrow from '@/components/pagination-arrow';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { index as categoriesIndex } from '@/routes/categories';
import { create, edit, index as productsIndex } from '@/routes/products';
import { index as suppliersIndex } from '@/routes/suppliers';
import type {
    BrandOption,
    OrganizationType,
    Paginated,
    Product,
    ProductCategoryOption,
    ProductCounterparty,
    ProductFilters,
    ProductPermissions,
    ProductReviewStatusOption,
    SupplierConnectionOption,
} from '@/types';

/**
 * Reference numbers a reader looks up rather than scans for. On a laptop
 * they would push status and completeness -- the columns the list is read
 * for -- off the side of the table, so they wait for a wide screen. The
 * search box still finds a product by either one, and a phone, where every
 * row is a card and nothing is pushed sideways, keeps them.
 */
const SECONDARY_COLUMN = 'px-4 md:max-2xl:hidden';

type Props = {
    products: Paginated<Product>;
    pageSizes: number[];
    permissions: ProductPermissions;
    availableConnections: SupplierConnectionOption[];
    hasTemplates: boolean;
    counterparties: ProductCounterparty[];
    filterableCategories: ProductCategoryOption[];
    filterableBrands: BrandOption[];
    filters: ProductFilters;
    availableStatuses: ProductReviewStatusOption[];
    hasProducts: boolean;
    viewerType: OrganizationType;
};

export default function ProductsIndex({
    products,
    pageSizes,
    permissions,
    availableConnections,
    hasTemplates,
    counterparties,
    filterableCategories,
    filterableBrands,
    filters,
    availableStatuses,
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
        filters.status !== null ||
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

    /**
     * The page size lives in the URL beside the filters, so changing it is a
     * visit like any other -- and one that starts again from the first page,
     * since the row the reader was looking at sits elsewhere once the page
     * size changes.
     */
    const changePerPage = (size: number) => {
        router.get(
            productsIndex(organizationSlug, {
                mergeQuery: { per_page: String(size), page: undefined },
            }),
            {},
            {
                only: ['products', 'filters', 'hasProducts'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

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
                        availableStatuses={availableStatuses}
                    />
                ) : null}

                {products.data.length > 0 ? (
                    <div className="workspace-table">
                        <Table className="md:min-w-3xl">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="px-4 md:pl-6">
                                        Name
                                    </TableHead>
                                    <TableHead className="w-px px-4">
                                        Status
                                    </TableHead>
                                    <TableHead className="w-px px-4">
                                        Complete
                                    </TableHead>
                                    <TableHead className="px-4">
                                        {counterpartyLabel}
                                    </TableHead>
                                    <TableHead className="px-4">
                                        Brand
                                    </TableHead>
                                    <TableHead className="px-4">
                                        Category
                                    </TableHead>
                                    <TableHead className={SECONDARY_COLUMN}>
                                        EAN / barcode
                                    </TableHead>
                                    <TableHead className={SECONDARY_COLUMN}>
                                        Country of origin
                                    </TableHead>
                                    <TableHead className="w-px px-4 md:pr-6">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {products.data.map((product) => (
                                    <TableRow
                                        key={product.id}
                                        data-test="product-row"
                                    >
                                        <TableCell className="min-w-48 px-4 font-medium whitespace-normal md:pl-6">
                                            <Link
                                                href={edit([
                                                    organizationSlug,
                                                    product.id,
                                                ])}
                                                className="hover:underline"
                                                data-test="product-name-link"
                                            >
                                                {product.name}
                                            </Link>
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
                                        </TableCell>
                                        <TableCell
                                            className="px-4"
                                            data-label="Status"
                                        >
                                            <ProductReviewStatusBadge
                                                status={product.review_status}
                                                label={
                                                    product.review_status_label
                                                }
                                            />
                                        </TableCell>
                                        <TableCell
                                            className="px-4"
                                            data-label="Complete"
                                        >
                                            <CompletenessMeter
                                                score={
                                                    product.completeness_score
                                                }
                                            />
                                        </TableCell>
                                        <TableCell
                                            className="px-4 whitespace-normal"
                                            data-test="product-counterparty"
                                            data-label={counterpartyLabel}
                                        >
                                            <span className="text-muted-foreground">
                                                {product.counterparty ?? '—'}
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
                                                        ? product.connection_is_invited
                                                            ? 'Pending'
                                                            : 'Not invited'
                                                        : 'Revoked'}
                                                </Badge>
                                            ) : null}
                                        </TableCell>
                                        <TableCell
                                            className="text-muted-foreground px-4 whitespace-normal"
                                            data-label="Brand"
                                        >
                                            {product.brand_label ?? '—'}
                                        </TableCell>
                                        <TableCell
                                            className="text-muted-foreground px-4 whitespace-normal"
                                            data-test="product-list-category"
                                            data-label="Category"
                                        >
                                            {product.category_label}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                SECONDARY_COLUMN,
                                                'text-muted-foreground font-mono text-xs',
                                            )}
                                            data-label="EAN / barcode"
                                        >
                                            {product.ean ?? '—'}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                SECONDARY_COLUMN,
                                                'text-muted-foreground',
                                            )}
                                            data-label="Country of origin"
                                        >
                                            {product.country_of_origin_label ??
                                                '—'}
                                        </TableCell>
                                        <TableCell className="px-4 md:pr-6">
                                            <div className="flex items-center justify-end gap-1">
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
                                                        <TooltipTrigger asChild>
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
                                                                Delete product
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                ) : null}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="flex flex-col items-center justify-between gap-3 border-t px-4 py-3 text-sm sm:flex-row">
                            <div className="text-muted-foreground flex flex-wrap items-center justify-center gap-3">
                                <p data-test="product-count">
                                    Showing{' '}
                                    <span className="text-foreground font-medium">
                                        {products.from}–{products.to}
                                    </span>{' '}
                                    of{' '}
                                    <span className="text-foreground font-medium">
                                        {products.total}
                                    </span>{' '}
                                    products
                                </p>

                                <Select
                                    value={String(filters.perPage)}
                                    onValueChange={(value) =>
                                        changePerPage(Number(value))
                                    }
                                >
                                    <SelectTrigger
                                        size="sm"
                                        className="w-28"
                                        aria-label="Products per page"
                                        data-test="per-page"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {pageSizes.map((size) => (
                                            <SelectItem
                                                key={size}
                                                value={String(size)}
                                            >
                                                {size} / page
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {products.last_page > 1 ? (
                                <nav
                                    className="flex flex-wrap items-center justify-center gap-1"
                                    aria-label="Pagination"
                                >
                                    <PaginationArrow
                                        href={products.prev_page_url}
                                        label="Previous page"
                                        icon={ChevronLeft}
                                        test="pagination-previous"
                                    />
                                    {products.links
                                        .slice(1, -1)
                                        .map((link, index) =>
                                            link.url ? (
                                                <Button
                                                    key={`${link.label}-${index}`}
                                                    variant={
                                                        link.active
                                                            ? 'outline'
                                                            : 'ghost'
                                                    }
                                                    size="sm"
                                                    className="min-w-8 px-2 tabular-nums"
                                                    asChild
                                                >
                                                    <Link
                                                        href={link.url}
                                                        preserveScroll
                                                        preserveState
                                                        aria-current={
                                                            link.active
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                    >
                                                        {link.label}
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <span
                                                    key={`${link.label}-${index}`}
                                                    className="text-muted-foreground px-2"
                                                >
                                                    {link.label}
                                                </span>
                                            ),
                                        )}
                                    <PaginationArrow
                                        href={products.next_page_url}
                                        label="Next page"
                                        icon={ChevronRight}
                                        test="pagination-next"
                                    />
                                </nav>
                            ) : null}
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
