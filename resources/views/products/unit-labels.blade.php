{{--
    A run of serialised labels on A4 sheets of die-cut stickers, one sticker
    per packet, after the Swiss Product Seal sample label: the code that
    leads to the packet's serial on the left, and beside it the seal's logo,
    the brand and product, the call to scan in red and the serial in its
    own box.

    Expects $copy from App\Support\StickerCopy. Laid out on the grid from
    config('labels.sheet'), in millimetres. Each
    label is placed by its own coordinates rather than flowed, so a long name
    is clipped on its own sticker instead of pushing every label after it off
    its die-cut square. Print at actual size: a driver's "fit to page"
    shrinks the grid off the sheet.

    Written for dompdf: tables, inline styles, points and the DejaVu fonts,
    because nothing else survives the renderer.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $product->name }}</title>
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #1F2933;
        }

        .sheet {
            position: relative;
            page-break-after: always;
        }

        .sheet:last-child { page-break-after: auto; }

        .label {
            position: absolute;
            width: {{ $grid['width'] - 3.6 }}mm;
            height: {{ $grid['height'] - 2.4 }}mm;
            padding: 1.2mm 1.8mm;
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: middle; padding: 0; }

        .qr { width: 21.5mm; }
        .qr img { width: 20mm; height: 20mm; display: block; }

        .info {
            border-left: 0.3mm solid #1F2933;
            padding-left: 1.6mm;
        }

        /* The one-line logo: the stacked one wastes the width of a sticker this wide and short. */
        .logo { width: 100%; display: block; margin-bottom: 0.7mm; }

        /*
         * Brand and product as one run of copy, the brand in capitals ahead
         * of the name. Two lines are kept for it whatever its length, so the
         * call to scan and the serial sit at the same height on every
         * sticker. App\Support\StickerCopy has already shortened the name to
         * what these two lines hold, so it ends on "..." rather than on half
         * a line; its size and width are kept in step with these.
         */
        .product {
            font-size: 5.3pt;
            line-height: 1.1;
            height: 5.2mm;
            overflow: hidden;
            margin: 0 0 0.8mm;
        }

        .brand { font-weight: bold; }

        .scan {
            background: #DA291C;
            color: #FFFFFF;
            font-size: 6pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            padding: 0.5mm 0;
            border-radius: 0.8mm;
            margin: 0 0 0.7mm;
        }

        .serial {
            border: 0.3mm solid #1F2933;
            border-radius: 0.8mm;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 6pt;
            font-weight: bold;
            text-align: center;
            padding: 0.4mm 0;
            margin: 0;
        }
    </style>
</head>
<body>
    @foreach (collect($units)->chunk($grid['columns'] * $grid['rows']) as $sheet)
        <div class="sheet">
            @foreach ($sheet->values() as $index => $unit)
                <div
                    class="label"
                    style="left: {{ $grid['left'] + ($index % $grid['columns']) * $grid['width'] }}mm; top: {{ $grid['top'] + intdiv($index, $grid['columns']) * $grid['height'] }}mm;"
                >
                    <table>
                        <tr>
                            <td class="qr"><img src="{{ $unit['qr'] }}" alt=""></td>
                            <td class="info">
                                <img class="logo" src="{{ public_path('brand/headerlogo_farbig_hell.png') }}" alt="Swiss Product Seal">
                                <div class="product">
                                    @if ($copy['brand'])
                                        <span class="brand">{{ $copy['brand'] }}</span>
                                    @endif
                                    {{ $copy['name'] }}
                                </div>
                                <p class="scan">{{ __('Scan to verify') }}</p>
                                <p class="serial">{{ $unit['serial'] }}</p>
                            </td>
                        </tr>
                    </table>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
