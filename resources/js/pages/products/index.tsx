import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    MoreHorizontal,
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatRelative } from '@/lib/format';
import { t, tn } from '@/lib/i18n';
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
    ProductReviewStatus,
    ProductReviewStatusOption,
    SupplierConnectionOption,
} from '@/types';

/**
 * The order the status tabs run in: the order a product moves through the
 * review, rather than the order the states happen to be declared in.
 */
const STATUS_ORDER: ProductReviewStatus[] = [
    'draft',
    'in_review',
    'changes_requested',
    'approved',
];

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
    /** How many products each tab would show, under the other filters. */
    statusCounts: Record<ProductReviewStatus, number>;
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
    statusCounts,
    hasProducts,
    viewerType,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const isSupplier = viewerType === 'supplier';
    const counterpartyLabel = isSupplier ? t('Distributor') : t('Supplier');

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
            <Head title={t('Products')} />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <h1 className="page-title">{t('Products')}</h1>
                        <p className="text-muted-foreground text-sm">
                            {isSupplier
                                ? t(
                                      'Products distributors have assigned to :organization.',
                                      {
                                          organization:
                                              currentOrganization?.name,
                                      },
                                  )
                                : t(
                                      'The products :organization is responsible for.',
                                      {
                                          organization:
                                              currentOrganization?.name,
                                      },
                                  )}
                        </p>
                    </div>

                    {canAddProducts ? (
                        <Button asChild>
                            <Link
                                href={create(organizationSlug)}
                                data-test="products-new-product-button"
                            >
                                <Plus /> {t('New product')}
                            </Link>
                        </Button>
                    ) : needsSupplier ? (
                        <Button variant="outline" asChild>
                            <Link
                                href={suppliersIndex(organizationSlug)}
                                data-test="products-invite-supplier-button"
                            >
                                <Plus /> {t('Invite a supplier')}
                            </Link>
                        </Button>
                    ) : needsTemplate ? (
                        <Button variant="outline" asChild>
                            <Link
                                href={categoriesIndex(organizationSlug)}
                                data-test="products-add-template-button"
                            >
                                <Plus /> {t('Add a template')}
                            </Link>
                        </Button>
                    ) : null}
                </div>

                {showFilters ? (
                    <StatusTabs
                        organizationSlug={organizationSlug}
                        active={filters.status}
                        counts={statusCounts}
                        statuses={availableStatuses}
                    />
                ) : null}

                {showFilters ? (
                    <ProductFilterBar
                        organizationSlug={organizationSlug}
                        filters={filters}
                        counterparties={counterparties}
                        counterpartyKind={
                            isSupplier ? 'distributor' : 'supplier'
                        }
                        filterableCategories={filterableCategories}
                        filterableBrands={filterableBrands}
                    />
                ) : null}

                {products.data.length > 0 ? (
                    <div className="workspace-table">
                        <Table className="md:min-w-3xl">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="px-4 md:pl-6">
                                        {t('Name')}
                                    </TableHead>
                                    <TableHead className="w-px px-4">
                                        {t('Status')}
                                    </TableHead>
                                    <TableHead className="w-px px-4">
                                        {t('Complete')}
                                    </TableHead>
                                    <TableHead className="px-4">
                                        {counterpartyLabel}
                                    </TableHead>
                                    <TableHead className="px-4">
                                        {t('Brand')}
                                    </TableHead>
                                    <TableHead className="px-4">
                                        {t('Category')}
                                    </TableHead>
                                    <TableHead className={SECONDARY_COLUMN}>
                                        {t('EAN / barcode')}
                                    </TableHead>
                                    <TableHead className={SECONDARY_COLUMN}>
                                        {t('Country of origin')}
                                    </TableHead>
                                    <TableHead className="w-px px-4">
                                        {t('Updated')}
                                    </TableHead>
                                    <TableHead className="w-px px-4 md:pr-6">
                                        <span className="sr-only">
                                            {t('Actions')}
                                        </span>
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
                                            data-label={t('Status')}
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
                                            data-label={t('Complete')}
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
                                                    className={cn(
                                                        'ml-2',
                                                        product.connection_is_expired &&
                                                            'bg-red-500/10 text-red-700 dark:text-red-400',
                                                    )}
                                                    data-test="product-connection-status"
                                                >
                                                    {
                                                        product.connection_status_label
                                                    }
                                                </Badge>
                                            ) : null}
                                        </TableCell>
                                        <TableCell
                                            className="text-muted-foreground px-4 whitespace-normal"
                                            data-label={t('Brand')}
                                        >
                                            {product.brand_label ?? '—'}
                                        </TableCell>
                                        <TableCell
                                            className="text-muted-foreground px-4 whitespace-normal"
                                            data-test="product-list-category"
                                            data-label={t('Category')}
                                        >
                                            {product.category_label}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                SECONDARY_COLUMN,
                                                'text-muted-foreground font-mono text-xs',
                                            )}
                                            data-label={t('EAN / barcode')}
                                        >
                                            {product.ean ?? '—'}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                SECONDARY_COLUMN,
                                                'text-muted-foreground',
                                            )}
                                            data-label={t('Country of origin')}
                                        >
                                            {product.country_of_origin_label ??
                                                '—'}
                                        </TableCell>
                                        <TableCell
                                            className="text-muted-foreground px-4 text-sm whitespace-nowrap"
                                            data-label={t('Updated')}
                                            data-test="product-updated"
                                        >
                                            {formatRelative(
                                                product.updated_at,
                                            ) ?? '—'}
                                        </TableCell>
                                        <TableCell className="px-4 md:pr-6">
                                            <ProductActions
                                                product={product}
                                                organizationSlug={
                                                    organizationSlug
                                                }
                                                canUpdate={
                                                    permissions.canUpdateProduct
                                                }
                                                canDelete={
                                                    permissions.canDeleteProduct
                                                }
                                                onDelete={confirmDelete}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="flex flex-col items-center justify-between gap-3 border-t px-4 py-3 text-sm sm:flex-row">
                            <div className="text-muted-foreground flex flex-wrap items-center justify-center gap-3">
                                <p data-test="product-count">
                                    {tn('Showing :range of :total products', {
                                        range: (
                                            <span className="text-foreground font-medium">
                                                {products.from}–{products.to}
                                            </span>
                                        ),
                                        total: (
                                            <span className="text-foreground font-medium">
                                                {products.total}
                                            </span>
                                        ),
                                    })}
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
                                        aria-label={t('Products per page')}
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
                                                {t(':size / page', { size })}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {products.last_page > 1 ? (
                                <nav
                                    className="flex flex-wrap items-center justify-center gap-1"
                                    aria-label={t('Pagination')}
                                >
                                    <PaginationArrow
                                        href={products.prev_page_url}
                                        label={t('Previous page')}
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
                                        label={t('Next page')}
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
                                {t('No products match these filters')}
                            </h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {filters.search !== null
                                    ? t(
                                          'Try a different name, barcode or article number, or clear the filters.',
                                      )
                                    : t(
                                          'Nothing matches the filters you have set. Try clearing one.',
                                      )}
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                        <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <Package className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">
                                {t('No products yet')}
                            </h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {isSupplier
                                    ? t(
                                          'Products a distributor assigns to you will show up here.',
                                      )
                                    : canAddProducts
                                      ? t(
                                            'Add your first product to start tracking it.',
                                        )
                                      : needsSupplier
                                        ? t(
                                              'Invite a supplier first — every product is assigned to one.',
                                          )
                                        : needsTemplate
                                          ? t(
                                                'Add a template to one of your categories first — every product is held to one.',
                                            )
                                          : t(
                                                'Products added to this organization will show up here.',
                                            )}
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

/**
 * The review states as tabs over the list, each with how many products
 * it holds under whatever else is filtered.
 *
 * Links rather than buttons: the status lives in the address with the rest
 * of the filters, so a tab can be opened in a new window or bookmarked.
 */
function StatusTabs({
    organizationSlug,
    active,
    counts,
    statuses,
}: {
    organizationSlug: string;
    active: ProductReviewStatus | null;
    counts: Record<ProductReviewStatus, number>;
    statuses: ProductReviewStatusOption[];
}) {
    const total = STATUS_ORDER.reduce(
        (sum, status) => sum + (counts[status] ?? 0),
        0,
    );

    const tabs: Array<{
        key: string;
        status: ProductReviewStatus | null;
        label: string;
        count: number;
    }> = [
        { key: 'all', status: null, label: t('All'), count: total },
        ...STATUS_ORDER.map((status) => ({
            key: status,
            status,
            label:
                statuses.find((option) => option.value === status)?.label ??
                status,
            count: counts[status] ?? 0,
        })),
    ];

    return (
        <nav
            aria-label={t('Filter by review status')}
            className="-mb-2 flex flex-wrap gap-x-6 shadow-[inset_0_-1px_0_var(--border)]"
        >
            {tabs.map((tab) => {
                const isActive = tab.status === active;

                return (
                    <Link
                        key={tab.key}
                        href={productsIndex(organizationSlug, {
                            mergeQuery: {
                                status: tab.status ?? undefined,
                                page: undefined,
                            },
                        })}
                        only={[
                            'products',
                            'filters',
                            'hasProducts',
                            'statusCounts',
                        ]}
                        preserveState
                        preserveScroll
                        replace
                        aria-current={isActive ? 'page' : undefined}
                        data-test={`product-status-tab-${tab.key}`}
                        className={cn(
                            'focus-visible:ring-ring inline-flex shrink-0 items-center gap-2 border-b-2 px-0.5 py-2.5 text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-2',
                            isActive
                                ? 'border-foreground text-foreground'
                                : 'text-muted-foreground hover:text-foreground border-transparent',
                        )}
                    >
                        {tab.label}
                        <span
                            className={cn(
                                'rounded-full px-1.5 text-xs tabular-nums',
                                tab.status === 'changes_requested' &&
                                    tab.count > 0
                                    ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                                    : 'bg-muted-foreground/15 text-muted-foreground',
                            )}
                        >
                            {tab.count}
                        </span>
                    </Link>
                );
            })}
        </nav>
    );
}

/**
 * What can be done to one product from the list, gathered behind one
 * button: opening it is what the name is for, and deleting it is rare
 * enough that it should not sit one click away on every row.
 */
function ProductActions({
    product,
    organizationSlug,
    canUpdate,
    canDelete,
    onDelete,
}: {
    product: Product;
    organizationSlug: string;
    canUpdate: boolean;
    canDelete: boolean;
    onDelete: (product: Product) => void;
}) {
    return (
        <div className="flex justify-end">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        data-test="product-actions"
                        aria-label={t('Actions for :name', {
                            name: product.name,
                        })}
                    >
                        <MoreHorizontal className="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    <DropdownMenuItem asChild>
                        <Link
                            href={edit([organizationSlug, product.id])}
                            data-test="product-edit-button"
                        >
                            <Pencil className="h-4 w-4" />
                            {canUpdate ? t('Edit product') : t('View product')}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <a
                            href={product.public_url}
                            target="_blank"
                            rel="noreferrer"
                            data-test="product-public-page-link"
                        >
                            <ExternalLink className="h-4 w-4" />
                            {t('Open public page')}
                        </a>
                    </DropdownMenuItem>
                    {canDelete ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                data-test="product-delete-button"
                                onSelect={() => onDelete(product)}
                            >
                                <Trash2 className="h-4 w-4" />
                                {t('Delete product…')}
                            </DropdownMenuItem>
                        </>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
