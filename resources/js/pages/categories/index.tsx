import { Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, Tags, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DeleteCategoryModal from '@/components/delete-category-modal';
import SaveCategoryModal from '@/components/save-category-modal';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { ProductCategory, ProductCategoryPermissions } from '@/types';

type Props = {
    categories: ProductCategory[];
    permissions: ProductCategoryPermissions;
};

export default function CategoriesIndex({ categories, permissions }: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const [saveDialogOpen, setSaveDialogOpen] = useState(false);
    const [categoryToEdit, setCategoryToEdit] = useState<
        ProductCategory | undefined
    >(undefined);

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [categoryToDelete, setCategoryToDelete] =
        useState<ProductCategory | null>(null);

    const addCategory = () => {
        setCategoryToEdit(undefined);
        setSaveDialogOpen(true);
    };

    const editCategory = (category: ProductCategory) => {
        setCategoryToEdit(category);
        setSaveDialogOpen(true);
    };

    const confirmDelete = (category: ProductCategory) => {
        setCategoryToDelete(category);
        setDeleteDialogOpen(true);
    };

    return (
        <>
            <Head title="Categories" />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <h1 className="page-title">Categories</h1>
                        <p className="text-muted-foreground text-sm">
                            The legal families {currentOrganization?.name} files
                            its products under.
                        </p>
                    </div>

                    {permissions.canCreateCategory ? (
                        <Button
                            data-test="categories-new-category-button"
                            onClick={addCategory}
                        >
                            <Plus /> New category
                        </Button>
                    ) : null}
                </div>

                {categories.length > 0 ? (
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
                                    {categories.map((category) => (
                                        <tr
                                            key={category.id}
                                            data-test="category-row"
                                            className="border-t"
                                        >
                                            <td className="px-6 font-medium break-words">
                                                {category.name}
                                            </td>
                                            <td
                                                className="text-muted-foreground px-6"
                                                data-test="category-products-count"
                                            >
                                                {category.products_count > 0
                                                    ? category.products_count
                                                    : '—'}
                                            </td>
                                            <td className="px-6">
                                                <div className="flex items-center justify-end gap-2">
                                                    {permissions.canUpdateCategory ? (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    data-test="category-edit-button"
                                                                    onClick={() =>
                                                                        editCategory(
                                                                            category,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Rename
                                                                        category
                                                                    </span>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>
                                                                    Rename
                                                                    category
                                                                </p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ) : null}

                                                    {permissions.canDeleteCategory ? (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    data-test="category-delete-button"
                                                                    onClick={() =>
                                                                        confirmDelete(
                                                                            category,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Delete
                                                                        category
                                                                    </span>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>
                                                                    Delete
                                                                    category
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
                            <Tags className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">No categories yet</h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {permissions.canCreateCategory
                                    ? 'Add the legal families your products fall under, such as toy or magnetic toy.'
                                    : 'Categories added to this organization will show up here.'}
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <SaveCategoryModal
                organizationSlug={organizationSlug}
                category={categoryToEdit}
                open={saveDialogOpen}
                onOpenChange={setSaveDialogOpen}
            />

            <DeleteCategoryModal
                organizationSlug={organizationSlug}
                category={categoryToDelete}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}
