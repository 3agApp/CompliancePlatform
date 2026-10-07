import { Head, Link, usePage } from '@inertiajs/react';
import { Copyright, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CreateBrandModal from '@/components/create-brand-modal';
import DeleteBrandModal from '@/components/delete-brand-modal';
import RenameBrandModal from '@/components/rename-brand-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { t, tc } from '@/lib/i18n';
import { index as distributorsIndex } from '@/routes/distributors';
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

export default function BrandsIndex({
    connections,
    permissions,
    viewerType,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const isSupplier = viewerType === 'supplier';

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

    return (
        <>
            <Head title={t('Brands')} />

            <div className="workspace-page">
                <div className="page-heading">
                    <h1 className="page-title">{t('Brands')}</h1>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'The makers behind each trade. A brand belongs to the supplier that carries it, so it is filed under them and shows up on every product they supply.',
                        )}
                    </p>
                </div>

                {connections.length > 0 ? (
                    <div className="grid gap-4">
                        {connections.map((connection) => (
                            <section
                                key={connection.id}
                                data-test="brand-connection"
                                className="workspace-panel grid gap-4 p-6"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="grid gap-1">
                                        <h2
                                            className="font-medium"
                                            data-test="brand-connection-name"
                                        >
                                            {connection.label}
                                            {!isSupplier &&
                                            connection.status !== 'active' ? (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    {connection.statusLabel}
                                                </Badge>
                                            ) : null}
                                        </h2>
                                        <p className="text-muted-foreground text-xs">
                                            {connection.brands.length === 0
                                                ? t('No brands yet.')
                                                : tc(
                                                      '1 brand|:count brands',
                                                      connection.brands.length,
                                                  )}
                                        </p>
                                    </div>

                                    {connection.canAddBrand ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            data-test="brand-add-button"
                                            onClick={() => addBrand(connection)}
                                        >
                                            <Plus className="size-4" />{' '}
                                            {t('Add brand')}
                                        </Button>
                                    ) : null}
                                </div>

                                {connection.brands.length > 0 ? (
                                    <ul className="grid gap-2">
                                        {connection.brands.map((brand) => (
                                            <li
                                                key={brand.id}
                                                data-test="brand-row"
                                                className="bg-muted/30 flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3"
                                            >
                                                <span className="min-w-0 text-sm font-medium break-words">
                                                    {brand.name}
                                                </span>

                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className="text-muted-foreground text-xs"
                                                        data-test="brand-products-count"
                                                    >
                                                        {tc(
                                                            '1 product|:count products',
                                                            brand.products_count,
                                                        )}
                                                    </span>

                                                    {permissions.canUpdateBrand ? (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    data-test="brand-edit-button"
                                                                    onClick={() =>
                                                                        renameBrand(
                                                                            brand,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        {t(
                                                                            'Rename brand',
                                                                        )}
                                                                    </span>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>
                                                                    {t(
                                                                        'Rename brand',
                                                                    )}
                                                                </p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ) : null}

                                                    {permissions.canDeleteBrand ? (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    data-test="brand-delete-button"
                                                                    onClick={() =>
                                                                        confirmDelete(
                                                                            brand,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        {t(
                                                                            'Delete brand',
                                                                        )}
                                                                    </span>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>
                                                                    {t(
                                                                        'Delete brand',
                                                                    )}
                                                                </p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ) : null}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                            </section>
                        ))}
                    </div>
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
