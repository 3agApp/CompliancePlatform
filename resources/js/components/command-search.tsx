import { router, useHttp } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight, Copyright, Factory, Package, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ProductReviewStatusBadge from '@/components/product-review-status-badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { t, tc } from '@/lib/i18n';
import { cn, toUrl } from '@/lib/utils';
import { search } from '@/routes';
import { index as productsIndex } from '@/routes/products';
import type { NavItem, ProductReviewStatus } from '@/types';

type SearchResponse = {
    products: Array<{
        id: number;
        name: string;
        articleNumber: string | null;
        counterparty: string | null;
        reviewStatus: ProductReviewStatus;
        reviewStatusLabel: string;
        url: string;
    }>;
    productsTotal: number;
    connections: Array<{ id: number; label: string; url: string }>;
    brands: Array<{
        id: number;
        name: string;
        counterparty: string | null;
        url: string;
    }>;
};

type Result = {
    key: string;
    group: string;
    url: string;
    icon: LucideIcon;
    title: string;
    meta?: string | null;
    status?: { value: ProductReviewStatus; label: string };
};

type Props = {
    organizationSlug: string;
    /** The sidebar's own destinations, offered as "Go to" results. */
    navItems: NavItem[];
    isSupplier: boolean;
};

/**
 * Search everything the organization can see, from anywhere: ⌘K (or
 * Ctrl+K) opens it, typing narrows it, the arrows move through it and
 * Enter goes there.
 *
 * The term goes to the server only once typing pauses and once it is long
 * enough to mean something; the sidebar's pages are matched on the spot.
 */
export default function CommandSearch({
    organizationSlug,
    navItems,
    isSupplier,
}: Props) {
    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState('');
    const [active, setActive] = useState(0);
    const debouncedTerm = useDebouncedValue(term.trim(), 200);

    const http = useHttp<Record<string, never>, SearchResponse>({});
    const [response, setResponse] = useState<SearchResponse | null>(null);
    const listRef = useRef<HTMLUListElement>(null);

    /**
     * The one shortcut the app answers to everywhere. It is the usual one
     * for a search like this, so people find it without being told.
     */
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.key.toLowerCase() === 'k' &&
                (event.metaKey || event.ctrlKey)
            ) {
                event.preventDefault();
                setOpen((current) => !current);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    useEffect(() => {
        if (!open || debouncedTerm.length < 2) {
            setResponse(null);

            return;
        }

        http.cancel();
        void http
            .get(search.url(organizationSlug, { query: { q: debouncedTerm } }))
            .then((data) => setResponse(data))
            .catch(() => setResponse(null));
    }, [debouncedTerm, open, organizationSlug]);

    const lowered = term.trim().toLocaleLowerCase();

    const results: Result[] = [
        ...(response?.products ?? []).map((product) => ({
            key: `product-${product.id}`,
            group: t('Products'),
            url: product.url,
            icon: Package,
            title: product.name,
            meta: [product.articleNumber, product.counterparty]
                .filter(Boolean)
                .join(' · '),
            status: {
                value: product.reviewStatus,
                label: product.reviewStatusLabel,
            },
        })),
        ...(response !== null &&
        response.productsTotal > response.products.length
            ? [
                  {
                      key: 'products-all',
                      group: t('Products'),
                      url: productsIndex(organizationSlug, {
                          query: { search: term.trim() },
                      }).url,
                      icon: ArrowRight,
                      title: tc(
                          'See the 1 product matching “:term”|See all :count products matching “:term”',
                          response.productsTotal,
                          { term: term.trim() },
                      ),
                  },
              ]
            : []),
        ...(response?.connections ?? []).map((connection) => ({
            key: `connection-${connection.id}`,
            group: isSupplier ? t('Distributors') : t('Suppliers'),
            url: connection.url,
            icon: Factory,
            title: connection.label,
            meta: t('View products'),
        })),
        ...(response?.brands ?? []).map((brand) => ({
            key: `brand-${brand.id}`,
            group: t('Brands'),
            url: brand.url,
            icon: Copyright,
            title: brand.name,
            meta: brand.counterparty,
        })),
        ...navItems
            .filter(
                (item) =>
                    lowered === '' ||
                    item.title.toLocaleLowerCase().includes(lowered),
            )
            .map((item) => ({
                key: `page-${item.testId ?? item.title}`,
                group: t('Go to'),
                url: toUrl(item.href),
                icon: item.icon ?? ArrowRight,
                title: item.title,
            })),
    ];

    const go = (result: Result | undefined) => {
        if (result === undefined) {
            return;
        }

        setOpen(false);
        router.visit(result.url);
    };

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            const next =
                event.key === 'ArrowDown'
                    ? Math.min(active + 1, results.length - 1)
                    : Math.max(active - 1, 0);

            setActive(next);
            listRef.current
                ?.querySelector(`[data-index="${next}"]`)
                ?.scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter') {
            event.preventDefault();
            go(results[active]);
        }
    };

    const onOpenChange = (next: boolean) => {
        setOpen(next);

        if (!next) {
            setTerm('');
            setActive(0);
            setResponse(null);
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                data-test="command-search-trigger"
                className="bg-background text-muted-foreground hover:text-foreground focus-visible:ring-ring flex h-8 w-full items-center gap-2 rounded-md border px-2.5 text-sm transition-colors outline-none group-data-[collapsible=icon]:hidden focus-visible:ring-2"
            >
                <Search className="size-4" />
                {t('Search')}
                <kbd className="bg-muted ml-auto rounded border px-1.5 font-sans text-[11px] font-medium">
                    ⌘K
                </kbd>
            </button>

            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent
                    className="top-[15%] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-xl"
                    data-test="command-search"
                >
                    <DialogTitle className="sr-only">{t('Search')}</DialogTitle>
                    <DialogDescription className="sr-only">
                        {t('Search products, suppliers, brands and pages.')}
                    </DialogDescription>

                    <div className="flex items-center gap-2.5 border-b px-4">
                        <Search className="text-muted-foreground size-4 shrink-0" />
                        <input
                            autoFocus
                            value={term}
                            onChange={(event) => {
                                setTerm(event.target.value);
                                setActive(0);
                            }}
                            onKeyDown={onKeyDown}
                            placeholder={t(
                                'Search products, suppliers, brands and pages',
                            )}
                            aria-label={t('Search')}
                            role="combobox"
                            aria-expanded
                            aria-controls="command-search-results"
                            aria-activedescendant={
                                results[active]
                                    ? `command-result-${active}`
                                    : undefined
                            }
                            className="placeholder:text-muted-foreground h-12 flex-1 bg-transparent text-sm outline-none"
                            data-test="command-search-input"
                        />
                    </div>

                    <ul
                        ref={listRef}
                        id="command-search-results"
                        role="listbox"
                        className="max-h-96 overflow-y-auto py-1"
                    >
                        {results.map((result, index) => (
                            <li key={result.key} role="presentation">
                                {index === 0 ||
                                results[index - 1].group !== result.group ? (
                                    <p className="text-muted-foreground px-4 pt-3 pb-1 text-xs font-medium tracking-wide uppercase">
                                        {result.group}
                                    </p>
                                ) : null}
                                <a
                                    href={result.url}
                                    id={`command-result-${index}`}
                                    role="option"
                                    aria-selected={index === active}
                                    data-index={index}
                                    data-test="command-search-result"
                                    onMouseMove={() => setActive(index)}
                                    onClick={(event) => {
                                        event.preventDefault();
                                        go(result);
                                    }}
                                    className={cn(
                                        'flex items-center gap-3 px-4 py-2 text-sm',
                                        index === active && 'bg-muted',
                                    )}
                                >
                                    <result.icon className="text-muted-foreground size-4 shrink-0" />
                                    <span className="grid min-w-0 flex-1">
                                        <span className="truncate">
                                            {result.title}
                                        </span>
                                        {result.meta ? (
                                            <span className="text-muted-foreground truncate text-xs">
                                                {result.meta}
                                            </span>
                                        ) : null}
                                    </span>
                                    {result.status ? (
                                        <ProductReviewStatusBadge
                                            status={result.status.value}
                                            label={result.status.label}
                                        />
                                    ) : null}
                                </a>
                            </li>
                        ))}

                        {results.length === 0 ? (
                            <li className="text-muted-foreground px-4 py-6 text-center text-sm">
                                {http.processing
                                    ? t('Searching…')
                                    : t('Nothing matches “:term”.', {
                                          term: term.trim(),
                                      })}
                            </li>
                        ) : null}
                    </ul>

                    <div className="text-muted-foreground bg-muted/40 flex flex-wrap gap-4 border-t px-4 py-2 text-xs">
                        <span>{t('↑ ↓ to move')}</span>
                        <span>{t('↵ to open')}</span>
                        <span className="ml-auto">
                            {t('Finds names, article numbers and EANs')}
                        </span>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
