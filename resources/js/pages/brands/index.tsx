import { Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, Copyright, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DeleteBrandModal from '@/components/delete-brand-modal';
import SaveBrandModal from '@/components/save-brand-modal';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { Brand, BrandPermissions } from '@/types';

type Props = {
    brands: Brand[];
    permissions: BrandPermissions;
};

export default function BrandsIndex({ brands, permissions }: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const [saveDialogOpen, setSaveDialogOpen] = useState(false);
    const [brandToEdit, setBrandToEdit] = useState<Brand | undefined>(
        undefined,
    );

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [brandToDelete, setBrandToDelete] = useState<Brand | null>(null);

    const addBrand = () => {
        setBrandToEdit(undefined);
        setSaveDialogOpen(true);
    };

    const editBrand = (brand: Brand) => {
        setBrandToEdit(brand);
        setSaveDialogOpen(true);
    };

    const confirmDelete = (brand: Brand) => {
        setBrandToDelete(brand);
        setDeleteDialogOpen(true);
    };

    return (
        <>
            <Head title="Brands" />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <p className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                            Organization catalog
                        </p>
                        <h1 className="page-title">Brands</h1>
                        <p className="text-muted-foreground text-sm">
                            The makers whose products{' '}
                            {currentOrganization?.name} carries.
                        </p>
                    </div>

                    {permissions.canCreateBrand ? (
                        <Button
                            data-test="brands-new-brand-button"
                            onClick={addBrand}
                        >
                            <Plus /> New brand
                        </Button>
                    ) : null}
                </div>

                {brands.length > 0 ? (
                    <div className="workspace-table">
                        <div className="min-w-0 overflow-x-auto">
                            <table className="w-full min-w-md text-left text-sm">
                                <thead>
                                    <tr className="text-muted-foreground">
                                        <th className="px-6 font-medium">
                                            Name
                                        </th>
                                        <th className="px-6 font-medium">
                                            Products
                                        </th>
                                        <th className="px-6 font-medium">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {brands.map((brand) => (
                                        <tr
                                            key={brand.id}
                                            data-test="brand-row"
                                            className="border-t"
                                        >
                                            <td className="px-6 font-medium break-words">
                                                {brand.name}
                                            </td>
                                            <td
                                                className="text-muted-foreground px-6"
                                                data-test="brand-products-count"
                                            >
                                                {brand.products_count > 0
                                                    ? brand.products_count
                                                    : '—'}
                                            </td>
                                            <td className="px-6">
                                                <div className="flex items-center justify-end gap-2">
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
                                                                        editBrand(
                                                                            brand,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Rename
                                                                        brand
                                                                    </span>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>
                                                                    Rename brand
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
                                                                        Delete
                                                                        brand
                                                                    </span>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>
                                                                    Delete brand
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
                ) : (
                    <div className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                        <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <Copyright className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">No brands yet</h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {permissions.canCreateBrand
                                    ? 'Add the makers you carry, such as tigerbox or Magna-Tiles.'
                                    : 'Brands added to this organization will show up here.'}
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <SaveBrandModal
                organizationSlug={organizationSlug}
                brand={brandToEdit}
                open={saveDialogOpen}
                onOpenChange={setSaveDialogOpen}
            />

            <DeleteBrandModal
                organizationSlug={organizationSlug}
                brand={brandToDelete}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}
