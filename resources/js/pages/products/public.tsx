import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    Barcode,
    Building2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Download,
    FileText,
    Hash,
    ImageOff,
    ShieldAlert,
    ShieldCheck,
    ShieldOff,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import ProductUnitCheck from '@/components/product-unit-check';
import PublicHeader from '@/components/public-header';
import { formatPublicDate, usePublicLocale } from '@/lib/public-i18n';
import type { PublicLocale, PublicTranslate } from '@/lib/public-i18n';
import { cn } from '@/lib/utils';
import type {
    ProductDocumentType,
    ProductSeal,
    ProductSealStatus,
    PublicProduct,
    PublicProductDocument,
    PublicProductImage,
    PublicProductSafety,
    UnitCheckResult,
} from '@/types';

type Props = {
    product: PublicProduct;
    safety: PublicProductSafety;
    seal: ProductSeal;
    images: PublicProductImage[];
    documents: PublicProductDocument[];
    importer: string;
    locale: string;
    checkUrl: string;
    /**
     * Whether the product's packets carry serials that can be checked.
     */
    checkable: boolean;
    /**
     * The serial off the scanned label, when the page was reached off one.
     */
    serial: string | null;
    /**
     * What the check the reader just made found.
     */
    checkResult: UnitCheckResult | null;
};

/**
 * How each seal reads at the top of the page. Like everywhere else the seal
 * is shown, colour never carries it alone: every seal has its own icon and
 * says in words what it means.
 */
const HERO: Record<ProductSealStatus, { icon: LucideIcon; gradient: string }> =
    {
        verified: {
            icon: ShieldCheck,
            gradient: 'from-emerald-600 via-emerald-700 to-emerald-800',
        },
        in_progress: {
            icon: ShieldAlert,
            gradient: 'from-amber-500 via-amber-600 to-orange-600',
        },
        not_verified: {
            icon: ShieldOff,
            gradient: 'from-slate-500 via-slate-600 to-slate-700',
        },
    };

/**
 * What this article is, whether the packet in hand is genuine, and where
 * its compliance check stands.
 *
 * The page a QR code on a packet leads to. Whoever is reading it has no
 * account and wants to know they are holding the right article, that it is
 * genuine, and whether anybody has checked it -- and how to use it safely.
 *
 * Nothing about the trade behind it: no supplier, no price, no stock. The
 * only papers on it are the ones the distributor chose to release.
 */
export default function PublicProduct({
    product,
    safety,
    seal,
    images,
    documents,
    importer,
    locale: initialLocale,
    checkUrl,
    checkable,
    serial,
    checkResult,
}: Props) {
    const { locale, setLocale, t } = usePublicLocale(initialLocale);

    return (
        <>
            <Head title={product.name}>
                <meta
                    name="description"
                    content={`${product.name}${product.brand ? ` by ${product.brand}` : ''} — compliance status: ${seal.label}.`}
                />
            </Head>

            <div className="min-h-screen bg-[#F5F4F1] text-gray-900">
                <PublicHeader
                    locale={locale}
                    onLocaleChange={setLocale}
                    t={t}
                />

                <main className="mx-auto max-w-lg pb-12">
                    <SealHero seal={seal} locale={locale} t={t} />

                    <div className="-mt-2 space-y-4 px-4">
                        <section className="overflow-hidden rounded-2xl border border-gray-100/80 bg-white shadow-sm">
                            <Gallery
                                images={images}
                                productName={product.name}
                                t={t}
                            />

                            <div className="p-5">
                                <div className="mb-4">
                                    {product.brand ? (
                                        <div className="mb-1.5 flex items-center gap-1.5">
                                            <div className="size-1 rounded-full bg-emerald-600" />
                                            <p
                                                className="text-xs font-semibold tracking-widest text-gray-400 uppercase"
                                                data-test="product-brand"
                                            >
                                                {product.brand}
                                            </p>
                                        </div>
                                    ) : null}
                                    <h1
                                        className="text-xl leading-tight font-bold break-words text-gray-900"
                                        data-test="product-name"
                                    >
                                        {product.name}
                                    </h1>
                                </div>

                                {product.ean ||
                                product.internal_article_number ? (
                                    <dl className="flex flex-wrap gap-2">
                                        {product.ean ? (
                                            <Chip
                                                icon={Barcode}
                                                label={t('ean')}
                                                value={product.ean}
                                                testId="product-ean"
                                            />
                                        ) : null}
                                        {product.internal_article_number ? (
                                            <Chip
                                                icon={Hash}
                                                label={t('articleNumber')}
                                                value={
                                                    product.internal_article_number
                                                }
                                                testId="product-article-number"
                                            />
                                        ) : null}
                                    </dl>
                                ) : null}
                            </div>
                        </section>

                        {checkable || serial || checkResult ? (
                            <ProductUnitCheck
                                serial={serial}
                                result={checkResult}
                                checkUrl={checkUrl}
                                locale={locale}
                                t={t}
                            />
                        ) : null}

                        <Safety safety={safety} t={t} />

                        {documents.length > 0 ? (
                            <section
                                className="overflow-hidden rounded-2xl border border-gray-100/80 bg-white shadow-sm"
                                data-test="product-public-documents"
                            >
                                <div className="p-5">
                                    <SectionHeader
                                        icon={FileText}
                                        title={t('documentsTitle')}
                                        tone="bg-sky-50 text-sky-600"
                                    />
                                    <p className="-mt-2 mb-4 text-xs text-gray-400">
                                        {t('documentsIntro')}
                                    </p>
                                    <ul className="space-y-3">
                                        {groupByType(documents).map(
                                            ([type, group]) => (
                                                <DocumentGroup
                                                    key={type}
                                                    type={type}
                                                    documents={group}
                                                    t={t}
                                                />
                                            ),
                                        )}
                                    </ul>
                                </div>
                            </section>
                        ) : null}

                        <section
                            className="overflow-hidden rounded-2xl border border-gray-100/80 bg-white shadow-sm"
                            data-test="product-importer"
                        >
                            <div className="p-5">
                                <p className="mb-4 text-[10px] font-bold tracking-widest text-gray-400 uppercase">
                                    {t('importedBy')}
                                </p>
                                <div className="flex items-center gap-4">
                                    <div className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-xl font-bold text-white shadow-md">
                                        {importer.charAt(0).toUpperCase() || (
                                            <Building2 className="size-6" />
                                        )}
                                    </div>
                                    <p className="text-base font-bold text-gray-900">
                                        {importer}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <p className="px-4 pt-2 text-center text-xs text-gray-400">
                            {t('footer')}
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}

/**
 * The seal, as the banner across the top of the page: the first thing a
 * reader sees, coloured by where the check stands.
 */
function SealHero({
    seal,
    locale,
    t,
}: {
    seal: ProductSeal;
    locale: PublicLocale;
    t: PublicTranslate;
}) {
    const hero = HERO[seal.status];
    const Icon = hero.icon;

    return (
        <div
            className={cn(
                'relative overflow-hidden bg-gradient-to-br',
                hero.gradient,
            )}
            data-test="product-seal"
            data-seal={seal.status}
        >
            <div className="absolute inset-0 opacity-10" aria-hidden>
                <div className="absolute top-4 right-4 size-32 rounded-full border-4 border-white" />
                <div className="absolute top-12 right-12 size-16 rounded-full border-2 border-white" />
                <div className="absolute -bottom-8 -left-8 size-40 rounded-full border-4 border-white" />
            </div>

            <div className="relative px-5 pt-8 pb-10">
                <div className="flex items-start gap-4">
                    <div className="shrink-0 rounded-2xl border border-white/20 bg-white/15 p-3 shadow-lg backdrop-blur-sm">
                        <Icon className="size-9 text-white" aria-hidden />
                    </div>
                    <div className="min-w-0 flex-1 pt-1">
                        <div className="mb-2 inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 backdrop-blur-sm">
                            <div className="size-1.5 animate-pulse rounded-full bg-white" />
                            <span className="text-xs font-medium tracking-wider text-white/90 uppercase">
                                {t(`sealPill_${seal.status}`)}
                            </span>
                        </div>
                        <p className="mb-1 text-xl leading-tight font-bold text-white">
                            {t(`sealTitle_${seal.status}`)}
                        </p>
                        <p className="text-sm leading-relaxed text-white/85">
                            {t(`sealDesc_${seal.status}`)}
                        </p>

                        {seal.status === 'verified' && seal.approvedAt ? (
                            <div
                                className="mt-3 inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/20 px-3 py-1.5 text-xs font-medium text-white backdrop-blur-sm"
                                data-test="product-seal-approved-at"
                            >
                                <CheckCircle2 className="size-3" />
                                {t('approved')}:{' '}
                                {formatPublicDate(seal.approvedAt, locale)}
                            </div>
                        ) : null}

                        {/*
                         * The bar belongs to an unfinished check and nowhere
                         * else: on a product that has passed, how complete it
                         * is is no longer the question.
                         */}
                        {seal.status === 'in_progress' ? (
                            <div
                                className="mt-3 flex items-center gap-2"
                                data-test="product-seal-bar"
                            >
                                <div
                                    className="h-1.5 flex-1 overflow-hidden rounded-full bg-white/20"
                                    role="progressbar"
                                    aria-valuenow={seal.score}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-label={t('dataCollected')}
                                >
                                    <div
                                        className="h-full rounded-full bg-white"
                                        style={{ width: `${seal.score}%` }}
                                    />
                                </div>
                                <span className="shrink-0 text-xs font-bold text-white tabular-nums">
                                    {seal.score}%
                                </span>
                            </div>
                        ) : null}

                        {/*
                         * A seal somebody set by hand is not the outcome of a
                         * check, and the page must not let the two read the
                         * same.
                         */}
                        {seal.isOverridden ? (
                            <p
                                className="mt-3 text-xs text-white/80"
                                data-test="product-seal-overridden"
                            >
                                {t('overridden')}
                            </p>
                        ) : null}
                    </div>
                </div>
            </div>

            <div
                className="absolute right-0 bottom-0 left-0 h-6 bg-[#F5F4F1]"
                style={{ clipPath: 'ellipse(55% 100% at 50% 100%)' }}
                aria-hidden
            />
        </div>
    );
}

/**
 * The pictures of the article, one at a time.
 *
 * First on the card: the reader is holding the article and checking they
 * are on the right page before they read a word of it.
 */
function Gallery({
    images,
    productName,
    t,
}: {
    images: PublicProductImage[];
    productName: string;
    t: PublicTranslate;
}) {
    const [shown, setShown] = useState(0);

    /**
     * Pictures the browser could not load are dropped, so a missing file
     * falls back to the "no picture" panel instead of an empty frame.
     */
    const [failed, setFailed] = useState<number[]>([]);
    const visible = images.filter((option) => !failed.includes(option.id));
    const image = visible[shown] ?? visible[0] ?? null;

    const dropImage = (id: number) => {
        setFailed((current) => [...current, id]);
        setShown(0);
    };

    if (image === null) {
        return (
            <div
                className="flex h-48 flex-col items-center justify-center gap-2 border-b border-gray-100 bg-gradient-to-b from-gray-50 to-white text-gray-400"
                data-test="product-photo-missing"
            >
                <ImageOff className="size-8" aria-hidden />
                <p className="text-sm">{t('noPicture')}</p>
            </div>
        );
    }

    const step = (by: number) =>
        setShown((current) => (current + by + visible.length) % visible.length);

    return (
        <figure className="border-b border-gray-100 bg-gradient-to-b from-gray-50 to-white">
            <div className="relative flex h-64 items-center justify-center overflow-hidden">
                <img
                    src={image.url}
                    alt={productName}
                    data-test="product-photo"
                    onError={() => dropImage(image.id)}
                    className="size-full object-contain p-6"
                />

                {visible.length > 1 ? (
                    <>
                        <button
                            type="button"
                            onClick={() => step(-1)}
                            aria-label={t('previousPicture')}
                            className="absolute top-1/2 left-3 -translate-y-1/2 rounded-full bg-white p-2 shadow-md transition-all hover:scale-105 hover:bg-gray-50"
                        >
                            <ChevronLeft className="size-4 text-gray-700" />
                        </button>
                        <button
                            type="button"
                            onClick={() => step(1)}
                            aria-label={t('nextPicture')}
                            className="absolute top-1/2 right-3 -translate-y-1/2 rounded-full bg-white p-2 shadow-md transition-all hover:scale-105 hover:bg-gray-50"
                        >
                            <ChevronRight className="size-4 text-gray-700" />
                        </button>
                    </>
                ) : null}
            </div>

            {visible.length > 1 ? (
                <div className="flex gap-2 overflow-x-auto px-4 pb-4">
                    {visible.map((option, index) => (
                        <button
                            key={option.id}
                            type="button"
                            onClick={() => setShown(index)}
                            data-test="product-photo-thumb"
                            aria-label={`${t('showPicture')} ${index + 1} / ${visible.length}`}
                            aria-current={index === shown}
                            className={cn(
                                'size-14 shrink-0 overflow-hidden rounded-xl border-2 transition-all',
                                index === shown
                                    ? 'scale-105 border-gray-700 shadow-md'
                                    : 'border-transparent hover:border-gray-300',
                            )}
                        >
                            <img
                                src={option.url}
                                alt=""
                                onError={() => dropImage(option.id)}
                                className="size-full object-cover"
                            />
                        </button>
                    ))}
                </div>
            ) : null}
        </figure>
    );
}

/**
 * What the reader needs to use the article safely, when the product has
 * any of it.
 */
function Safety({
    safety,
    t,
}: {
    safety: PublicProductSafety;
    t: PublicTranslate;
}) {
    const notes = (
        [
            'safety_notice',
            'safety_instructions',
            'material_information',
            'usage_restrictions',
        ] as const
    ).filter((key) => safety[key]);

    if (!safety.age_grading && !safety.warning_text && notes.length === 0) {
        return null;
    }

    return (
        <section
            className="overflow-hidden rounded-2xl border border-amber-100/80 bg-white shadow-sm"
            data-test="product-safety"
        >
            <div className="h-1 w-full bg-gradient-to-r from-amber-400 to-orange-400" />
            <div className="space-y-3 p-5">
                <SectionHeader
                    icon={AlertTriangle}
                    title={t('safetyTitle')}
                    tone="bg-amber-50 text-amber-500"
                />

                {safety.age_grading ? (
                    <div className="flex items-center gap-3 rounded-xl border border-amber-100 bg-amber-50 p-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-xs font-bold text-amber-800">
                            {safety.age_grading.replace(/[^0-9+]/g, '') || '!'}
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-amber-800">
                                {t('age_grading')}
                            </p>
                            <p className="text-sm text-amber-700">
                                {safety.age_grading}
                            </p>
                        </div>
                    </div>
                ) : null}

                {safety.warning_text ? (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4">
                        <div className="mb-2 flex items-center gap-2">
                            <AlertTriangle className="size-4 shrink-0 text-red-500" />
                            <p className="text-xs font-bold tracking-wide text-red-800 uppercase">
                                {t('warning_text')}
                            </p>
                        </div>
                        <p className="text-sm leading-relaxed whitespace-pre-line text-red-700">
                            {safety.warning_text}
                        </p>
                    </div>
                ) : null}

                {notes.map((key) => (
                    <div
                        key={key}
                        className="rounded-xl border border-gray-100 bg-gray-50 p-3"
                    >
                        <p className="mb-1 text-xs font-semibold text-gray-500">
                            {t(key)}
                        </p>
                        <p className="text-sm leading-relaxed whitespace-pre-line text-gray-700">
                            {safety[key]}
                        </p>
                    </div>
                ))}
            </div>
        </section>
    );
}

/**
 * One document the reader may download.
 */
/**
 * The order kinds of document are listed in: the papers that say the
 * product complies first, then what helps the reader use it.
 */
const DOCUMENT_ORDER: ProductDocumentType[] = [
    'declaration_of_conformity',
    'certificate',
    'test_report',
    'regulatory_document',
    'manual_or_instructions',
    'safety_image',
    'product_image',
    'other',
];

/**
 * Sort the released documents into one group per kind, in reading order.
 */
function groupByType(
    documents: PublicProductDocument[],
): [ProductDocumentType, PublicProductDocument[]][] {
    return DOCUMENT_ORDER.map(
        (type) =>
            [type, documents.filter((document) => document.type === type)] as [
                ProductDocumentType,
                PublicProductDocument[],
            ],
    ).filter(([, group]) => group.length > 0);
}

/**
 * One kind of document, and every file of that kind the reader may
 * download.
 */
function DocumentGroup({
    type,
    documents,
    t,
}: {
    type: ProductDocumentType;
    documents: PublicProductDocument[];
    t: PublicTranslate;
}) {
    return (
        <li
            className="rounded-xl border border-sky-100 bg-sky-50/60 p-4"
            data-test="product-public-document-group"
            data-type={type}
        >
            <div className="mb-3 flex items-center gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-xl border border-sky-100 bg-white shadow-sm">
                    <FileText className="size-[18px] text-sky-700" />
                </div>
                <p className="text-sm font-semibold text-sky-800">
                    {t(`docType_${type}`)}
                    <span className="ml-1.5 text-xs font-normal text-sky-700/70">
                        ({documents.length})
                    </span>
                </p>
            </div>
            <ul className="space-y-2">
                {documents.map((document) => (
                    <li
                        key={document.id}
                        className="flex items-center gap-3 rounded-lg border border-sky-100 bg-white px-3 py-2"
                        data-test="product-public-document"
                    >
                        <div className="min-w-0 flex-1">
                            <p
                                className="truncate text-sm text-gray-800"
                                title={document.name}
                            >
                                {document.name}
                            </p>
                            <p className="text-xs text-gray-400">
                                {humanSize(document.size)}
                            </p>
                        </div>
                        <a
                            href={document.url}
                            className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-sky-200 bg-white px-3 py-1.5 text-xs font-medium text-sky-700 shadow-sm transition-colors hover:bg-sky-50"
                            aria-label={`${t('download')} ${document.name}`}
                        >
                            <Download className="size-3.5" />
                            {t('download')}
                        </a>
                    </li>
                ))}
            </ul>
        </li>
    );
}

function SectionHeader({
    icon: Icon,
    title,
    tone,
}: {
    icon: LucideIcon;
    title: string;
    tone: string;
}) {
    return (
        <div className="mb-5 flex items-center gap-3">
            <div
                className={cn(
                    'flex size-9 items-center justify-center rounded-xl shadow-sm',
                    tone,
                )}
            >
                <Icon className="size-[18px]" />
            </div>
            <h2 className="text-base font-bold tracking-tight text-gray-900">
                {title}
            </h2>
        </div>
    );
}

/**
 * One number off the box, named and shown.
 */
function Chip({
    icon: Icon,
    label,
    value,
    testId,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    testId: string;
}) {
    return (
        <div
            className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5"
            data-test={testId}
        >
            <Icon className="size-3 text-gray-400" aria-hidden />
            <dt className="sr-only">{label}</dt>
            <dd className="font-mono text-xs text-gray-600">{value}</dd>
        </div>
    );
}

function humanSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
