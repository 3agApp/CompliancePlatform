import { Form } from '@inertiajs/react';
import {
    History,
    Search,
    ShieldCheck,
    ShieldX,
    Smartphone,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import CaptchaField from '@/components/captcha-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPublicDate } from '@/lib/public-i18n';
import type { PublicLocale, PublicTranslate } from '@/lib/public-i18n';
import { cn } from '@/lib/utils';
import type { ProductUnitStatus, UnitCheckResult } from '@/types';

type Props = {
    /** The serial off the scanned label, filled in for the reader. */
    serial: string | null;
    /** What the check the reader just made found, once they made one. */
    result: UnitCheckResult | null;
    checkUrl: string;
    locale: PublicLocale;
    t: PublicTranslate;
};

const GOOD = {
    box: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    icon: 'text-emerald-600',
};
const WARN = {
    box: 'border-amber-200 bg-amber-50 text-amber-900',
    icon: 'text-amber-600',
};
const BAD = {
    box: 'border-red-200 bg-red-50 text-red-900',
    icon: 'text-red-600',
};

/**
 * How each answer reads. Colour never carries it alone: every answer has
 * its own icon and says in words what it means.
 */
const LOOKS: Record<
    ProductUnitStatus,
    { icon: LucideIcon; tone: typeof GOOD }
> = {
    first_check: { icon: ShieldCheck, tone: GOOD },
    checked_before: { icon: ShieldCheck, tone: GOOD },
    checked_elsewhere: { icon: TriangleAlert, tone: WARN },
    unknown: { icon: ShieldX, tone: BAD },
    revoked: { icon: ShieldX, tone: BAD },
};

/**
 * Whether the packet in the reader's hand is genuine.
 *
 * The serial comes filled in off the scanned label, and can be changed to
 * check another. Nothing about the packet shows until the reader types the
 * code and presses Check: the answer is for whoever asked, not for anybody
 * who scans a box on a shelf. A code checked before shows when, and
 * whether from this device or another.
 */
export default function ProductUnitCheck({
    serial,
    result,
    checkUrl,
    locale,
    t,
}: Props) {
    const [nonce, setNonce] = useState(0);

    return (
        <section
            className="overflow-hidden rounded-2xl border border-gray-100/80 bg-white shadow-sm"
            data-test="product-unit-check"
        >
            <div className="h-1 w-full bg-gradient-to-r from-emerald-500 via-teal-500 to-sky-500" />
            <div className="space-y-4 p-5">
                <div className="flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50">
                        <ShieldCheck className="size-5 text-emerald-600" />
                    </div>
                    <div>
                        <h2 className="text-base font-bold text-gray-900">
                            {t('checkCardTitle')}
                        </h2>
                        <p className="mt-0.5 text-xs leading-relaxed text-gray-500">
                            {t('checkCardIntro')}
                        </p>
                    </div>
                </div>

                {result ? (
                    <Result result={result} locale={locale} t={t} />
                ) : null}

                <Form
                    action={checkUrl}
                    method="post"
                    options={{ preserveScroll: true }}
                    resetOnError={['captcha']}
                    resetOnSuccess={['captcha']}
                    onFinish={() => setNonce((value) => value + 1)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label
                                    htmlFor="unit-serial"
                                    className="text-gray-700"
                                >
                                    {t('serial')}
                                </Label>
                                <Input
                                    key={result?.serial ?? serial ?? ''}
                                    id="unit-serial"
                                    name="serial"
                                    defaultValue={
                                        result?.serial ?? serial ?? ''
                                    }
                                    autoComplete="off"
                                    autoCapitalize="characters"
                                    spellCheck={false}
                                    placeholder="XXXX-XXXX-XXXX"
                                    className="h-11 border-gray-200 bg-white font-mono text-base text-gray-900 uppercase sm:max-w-72"
                                    aria-invalid={!!errors.serial}
                                    data-test="check-serial"
                                />
                                <p className="text-xs text-gray-400">
                                    {t('serialHint')}
                                </p>
                                <InputError
                                    message={errors.serial}
                                    data-test="check-serial-error"
                                />
                            </div>

                            <CaptchaField
                                nonce={nonce}
                                onRefresh={() => setNonce((value) => value + 1)}
                                error={errors.captcha}
                                t={t}
                            />

                            <div className="space-y-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-11 w-full bg-emerald-600 text-white hover:bg-emerald-700 sm:w-auto"
                                    data-test="check-submit"
                                >
                                    <Search className="size-4" />
                                    {t('checkSubmit')}
                                </Button>
                                <p className="text-xs leading-relaxed text-gray-500">
                                    {t('checkNote')}
                                </p>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </section>
    );
}

/**
 * What the check found, and the checks that came before it.
 */
function Result({
    result,
    locale,
    t,
}: {
    result: UnitCheckResult;
    locale: PublicLocale;
    t: PublicTranslate;
}) {
    const look = LOOKS[result.status];
    const Icon = look.icon;
    const hidden = result.earlierChecks - result.history.length;

    return (
        <div
            className={cn('rounded-xl border p-3.5', look.tone.box)}
            aria-live="polite"
            data-test="check-result"
            data-status={result.status}
        >
            <div className="flex items-start gap-3">
                <Icon
                    className={cn('mt-0.5 size-5 shrink-0', look.tone.icon)}
                    aria-hidden
                />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-bold">
                        {t(`resultTitle_${result.status}`)}
                    </p>
                    <p className="mt-1 text-xs leading-relaxed">
                        {t(`resultText_${result.status}`)}
                    </p>
                    <p className="mt-1 font-mono text-xs opacity-70">
                        {result.serial}
                    </p>
                </div>
            </div>

            {result.history.length > 0 ? (
                <div
                    className="mt-3 border-t border-current/10 pt-3"
                    data-test="check-history"
                >
                    <p className="mb-2 flex items-center gap-1.5 text-xs font-semibold">
                        <History className="size-3.5" aria-hidden />
                        {result.earlierChecks === 1
                            ? t('historyCountOne')
                            : t('historyCount', {
                                  count: String(result.earlierChecks),
                              })}
                    </p>
                    <ol className="space-y-1.5">
                        {result.history.map((check, index) => (
                            <li
                                key={`${check.at}-${index}`}
                                className="flex items-center justify-between gap-3 rounded-lg bg-white/70 px-2.5 py-1.5 text-xs"
                                data-test="check-history-entry"
                            >
                                <span className="tabular-nums">
                                    {formatPublicDate(check.at, locale, true)}
                                </span>
                                <span
                                    className={cn(
                                        'inline-flex shrink-0 items-center gap-1 font-medium',
                                        check.thisDevice
                                            ? 'text-emerald-700'
                                            : 'text-amber-700',
                                    )}
                                >
                                    <Smartphone
                                        className="size-3"
                                        aria-hidden
                                    />
                                    {check.thisDevice
                                        ? t('thisDevice')
                                        : t('otherDevice')}
                                </span>
                            </li>
                        ))}
                    </ol>
                    {hidden > 0 ? (
                        <p className="mt-1.5 text-xs opacity-70">
                            {t('historyMore', { count: String(hidden) })}
                        </p>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
