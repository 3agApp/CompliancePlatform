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
import { index as productsIndex } from '@/routes/products';
import type { ProductCounterparty, ProductFilters } from '@/types';

/**
 * Radix refuses an empty string as an item value, so "no filter" travels as a
 * sentinel here and leaves as an absent query parameter.
 */
const ALL_COUNTERPARTIES = 'all';

/**
 * Only the list and the state describing it are re-fetched. The permissions,
 * the countries, the categories and the assignable connections cannot change
 * while the page is open.
 */
const ONLY = ['products', 'filters', 'hasProducts'];

type Props = {
    organizationSlug: string;
    filters: ProductFilters;
    counterparties: ProductCounterparty[];
    counterpartyLabel: string;
};

export default function ProductFilterBar({
    organizationSlug,
    filters,
    counterparties,
    counterpartyLabel,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const debouncedSearch = useDebouncedValue(search);

    /**
     * The URL is the filter state, so every change is a visit rather than
     * local state. mergeQuery keeps the two controls independent: changing
     * one leaves whatever the other put in the query string alone.
     */
    const visit = (query: { connection?: string; search?: string }) => {
        router.get(
            productsIndex(organizationSlug, { mergeQuery: query }),
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
        visit({ connection: undefined, search: undefined });
    };

    const lowerLabel = counterpartyLabel.toLowerCase();

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="relative min-w-0 flex-1 sm:max-w-xs">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    data-test="product-filter-search"
                    aria-label="Search products by name, barcode or article number"
                    placeholder="Search name, barcode or article no."
                    className="pl-9"
                />
            </div>

            {counterparties.length > 0 ? (
                <Select
                    value={
                        filters.connection === null
                            ? ALL_COUNTERPARTIES
                            : String(filters.connection)
                    }
                    onValueChange={(value) =>
                        visit({
                            connection:
                                value === ALL_COUNTERPARTIES
                                    ? undefined
                                    : value,
                        })
                    }
                >
                    <SelectTrigger
                        data-test="product-filter-connection"
                        aria-label={`Filter by ${lowerLabel}`}
                        className="w-full sm:w-56"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL_COUNTERPARTIES}>
                            All {lowerLabel}s
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

            {filters.connection !== null || filters.search !== null ? (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={clear}
                    data-test="product-filter-clear"
                >
                    <X className="h-4 w-4" /> Clear
                </Button>
            ) : null}
        </div>
    );
}
