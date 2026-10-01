import { RefreshCw } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PublicTranslate } from '@/lib/public-i18n';
import { captcha } from '@/routes';

type Props = {
    /**
     * Bumped to draw a new code. The server forgets a code the moment it is
     * checked, right or wrong, so every submit needs a fresh picture.
     */
    nonce: number;
    onRefresh: () => void;
    error?: string;
    t: PublicTranslate;
};

/**
 * A picture of a code, and a box to type it back into.
 *
 * Drawn by this application rather than a third party, so checking a
 * product sends nothing about the reader anywhere else.
 */
export default function CaptchaField({ nonce, onRefresh, error, t }: Props) {
    return (
        <div className="space-y-2">
            <Label htmlFor="captcha" className="text-gray-700">
                {t('captchaLabel')}
            </Label>
            <div className="flex flex-wrap items-center gap-2">
                <img
                    key={nonce}
                    src={captcha.url({ query: { n: nonce } })}
                    alt={t('captchaAlt')}
                    width={180}
                    height={60}
                    className="h-12 w-36 shrink-0 rounded-lg border border-gray-200 bg-white object-cover"
                    data-test="captcha-image"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onRefresh}
                    aria-label={t('captchaRefresh')}
                    className="text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                    data-test="captcha-refresh"
                >
                    <RefreshCw className="size-4" />
                </Button>
                <Input
                    id="captcha"
                    name="captcha"
                    autoComplete="off"
                    autoCapitalize="characters"
                    spellCheck={false}
                    maxLength={8}
                    placeholder={t('captchaPlaceholder')}
                    className="h-12 w-32 border-gray-200 bg-white font-mono text-base tracking-widest text-gray-900 uppercase"
                    aria-invalid={!!error}
                    data-test="captcha-input"
                />
            </div>
            <InputError message={error} data-test="captcha-error" />
        </div>
    );
}
