import { Head, Link, usePage } from '@inertiajs/react';
import { Package, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CreateProductModal from '@/components/create-product-modal';
import DeleteProductModal from '@/components/delete-product-modal';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit } from '@/routes/products';
import type { CountryOption, Product, ProductPermissions } from '@/types';

type Props = {
    products: Product[];
    permissions: ProductPermissions;
    availableCountries: CountryOption[];
};

export default function ProductsIndex({
    products,
    permissions,
    availableCountries,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

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
                            Organization catalog
                        </p>
                        <h1 className="page-title">Products</h1>
                        <p className="text-muted-foreground text-sm">
                            The products {currentOrganization?.name} is
                            responsible for.
                        </p>
                    </div>

                    {permissions.canCreateProduct ? (
                        <CreateProductModal
                            organizationSlug={organizationSlug}
                            availableCountries={availableCountries}
                        >
                            <Button data-test="products-new-product-button">
                                <Plus /> New product
                            </Button>
                        </CreateProductModal>
                    ) : null}
                </div>

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
                ) : (
                    <div className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                        <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <Package className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">No products yet</h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {permissions.canCreateProduct
                                    ? 'Add your first product to start tracking it.'
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
