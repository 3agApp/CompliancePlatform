{{--
    A run of serialised labels on A4 sheets of die-cut stickers, one sticker
    per packet, after the Swiss Product Seal sample label: the code that
    leads to the packet's serial on the left, and beside it the seal's logo,
    the product, the call to scan in red and the serial in its own box.

    Laid out on the grid from config('labels.sheet'), in millimetres. Each
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

        .logo { width: 15.5mm; display: block; margin-bottom: 0.6mm; }

        .brand {
            font-size: 4pt;
            color: #59646B;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            margin: 0;
        }

        /* Two lines at most; a longer name is cut short rather than crowding the serial. */
        .name {
            font-size: 4.8pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
            margin: 0 0 0.6mm;
        }

        .scan {
            background: #DA291C;
            color: #FFFFFF;
            font-size: 5.5pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            padding: 0.4mm 0;
            border-radius: 0.8mm;
            margin: 0 0 0.5mm;
        }

        .serial {
            border: 0.3mm solid #1F2933;
            border-radius: 0.8mm;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 5.5pt;
            font-weight: bold;
            text-align: center;
            padding: 0.3mm 0;
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
                                <img class="logo" src="{{ public_path('brand/hauptlogo_farbig_hell.png') }}" alt="Swiss Product Seal">
                                @if ($product->brand)
                                    <p class="brand">{{ Str::limit($product->brand->name, 24) }}</p>
                                @endif
                                <p class="name">{{ Str::limit($product->name, 30) }}</p>
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
