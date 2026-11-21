import { router } from '@inertiajs/react';
import { ImageUp, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** The largest logo accepted, matching the server's limit. */
const MAX_LOGO_BYTES = 2 * 1024 * 1024;

const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

type Props = {
    logoUrl: string | null;
    alt: string;
    storeUrl: string;
    destroyUrl: string;
    /** Prefix for the test hooks, so each place keeps its own. */
    testIdPrefix: string;
};

/**
 * A logo, uploaded the moment it is chosen or dropped.
 *
 * Shared by the organization profile and the brands page, which keep their
 * logos to the same rules on the server.
 */
export default function LogoUploadField({
    logoUrl,
    alt,
    storeUrl,
    destroyUrl,
    testIdPrefix,
}: Props) {
    const input = useRef<HTMLInputElement>(null);
    const [error, setError] = useState<string | undefined>();
    const [uploading, setUploading] = useState(false);
    const [dragging, setDragging] = useState(false);

    const upload = (file: File | undefined) => {
        if (file === undefined) {
            return;
        }

        if (!LOGO_TYPES.includes(file.type)) {
            setError(t('The logo must be a PNG, JPG or WebP image.'));

            return;
        }

        if (file.size > MAX_LOGO_BYTES) {
            setError(t('The logo may not be larger than 2 MB.'));

            return;
        }

        setError(undefined);

        router.post(
            storeUrl,
            { logo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onStart: () => setUploading(true),
                onFinish: () => {
                    setUploading(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
                onError: (errors) => setError(errors.logo),
            },
        );
    };

    const remove = () => {
        router.delete(destroyUrl, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setUploading(true),
            onFinish: () => setUploading(false),
        });
    };

    return (
        <div className="flex flex-wrap items-center gap-5">
            <label
                className={cn(
                    'bg-muted/40 relative flex size-24 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed transition-colors',
                    dragging && 'border-primary bg-primary/5',
                    logoUrl && 'border-solid bg-white',
                )}
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);
                    upload(event.dataTransfer.files[0]);
                }}
                data-test={`${testIdPrefix}-dropzone`}
            >
                {logoUrl ? (
                    <img
                        src={logoUrl}
                        alt={alt}
                        className="size-full object-contain p-2"
                        data-test={`${testIdPrefix}-image`}
                    />
                ) : (
                    <ImageUp className="text-muted-foreground size-7" />
                )}
                {uploading && (
                    <span className="bg-background/70 absolute inset-0 flex items-center justify-center">
                        <Spinner />
                    </span>
                )}
                <input
                    ref={input}
                    type="file"
                    accept={LOGO_TYPES.join(',')}
                    className="sr-only"
                    onChange={(event) => upload(event.target.files?.[0])}
                    data-test={`${testIdPrefix}-input`}
                />
            </label>
            <div className="space-y-2">
                <p className="text-sm font-medium">{t('Logo')}</p>

                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={uploading}
                        onClick={() => input.current?.click()}
                    >
                        <ImageUp />
                        {logoUrl ? t('Replace') : t('Upload logo')}
                    </Button>
                    {logoUrl && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={uploading}
                            onClick={remove}
                            data-test={`${testIdPrefix}-remove`}
                        >
                            <Trash2 />
                            {t('Remove')}
                        </Button>
                    )}
                </div>
                <InputError message={error} />
            </div>
        </div>
    );
}
