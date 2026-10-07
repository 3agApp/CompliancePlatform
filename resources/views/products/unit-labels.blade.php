{{--
    A run of serialised labels, one label per page at the roll's exact size,
    so a label printer's ordinary driver prints them without scaling.

    One label per packet, for the box: the serial, and the code that leads
    to it.

    Written for dompdf, like the A6 sheet: tables, inline styles, points and
    the DejaVu fonts, because nothing else survives the renderer. Sizes are
    for a 50 x 30 mm label and hold up a few millimetres either side.
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
            color: #000;
        }

        /*
         * A fixed height that never overruns the page: anything taller would
         * spill onto a page of its own, and every label after it on the roll
         * would be one off.
         */
        .label {
            padding: 2mm 3mm 0;
            height: 26mm;
            overflow: hidden;
            page-break-after: always;
        }

        .label:last-child { page-break-after: auto; }

        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 0; }

        .qr { width: 23mm; }
        .qr img { width: 22mm; height: 22mm; }

        .brand {
            font-size: 5.5pt;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 0;
        }

        /* Two lines at most: a third pushes the row off the label and onto a page of its own. */
        .name {
            font-size: 7pt;
            font-weight: bold;
            line-height: 1.2;
            margin: 0.5mm 0 1.5mm;
        }

        .caption { font-size: 5pt; margin: 0; }

        .serial {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 6.5pt;
            font-weight: bold;
            margin: 0.5mm 0 0;
        }


    </style>
</head>
<body>
    @foreach ($units as $unit)
        <div class="label">
            <table>
                <tr>
                    <td class="qr"><img src="{{ $unit['qr'] }}" alt=""></td>
                    <td>
                        @if ($product->brand)
                            <p class="brand">{{ $product->brand->name }}</p>
                        @endif
                        <p class="name">{{ Str::limit($product->name, 24) }}</p>
                        <p class="caption">{{ __('Scan to check this product is genuine') }}</p>
                        <p class="serial">{{ $unit['serial'] }}</p>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
