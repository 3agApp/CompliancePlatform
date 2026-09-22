import { Check, Copy, ExternalLink, PenLine } from 'lucide-react';
import { useState } from 'react';
import OverrideProductSealModal from '@/components/override-product-seal-modal';
import ProductSealMark from '@/components/product-seal';
import { Button } from '@/components/ui/button';
import type {
    ProductSeal,
    ProductSealOption,
    ProductSealOverride,
} from '@/types';

type Props = {
    organizationSlug: string;
    productId: number;
    seal: ProductSeal;
    override: ProductSealOverride | null;
    availableSeals: ProductSealOption[];
    publicUrl: string;
    canOverrideSeal: boolean;
};

/**
 * What the outside world sees, from inside.
 *
 * The public page is the one part of a product nobody working on it ever
 * visits, so the page it lives on has to show what it says and where it is
 * -- otherwise the first person to find out the seal reads wrong is a
 * customer holding the packet.
 */
export default function ProductPublicPanel({
    organizationSlug,
    productId,
    seal,
    override,
    availableSeals,
    publicUrl,
    canOverrideSeal,
}: Props) {
    const [sealDialogOpen, setSealDialogOpen] = useState(false);
    const [copied, setCopied] = useState(false);

    /**
     * Clipboard access is refused outright on an insecure origin and can be
     * declined on any of them, so the address stays selectable on screen
     * and the button is only ever a shortcut.
     */
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(publicUrl);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    };

    return (
        <div
            className="workspace-panel space-y-4 p-5"
            data-test="product-public-panel"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-medium">Public page</h2>

                <Button variant="ghost" size="sm" asChild>
                    <a href={publicUrl} target="_blank" rel="noreferrer">
                        <ExternalLink className="h-4 w-4" /> Open
                    </a>
                </Button>
            </div>

            <ProductSealMark seal={seal} />

            {override?.setBy ? (
                <p
                    className="text-muted-foreground text-xs"
                    data-test="product-seal-set-by"
                >
                    Set to {override.label} by {override.setBy}
                    {override.reason ? ` — ${override.reason}` : null}
                </p>
            ) : null}

            <div className="flex items-center gap-2">
                <input
                    readOnly
                    value={publicUrl}
                    aria-label="Public page address"
                    data-test="product-public-url"
                    className="border-input bg-muted text-muted-foreground min-w-0 flex-1 truncate rounded-md border px-2 py-1 text-xs"
                    onFocus={(event) => event.currentTarget.select()}
                />

                <Button
                    variant="outline"
                    size="sm"
                    onClick={copy}
                    data-test="product-public-url-copy"
                >
                    {copied ? (
                        <Check className="h-4 w-4" />
                    ) : (
                        <Copy className="h-4 w-4" />
                    )}
                    <span className="sr-only">Copy the public address</span>
                </Button>
            </div>

            {canOverrideSeal ? (
                <>
                    <Button
                        variant="outline"
                        size="sm"
                        className="w-full"
                        data-test="product-override-seal"
                        onClick={() => setSealDialogOpen(true)}
                    >
                        <PenLine className="h-4 w-4" /> Set the seal by hand
                    </Button>

                    <OverrideProductSealModal
                        organizationSlug={organizationSlug}
                        productId={productId}
                        availableSeals={availableSeals}
                        override={override}
                        open={sealDialogOpen}
                        onOpenChange={setSealDialogOpen}
                    />
                </>
            ) : null}
        </div>
    );
}
