import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Copyright,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    SearchX,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import CreateBrandModal from '@/components/create-brand-modal';
import DeleteBrandModal from '@/components/delete-brand-modal';
import RenameBrandModal from '@/components/rename-brand-modal';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { t, tc } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as distributorsIndex } from '@/routes/distributors';
import { index as productsIndex } from '@/routes/products';
import { index as suppliersIndex } from '@/routes/suppliers';
import type {
    Brand,
    BrandConnection,
    BrandPermissions,
    OrganizationType,
} from '@/types';

type Props = {
    connections: BrandConnection[];
    permissions: BrandPermissions;
    viewerType: OrganizationType;
};

const ALL = 'all';

/**
 * The trade a filter link asked for, by id. The suppliers page links here
 * with ?supplier=, so the list opens already narrowed to that supplier.
 */
function connectionFromUrl(url: string): string {
    const requested = new URL(url, 'http://localhost').searchParams.get(
        'supplier',
    );

    return requested !== null && /^\d+$/.test(requested) ? requested : ALL;
}

/**
 * The makers behind each trade, as one list.
 *
 * Every brand is filed under the trade that carries it, so the trade is a
 * column rather than a heading: two suppliers' brands of the same name are
 * then two plainly different rows, and the list can be searched and
 * narrowed instead of read one supplier at a time.
 */
export default function BrandsIndex({
    connections,
    permissions,
    viewerType,
}: Props) {
    const page = usePage();
    const organizationSlug = page.props.currentOrganization?.slug ?? '';

    const isSupplier = viewerType === 'supplier';
    const counterpartyLabel = isSupplier ? t('Distributor') : t('Supplier');

    const [search, setSearch] = useState('');
    const [connectionFilter, setConnectionFilter] = useState(() => {
        const requested = connectionFromUrl(page.url);

        return connections.some(
            (connection) => String(connection.id) === requested,
        )
            ? requested
            : ALL;
    });

    /**
     * Keep the filter in the address without a request, so a reload, a
     * shared link, or the redirect after a rename comes back to the same
     * supplier rather than to all of them.
     */
    useEffect(() => {
        if (connectionFromUrl(window.location.href) === connectionFilter) {
            return;
        }

        const url = new URL(window.location.href);

        if (connectionFilter === ALL) {
            url.searchParams.delete('supplier');
        } else {
            url.searchParams.set('supplier', connectionFilter);
        }

        router.replace({
            url: url.pathname + url.search,
            preserveScroll: true,
            preserveState: true,
        });
    }, [connectionFilter, page.url]);

    const [renameOpen, setRenameOpen] = useState(false);
    const [brandToRename, setBrandToRename] = useState<Brand | null>(null);

    const [deleteOpen, setDeleteOpen] = useState(false);
    const [brandToDelete, setBrandToDelete] = useState<Brand | null>(null);

    const [createOpen, setCreateOpen] = useState(false);
    const [createUnder, setCreateUnder] = useState<BrandConnection | null>(
        null,
    );

    const addBrand = (connection: BrandConnection) => {
        setCreateUnder(connection);
        setCreateOpen(true);
    };

    const renameBrand = (brand: Brand) => {
        setBrandToRename(brand);
        setRenameOpen(true);
    };

    const confirmDelete = (brand: Brand) => {
        setBrandToDelete(brand);
        setDeleteOpen(true);
    };

    const term = search.trim().toLocaleLowerCase();

    const rows = connections
        .filter(
            (connection) =>
                connectionFilter === ALL ||
                String(connection.id) === connectionFilter,
        )
        .flatMap((connection) =>
            connection.brands.map((brand) => ({ brand, connection })),
        )
        .filter(
            ({ brand }) =>
                term === '' || brand.name.toLocaleLowerCase().includes(term),
        )
        .sort(
            (a, b) =>
                a.brand.name.localeCompare(b.brand.name) ||
                a.connection.label.localeCompare(b.connection.label),
        );

    const brandCount = connections.reduce(
        (sum, connection) => sum + connection.brands.length,
        0,
    );

    /**
     * Where a new brand can go: the trade being looked at, when the list
     * is narrowed to one that takes brands, else a choice of them.
     */
    const addable = connections.filter((connection) => connection.canAddBrand);
    const filteredConnection =
        addable.find(
            (connection) => String(connection.id) === connectionFilter,
        ) ?? null;

    return (
        <>
            <Head title={t('Brands')} />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <h1 className="page-title">{t('Brands')}</h1>
                        <p className="text-muted-foreground text-sm">
                            {isSupplier
                                ? t(
                                      'The makers behind each trade. A brand belongs to the distributor it is sold to.',
                                  )
                                : t(
                                      'The makers behind each trade. A brand belongs to the supplier that carries it.',
                                  )}
                        </p>
                    </div>

                    {addable.length > 0 ? (
                        filteredConnection !== null || addable.length === 1 ? (
                            <Button
                                data-test="brand-add-button"
                                onClick={() =>
                                    addBrand(filteredConnection ?? addable[0])
                                }
                            >
                                <Plus /> {t('Add brand')}
                            </Button>
                        ) : (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button data-test="brand-add-button">
                                        <Plus /> {t('Add brand')}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-64"
                                >
                                    <DropdownMenuLabel className="text-muted-foreground text-xs">
                                        {isSupplier
                                            ? t('For which distributor?')
                                            : t('For which supplier?')}
                                    </DropdownMenuLabel>
                                    {addable.map((connection) => (
                                        <DropdownMenuItem
                                            key={connection.id}
                                            data-test="brand-add-for"
                                            onSelect={() =>
                                                addBrand(connection)
                                            }
                                        >
                                            {connection.label}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )
                    ) : null}
                </div>

                {connections.length > 0 ? (
                    <>
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="relative min-w-56 flex-1 sm:max-w-xs">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    type="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    aria-label={t('Search brands')}
                                    placeholder={t('Search brands')}
                                    className="pl-9"
                                    data-test="brand-search"
                                />
                            </div>

                            <Select
                                value={connectionFilter}
                                onValueChange={setConnectionFilter}
                            >
                                <SelectTrigger
                                    className="w-full sm:w-64"
                                    aria-label={
                                        isSupplier
                                            ? t('Filter by distributor')
                                            : t('Filter by supplier')
                                    }
                                    data-test="brand-connection-filter"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        {isSupplier
                                            ? t('All distributors')
                                            : t('All suppliers')}
                                    </SelectItem>
                                    {connections.map((connection) => (
                                        <SelectItem
                                            key={connection.id}
                                            value={String(connection.id)}
                                        >
                                            {connection.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <p className="text-muted-foreground text-sm sm:ml-auto">
                                {tc('1 brand|:count brands', brandCount)}
                            </p>
                        </div>

                        {rows.length > 0 ? (
                            <div className="workspace-table">
                                <div className="min-w-0 overflow-x-auto">
                                    <table className="w-full text-left text-sm md:min-w-xl">
                                        <thead>
                                            <tr className="text-muted-foreground">
                                                <th className="px-6 font-medium">
                                                    {t('Brand')}
                                                </th>
                                                <th className="px-6 font-medium">
                                                    {counterpartyLabel}
                                                </th>
                                                <th className="px-6 font-medium">
                                                    {t('Products')}
                                                </th>
                                                <th className="px-6 font-medium">
                                                    <span className="sr-only">
                                                        {t('Actions')}
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map(
                                                ({ brand, connection }) => (
                                                    <BrandRow
                                                        key={brand.id}
                                                        brand={brand}
                                                        connection={connection}
                                                        counterpartyLabel={
                                                            counterpartyLabel
                                                        }
                                                        organizationSlug={
                                                            organizationSlug
                                                        }
                                                        permissions={
                                                            permissions
                                                        }
                                                        onRename={renameBrand}
                                                        onDelete={confirmDelete}
                                                    />
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ) : (
                            <div
                                className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-12 text-center"
                                data-test="brands-empty"
                            >
                                <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                                    {brandCount === 0 ? (
                                        <Copyright className="text-muted-foreground size-6" />
                                    ) : (
                                        <SearchX className="text-muted-foreground size-6" />
                                    )}
                                </div>
                                <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                    {brandCount === 0
                                        ? t(
                                              'No brands yet. Add one to name the maker behind a supplier’s products.',
                                          )
                                        : t('No brands match.')}
                                </p>
                            </div>
                        )}
                    </>
                ) : (
                    <div className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                        <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <Copyright className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">
                                {isSupplier
                                    ? t('No distributors yet')
                                    : t('No suppliers yet')}
                            </h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {isSupplier
                                    ? t(
                                          'A brand belongs to a trade, so there is nowhere to file one until you have distributors.',
                                      )
                                    : t(
                                          'A brand belongs to a trade, so there is nowhere to file one until you have suppliers.',
                                      )}
                            </p>
                        </div>
                        <Button variant="outline" asChild>
                            <Link
                                href={
                                    isSupplier
                                        ? distributorsIndex(organizationSlug)
                                        : suppliersIndex(organizationSlug)
                                }
                                data-test="brands-connections-link"
                            >
                                {isSupplier
                                    ? t('View distributors')
                                    : t('View suppliers')}
                            </Link>
                        </Button>
                    </div>
                )}
            </div>

            {createUnder !== null ? (
                <CreateBrandModal
                    organizationSlug={organizationSlug}
                    supplierConnectionId={createUnder.id}
                    supplierLabel={createUnder.label}
                    reloadOnly={['connections']}
                    open={createOpen}
                    onOpenChange={setCreateOpen}
                />
            ) : null}

            <RenameBrandModal
                organizationSlug={organizationSlug}
                brand={brandToRename}
                open={renameOpen}
                onOpenChange={setRenameOpen}
            />

            <DeleteBrandModal
                organizationSlug={organizationSlug}
                brand={brandToDelete}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

function BrandRow({
    brand,
    connection,
    counterpartyLabel,
    organizationSlug,
    permissions,
    onRename,
    onDelete,
}: {
    brand: Brand;
    connection: BrandConnection;
    counterpartyLabel: string;
    organizationSlug: string;
    permissions: BrandPermissions;
    onRename: (brand: Brand) => void;
    onDelete: (brand: Brand) => void;
}) {
    const canAct = permissions.canUpdateBrand || permissions.canDeleteBrand;

    return (
        <tr className="border-t" data-test="brand-row">
            <td className="px-6 font-medium break-words">{brand.name}</td>
            <td className="px-6" data-label={counterpartyLabel}>
                <span className="inline-flex flex-wrap items-center justify-end gap-2 md:justify-start">
                    <span data-test="brand-connection-name">
                        {connection.label}
                    </span>
                    {connection.status !== 'active' ? (
                        <span
                            className={cn(
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                connection.isExpired
                                    ? 'bg-red-500/10 text-red-700 dark:text-red-400'
                                    : connection.status === 'pending'
                                      ? 'bg-sky-500/10 text-sky-700 dark:text-sky-400'
                                      : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {connection.statusLabel}
                        </span>
                    ) : null}
                </span>
            </td>
            <td className="px-6" data-label={t('Products')}>
                {brand.products_count > 0 ? (
                    <Link
                        href={
                            productsIndex(organizationSlug, {
                                query: { brand: brand.id },
                            }).url
                        }
                        className="underline-offset-4 hover:underline"
                        data-test="brand-products-count"
                    >
                        {tc('1 product|:count products', brand.products_count)}
                    </Link>
                ) : (
                    <span
                        className="text-muted-foreground"
                        data-test="brand-products-count"
                    >
                        {t('None yet')}
                    </span>
                )}
            </td>
            <td className="px-6">
                {canAct ? (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    data-test="brand-actions"
                                    aria-label={t('Actions for :name', {
                                        name: brand.name,
                                    })}
                                >
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-44">
                                {permissions.canUpdateBrand ? (
                                    <DropdownMenuItem
                                        data-test="brand-edit-button"
                                        onSelect={() => onRename(brand)}
                                    >
                                        <Pencil className="h-4 w-4" />
                                        {t('Rename brand')}
                                    </DropdownMenuItem>
                                ) : null}
                                {permissions.canDeleteBrand ? (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        data-test="brand-delete-button"
                                        onSelect={() => onDelete(brand)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        {t('Delete brand…')}
                                    </DropdownMenuItem>
                                ) : null}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                ) : null}
            </td>
        </tr>
    );
}
