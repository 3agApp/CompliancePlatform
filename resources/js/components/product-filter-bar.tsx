import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { t } from '@/lib/i18n';
import { index as productsIndex } from '@/routes/products';
import type {
    BrandOption,
    ProductCategoryOption,
    ProductCounterparty,
    ProductFilters,
    ProductReviewStatusOption,
} from '@/types';

/**
 * Radix refuses an empty string as an item value, so "no filter" travels as a
 * sentinel here and leaves as an absent query parameter.
 */
const NO_FILTER = 'all';

/**
 * Only the list and the state describing it are re-fetched. The permissions,
 * the countries, the categories and the assignable connections cannot change
 * while the page is open.
 */
const ONLY = ['products', 'filters', 'hasProducts'];

type Query = {
    connection?: string;
    category?: string;
    brand?: string;
    status?: string;
    search?: string;
    per_page?: string;
};

type Props = {
    organizationSlug: string;
    filters: ProductFilters;
    counterparties: ProductCounterparty[];
    /** Which side of the trade the counterparties are on. */
    counterpartyKind: 'supplier' | 'distributor';
    filterableCategories: ProductCategoryOption[];
    filterableBrands: BrandOption[];
    availableStatuses: ProductReviewStatusOption[];
};

export default function ProductFilterBar({
    organizationSlug,
    filters,
    counterparties,
    counterpartyKind,
    filterableCategories,
    filterableBrands,
    availableStatuses,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const debouncedSearch = useDebouncedValue(search);

    /**
     * The URL is the filter state, so every change is a visit rather than
     * local state. mergeQuery keeps the two controls independent: changing
     * one leaves whatever the other put in the query string alone.
     *
     * The page is the exception: narrowing the list while deep in it would
     * otherwise land on a page the shorter list no longer has, so every
     * change starts again from the first one.
     */
    const visit = (query: Query) => {
        router.get(
            productsIndex(organizationSlug, {
                mergeQuery: { ...query, page: undefined },
            }),
            {},
            {
                only: ONLY,
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    /**
     * The server value is the source of truth, so a visit only fires when the
     * settled input disagrees with it. That skips the pointless request on
     * mount and converges rather than looping once our own response lands.
     */
    useEffect(() => {
        const term = debouncedSearch.trim();

        if (term === (filters.search ?? '')) {
            return;
        }

        visit({ search: term === '' ? undefined : term });
    }, [debouncedSearch, filters.search]);

    const clear = () => {
        setSearch('');
        visit({
            connection: undefined,
            category: undefined,
            brand: undefined,
            status: undefined,
            search: undefined,
        });
    };

    const isSupplierFilter = counterpartyKind === 'supplier';

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="relative min-w-0 flex-1 sm:max-w-xs">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    data-test="product-filter-search"
                    aria-label={t(
                        'Search products by name, barcode or article number',
                    )}
                    placeholder={t('Search name, barcode or article no.')}
                    className="pl-9"
                />
            </div>

            {counterparties.length > 0 ? (
                <Select
                    value={
                        filters.connection === null
                            ? NO_FILTER
                            : String(filters.connection)
                    }
                    onValueChange={(value) =>
                        visit({
                            connection: value === NO_FILTER ? undefined : value,
                        })
                    }
                >
                    <SelectTrigger
                        data-test="product-filter-connection"
                        aria-label={
                            isSupplierFilter
                                ? t('Filter by supplier')
                                : t('Filter by distributor')
                        }
                        className="w-full sm:w-56"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_FILTER}>
                            {isSupplierFilter
                                ? t('All suppliers')
                                : t('All distributors')}
                        </SelectItem>
                        {counterparties.map((counterparty) => (
                            <SelectItem
                                key={counterparty.id}
                                value={String(counterparty.id)}
                            >
                                {counterparty.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : null}

            {filterableCategories.length > 0 ? (
                <Select
                    value={
                        filters.category === null
                            ? NO_FILTER
                            : String(filters.category)
                    }
                    onValueChange={(value) =>
                        visit({
                            category: value === NO_FILTER ? undefined : value,
                        })
                    }
                >
                    <SelectTrigger
                        data-test="product-filter-category"
                        aria-label={t('Filter by category')}
                        className="w-full sm:w-56"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_FILTER}>
                            {t('All categories')}
                        </SelectItem>
                        {filterableCategories.map((category) => (
                            <SelectItem
                                key={category.id}
                                value={String(category.id)}
                            >
                                {category.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : null}

            {filterableBrands.length > 0 ? (
                <Select
                    value={
                        filters.brand === null
                            ? NO_FILTER
                            : String(filters.brand)
                    }
                    onValueChange={(value) =>
                        visit({
                            brand: value === NO_FILTER ? undefined : value,
                        })
                    }
                >
                    <SelectTrigger
                        data-test="product-filter-brand"
                        aria-label={t('Filter by brand')}
                        className="w-full sm:w-56"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_FILTER}>
                            {t('All brands')}
                        </SelectItem>
                        {filterableBrands.map((brand) => (
                            <SelectItem key={brand.id} value={String(brand.id)}>
                                {brand.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : null}

            {/*
             * Whose move it is, which is the filter a reviewer reaches for
             * first: a distributor opening the catalogue is usually looking
             * for what is waiting on them.
             */}
            <Select
                value={filters.status === null ? NO_FILTER : filters.status}
                onValueChange={(value) =>
                    visit({ status: value === NO_FILTER ? undefined : value })
                }
            >
                <SelectTrigger
                    data-test="product-filter-status"
                    aria-label={t('Filter by review status')}
                    className="w-full sm:w-48"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NO_FILTER}>{t('Any status')}</SelectItem>
                    {availableStatuses.map((status) => (
                        <SelectItem key={status.value} value={status.value}>
                            {status.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {filters.connection !== null ||
            filters.category !== null ||
            filters.brand !== null ||
            filters.status !== null ||
            filters.search !== null ? (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={clear}
                    data-test="product-filter-clear"
                >
                    <X className="h-4 w-4" /> {t('Clear')}
                </Button>
            ) : null}
        </div>
    );
}
