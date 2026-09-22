import { Head, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Pencil,
    Plus,
    Tags,
    Trash2,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import DeleteCategoryModal from '@/components/delete-category-modal';
import DeleteTemplateModal from '@/components/delete-template-modal';
import SaveCategoryModal from '@/components/save-category-modal';
import SaveTemplateModal from '@/components/save-template-modal';
import TemplateRequirementSummary from '@/components/template-requirement-summary';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type {
    ProductCategory,
    ProductCategoryPermissions,
    ProductRequirementOption,
    ProductTemplate,
} from '@/types';

type Props = {
    categories: ProductCategory[];
    permissions: ProductCategoryPermissions;
    availableRequirements: ProductRequirementOption[];
};

export default function CategoriesIndex({
    categories,
    permissions,
    availableRequirements,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const [saveDialogOpen, setSaveDialogOpen] = useState(false);
    const [categoryToEdit, setCategoryToEdit] = useState<
        ProductCategory | undefined
    >(undefined);

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [categoryToDelete, setCategoryToDelete] =
        useState<ProductCategory | null>(null);

    const [templateDialogOpen, setTemplateDialogOpen] = useState(false);
    const [templateCategory, setTemplateCategory] =
        useState<ProductCategory | null>(null);
    const [templateToEdit, setTemplateToEdit] = useState<
        ProductTemplate | undefined
    >(undefined);

    const [deleteTemplateOpen, setDeleteTemplateOpen] = useState(false);
    const [templateToDelete, setTemplateToDelete] =
        useState<ProductTemplate | null>(null);

    /**
     * Every row starts open. A distributor keeps a handful of families and
     * this page is where the templates under them are managed, so folding
     * them away by default would hide most of what the screen is for --
     * including a category with no templates at all, which cannot take a
     * product yet. The chevron is for tidying up, not for finding things.
     */
    const [collapsed, setCollapsed] = useState<number[]>([]);

    const toggle = (categoryId: number) => {
        setCollapsed((closed) =>
            closed.includes(categoryId)
                ? closed.filter((id) => id !== categoryId)
                : [...closed, categoryId],
        );
    };

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

    const addTemplate = (category: ProductCategory) => {
        setTemplateCategory(category);
        setTemplateToEdit(undefined);
        setTemplateDialogOpen(true);
    };

    const editTemplate = (
        category: ProductCategory,
        template: ProductTemplate,
    ) => {
        setTemplateCategory(category);
        setTemplateToEdit(template);
        setTemplateDialogOpen(true);
    };

    const confirmDeleteTemplate = (
        category: ProductCategory,
        template: ProductTemplate,
    ) => {
        setTemplateCategory(category);
        setTemplateToDelete(template);
        setDeleteTemplateOpen(true);
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
                            its products under, and the templates that say what
                            each kind of product needs.
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
                            <table className="w-full text-left text-sm md:min-w-md">
                                <thead>
                                    <tr className="text-muted-foreground">
                                        <th className="px-6 font-medium">
                                            Name
                                        </th>
                                        <th className="px-6 font-medium">
                                            Templates
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
                                    {categories.map((category) => {
                                        const isOpen = !collapsed.includes(
                                            category.id,
                                        );

                                        return (
                                            <Fragment key={category.id}>
                                                <tr
                                                    data-test="category-row"
                                                    className="border-t"
                                                >
                                                    {/*
                                                     * The name stays a text node of the cell rather
                                                     * than being wrapped alongside the chevron: it is
                                                     * how a row is identified, by a reader and by the
                                                     * browser tests alike.
                                                     */}
                                                    <td className="px-6 align-middle font-medium break-words">
                                                        <button
                                                            type="button"
                                                            className="text-muted-foreground hover:text-foreground mr-1 -ml-1 inline-flex p-1 align-middle"
                                                            data-test="category-toggle"
                                                            aria-expanded={
                                                                isOpen
                                                            }
                                                            aria-label={`${isOpen ? 'Hide' : 'Show'} the templates under ${category.name}`}
                                                            onClick={() =>
                                                                toggle(
                                                                    category.id,
                                                                )
                                                            }
                                                        >
                                                            {isOpen ? (
                                                                <ChevronDown className="size-4" />
                                                            ) : (
                                                                <ChevronRight className="size-4" />
                                                            )}
                                                        </button>
                                                        {category.name}
                                                    </td>
                                                    <td
                                                        className="text-muted-foreground px-6"
                                                        data-test="category-templates-count"
                                                        data-label="Templates"
                                                    >
                                                        {category.templates
                                                            .length > 0
                                                            ? category.templates
                                                                  .length
                                                            : 'None yet'}
                                                    </td>
                                                    <td
                                                        className="text-muted-foreground px-6"
                                                        data-test="category-products-count"
                                                        data-label="Products"
                                                    >
                                                        {category.products_count >
                                                        0
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

                                                {isOpen ? (
                                                    <tr
                                                        className="border-t"
                                                        data-test="category-templates"
                                                    >
                                                        <td
                                                            colSpan={4}
                                                            className="bg-muted/30 px-6"
                                                        >
                                                            <TemplateList
                                                                category={
                                                                    category
                                                                }
                                                                availableRequirements={
                                                                    availableRequirements
                                                                }
                                                                canManage={
                                                                    permissions.canUpdateCategory
                                                                }
                                                                onAdd={() =>
                                                                    addTemplate(
                                                                        category,
                                                                    )
                                                                }
                                                                onEdit={(
                                                                    template,
                                                                ) =>
                                                                    editTemplate(
                                                                        category,
                                                                        template,
                                                                    )
                                                                }
                                                                onDelete={(
                                                                    template,
                                                                ) =>
                                                                    confirmDeleteTemplate(
                                                                        category,
                                                                        template,
                                                                    )
                                                                }
                                                            />
                                                        </td>
                                                    </tr>
                                                ) : null}
                                            </Fragment>
                                        );
                                    })}
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

            <SaveTemplateModal
                organizationSlug={organizationSlug}
                category={templateCategory}
                template={templateToEdit}
                availableRequirements={availableRequirements}
                open={templateDialogOpen}
                onOpenChange={setTemplateDialogOpen}
            />

            <DeleteTemplateModal
                organizationSlug={organizationSlug}
                category={templateCategory}
                template={templateToDelete}
                open={deleteTemplateOpen}
                onOpenChange={setDeleteTemplateOpen}
            />
        </>
    );
}

function TemplateList({
    category,
    availableRequirements,
    canManage,
    onAdd,
    onEdit,
    onDelete,
}: {
    category: ProductCategory;
    availableRequirements: ProductRequirementOption[];
    canManage: boolean;
    onAdd: () => void;
    onEdit: (template: ProductTemplate) => void;
    onDelete: (template: ProductTemplate) => void;
}) {
    return (
        <div className="grid gap-3 py-4">
            {category.templates.length === 0 ? (
                <p
                    className="text-muted-foreground text-sm"
                    data-test="category-templates-empty"
                >
                    No templates yet. A product must be held to one, so nothing
                    can be filed under {category.name} until you add a template.
                </p>
            ) : (
                <ul className="grid gap-2">
                    {category.templates.map((template) => (
                        <li
                            key={template.id}
                            data-test="template-row"
                            className="bg-card flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3"
                        >
                            <div className="grid min-w-0 gap-0.5">
                                <span className="text-sm font-medium break-words">
                                    {template.name}
                                </span>
                                <TemplateRequirementSummary
                                    requirements={template.requirements}
                                    availableRequirements={
                                        availableRequirements
                                    }
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <span
                                    className="text-muted-foreground text-xs"
                                    data-test="template-products-count"
                                >
                                    {template.products_count === 1
                                        ? '1 product'
                                        : `${template.products_count} products`}
                                </span>

                                {canManage ? (
                                    <>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            data-test="template-edit-button"
                                            onClick={() => onEdit(template)}
                                        >
                                            <Pencil className="h-4 w-4" />
                                            <span className="sr-only">
                                                Edit template
                                            </span>
                                        </Button>

                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            data-test="template-delete-button"
                                            onClick={() => onDelete(template)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                            <span className="sr-only">
                                                Delete template
                                            </span>
                                        </Button>
                                    </>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {canManage ? (
                <div>
                    <Button
                        variant="outline"
                        size="sm"
                        data-test="category-new-template-button"
                        onClick={onAdd}
                    >
                        <Plus className="h-4 w-4" /> Add template
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
