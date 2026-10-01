import { Form, Head } from '@inertiajs/react';
import { Search, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PublicHeader from '@/components/public-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePublicLocale } from '@/lib/public-i18n';
import { store } from '@/routes/check';

type Props = {
    locale: string;
};

/**
 * Check a packet by the serial on its label.
 *
 * For a QR code that will not scan, or a buyer who would rather type. A
 * genuine serial leads to the product's page with the serial filled in,
 * ready to check; anything else is warned about here.
 */
export default function Check({ locale: initialLocale }: Props) {
    const { locale, setLocale, t } = usePublicLocale(initialLocale);

    return (
        <>
            <Head title={t('checkTitle')} />

            <div className="min-h-screen bg-[#F5F4F1] text-gray-900">
                <PublicHeader
                    locale={locale}
                    onLocaleChange={setLocale}
                    t={t}
                />

                <main className="mx-auto max-w-lg px-4 py-10">
                    <section className="overflow-hidden rounded-2xl border border-gray-100/80 bg-white shadow-sm">
                        <div className="h-1 w-full bg-gradient-to-r from-emerald-500 via-teal-500 to-sky-500" />
                        <div className="space-y-5 p-5">
                            <div className="flex items-start gap-3">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50">
                                    <ShieldCheck className="size-5 text-emerald-600" />
                                </div>
                                <div>
                                    <h1 className="text-base font-bold text-gray-900">
                                        {t('checkTitle')}
                                    </h1>
                                    <p className="mt-0.5 text-xs leading-relaxed text-gray-500">
                                        {t('checkIntro')}
                                    </p>
                                </div>
                            </div>

                            <Form {...store.form()} className="space-y-2">
                                {({ errors, processing }) => (
                                    <>
                                        <Label
                                            htmlFor="check-serial"
                                            className="text-gray-700"
                                        >
                                            {t('serial')}
                                        </Label>
                                        <div className="flex flex-col gap-2 sm:flex-row">
                                            <Input
                                                id="check-serial"
                                                name="serial"
                                                autoComplete="off"
                                                autoCapitalize="characters"
                                                placeholder="XXXX-XXXX-XXXX"
                                                className="h-11 flex-1 border-gray-200 bg-white font-mono text-base text-gray-900 uppercase"
                                                aria-invalid={!!errors.serial}
                                                data-test="check-serial"
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="h-11 bg-emerald-600 text-white hover:bg-emerald-700"
                                                data-test="check-submit"
                                            >
                                                <Search className="size-4" />
                                                {t('checkButton')}
                                            </Button>
                                        </div>
                                        <InputError
                                            message={errors.serial}
                                            data-test="check-serial-error"
                                        />
                                    </>
                                )}
                            </Form>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
