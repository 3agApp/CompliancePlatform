import { Download, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { label as labelRoute, qr } from '@/routes/products';

type Props = {
    organizationSlug: string;
    productId: number;
    productName: string;
};

/**
 * The code that puts the public page on the product itself.
 *
 * Shown as well as offered: the whole point of a code is that somebody
 * prints it on a box, and a wrong one is only ever found by a customer
 * scanning it. So the picture on screen is the same picture the PNG hands
 * over, and it can be scanned off the screen to check where it goes.
 *
 * Three formats for three jobs: a PNG to drop into a listing, an SVG for
 * whoever lays out the packaging and needs it to scale, and an A6 sheet for
 * whoever just wants to print something and put it in the box.
 */
export default function ProductQrPanel({
    organizationSlug,
    productId,
    productName,
}: Props) {
    const png = qr([organizationSlug, productId, 'png']).url;
    const svg = qr([organizationSlug, productId, 'svg']).url;
    const sheet = labelRoute([organizationSlug, productId]).url;

    /**
     * The filename the browser saves under. The server offers one too, but
     * it serves the picture inline so the preview above can use the same
     * address, and inline is the disposition the preview needs.
     */
    const filename = (extension: string) =>
        `${
            productName
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '') || 'product'
        }-qr.${extension}`;

    return (
        <div
            className="workspace-panel space-y-4 p-5"
            data-test="product-qr-panel"
        >
            <h2 className="text-sm font-medium">QR code</h2>

            <img
                src={png}
                alt={`QR code linking to the public page for ${productName}`}
                data-test="product-qr-preview"
                className="bg-background mx-auto block size-40 rounded-lg border p-2"
            />

            <p className="text-muted-foreground text-xs">
                Scanning it opens this product's public page.
            </p>

            <div className="grid grid-cols-2 gap-2">
                <Button variant="outline" size="sm" asChild>
                    <a
                        href={png}
                        download={filename('png')}
                        data-test="product-qr-png"
                    >
                        <Download className="h-4 w-4" /> PNG
                    </a>
                </Button>

                <Button variant="outline" size="sm" asChild>
                    <a
                        href={svg}
                        download={filename('svg')}
                        data-test="product-qr-svg"
                    >
                        <Download className="h-4 w-4" /> SVG
                    </a>
                </Button>
            </div>

            <Button variant="outline" size="sm" className="w-full" asChild>
                <a href={sheet} data-test="product-qr-pdf">
                    <FileText className="h-4 w-4" /> A6 sheet (PDF)
                </a>
            </Button>
        </div>
    );
}
