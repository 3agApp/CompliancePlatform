import { Head } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import { useState } from 'react';
import ProductSealMark from '@/components/product-seal';
import { cn } from '@/lib/utils';
import type { ProductSeal, PublicProduct, PublicProductImage } from '@/types';

type Props = {
    product: PublicProduct;
    seal: ProductSeal;
    images: PublicProductImage[];
};

/**
 * What this article is, and where its compliance check stands.
 *
 * The page a QR code on a packet leads to. Whoever is reading it has no
 * account and wants two things: to know they are holding the right article,
 * and to know whether anybody has checked it. So it carries the pictures,
 * the name, the maker and the numbers printed on the box -- and the seal.
 *
 * Nothing else. No supplier, no price, no stock, no papers: none of it is
 * the reader's business, and some of it is somebody's trade to protect.
 */
export default function PublicProduct({ product, seal, images }: Props) {
    const [shown, setShown] = useState(0);
    const image = images[shown] ?? null;

    return (
        <>
            <Head title={product.name}>
                <meta
                    name="description"
                    content={`${product.name}${product.brand ? ` by ${product.brand}` : ''} — compliance status: ${seal.label}.`}
                />
            </Head>

            <div className="bg-muted/40 flex min-h-screen flex-col items-center px-4 py-10 sm:py-16">
                <main className="w-full max-w-2xl">
                    <div className="bg-card overflow-hidden rounded-2xl border shadow-sm">
                        <div className="space-y-6 p-6 sm:p-8">
                            <h1 className="sr-only">{product.name}</h1>

                            {/*
                             * The picture comes first: the reader is holding
                             * the article and checking they are on the right
                             * page before they read a word of it.
                             */}
                            {image !== null ? (
                                <figure className="space-y-3">
                                    <img
                                        src={image.url}
                                        alt={product.name}
                                        data-test="product-photo"
                                        className="bg-muted aspect-4/3 w-full rounded-xl object-contain"
                                    />

                                    {images.length > 1 ? (
                                        <div className="flex flex-wrap gap-2">
                                            {images.map((option, index) => (
                                                <button
                                                    key={option.id}
                                                    type="button"
                                                    onClick={() =>
                                                        setShown(index)
                                                    }
                                                    data-test="product-photo-thumb"
                                                    aria-label={`Show picture ${index + 1} of ${images.length}`}
                                                    aria-current={
                                                        index === shown
                                                    }
                                                    className={cn(
                                                        'size-16 overflow-hidden rounded-lg border-2 transition-colors',
                                                        index === shown
                                                            ? 'border-primary'
                                                            : 'hover:border-border border-transparent',
                                                    )}
                                                >
                                                    <img
                                                        src={option.url}
                                                        alt=""
                                                        className="bg-muted size-full object-cover"
                                                    />
                                                </button>
                                            ))}
                                        </div>
                                    ) : null}
                                </figure>
                            ) : (
                                <div
                                    className="bg-muted text-muted-foreground flex aspect-4/3 w-full flex-col items-center justify-center gap-2 rounded-xl"
                                    data-test="product-photo-missing"
                                >
                                    <ImageOff className="size-8" aria-hidden />
                                    <p className="text-sm">
                                        No picture of this article yet
                                    </p>
                                </div>
                            )}

                            <div className="space-y-1">
                                {product.brand ? (
                                    <p
                                        className="text-muted-foreground text-sm font-medium tracking-[0.16em] uppercase"
                                        data-test="product-brand"
                                    >
                                        {product.brand}
                                    </p>
                                ) : null}

                                <p
                                    className="text-2xl font-semibold break-words sm:text-3xl"
                                    data-test="product-name"
                                >
                                    {product.name}
                                </p>
                            </div>

                            {/*
                             * The numbers printed on the box, as chips: this
                             * is how a reader confirms the page in front of
                             * them is about the thing in their hand.
                             */}
                            {product.ean || product.internal_article_number ? (
                                <dl className="flex flex-wrap gap-2">
                                    {product.ean ? (
                                        <Chip
                                            label="EAN"
                                            value={product.ean}
                                            testId="product-ean"
                                        />
                                    ) : null}

                                    {product.internal_article_number ? (
                                        <Chip
                                            label="Article no."
                                            value={
                                                product.internal_article_number
                                            }
                                            testId="product-article-number"
                                        />
                                    ) : null}
                                </dl>
                            ) : null}
                        </div>

                        <div className="border-t p-6 sm:p-8">
                            <ProductSealMark seal={seal} />
                        </div>
                    </div>

                    <p className="text-muted-foreground mt-6 text-center text-xs">
                        Compliance information published by the company that
                        places this product on the market.
                    </p>
                </main>
            </div>
        </>
    );
}

/**
 * One number off the box, named and shown.
 */
function Chip({
    label,
    value,
    testId,
}: {
    label: string;
    value: string;
    testId: string;
}) {
    return (
        <div
            className="bg-muted flex items-baseline gap-2 rounded-full px-3 py-1.5"
            data-test={testId}
        >
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="font-mono text-sm">{value}</dd>
        </div>
    );
}
