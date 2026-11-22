{{--
    A run of serialised labels, one per packet: the code that leads to its
    serial, whose product it is, and the seal's mark beside the call to scan.

    Laid out on the grid from App\Enums\LabelSheet::grid(), in millimetres:
    a roll is a grid of one, the page being the label; an A4 sheet of
    die-cut labels holds many. Each label is placed by its own coordinates
    rather than flowed, so a long name is clipped on its own label instead
    of pushing it, and every label after it, onto a page of its own. Print
    at actual size: a driver's "fit to page" shrinks the grid off the sheet.

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
            color: #000;
        }

        .sheet {
            position: relative;
            page-break-after: always;
        }

        .sheet:last-child { page-break-after: auto; }

        .label {
            position: absolute;
            width: {{ $grid['width'] - 4 }}mm;
            height: {{ $grid['height'] - 3 }}mm;
            padding: 1.5mm 2mm;
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 0; }

        /* As large as the label's height allows, up to the 22 mm a phone reads from arm's length. */
        .qr { width: {{ min(22, $grid['height'] - 5) + 1 }}mm; }
        .qr img { width: {{ min(22, $grid['height'] - 5) }}mm; height: {{ min(22, $grid['height'] - 5) }}mm; }

        .brand {
            font-size: 5pt;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 0;
        }

        /* Two lines at most, or the serial falls off the bottom of the label. */
        .name {
            font-size: 6.5pt;
            font-weight: bold;
            line-height: 1.2;
            margin: 0.5mm 0 1mm;
        }

        .scan { margin: 0; }
        .scan td { vertical-align: middle; }
        .mark { width: 4mm; padding-right: 1mm; }
        .mark img { width: 4mm; height: 4mm; }

        .caption { font-size: 4.5pt; margin: 0; }

        .serial {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 6pt;
            font-weight: bold;
            margin: 0.5mm 0 0;
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
                            <td>
                                @if ($product->brand)
                                    <p class="brand">{{ $product->brand->name }}</p>
                                @endif
                                <p class="name">{{ Str::limit($product->name, 24) }}</p>
                                <table class="scan">
                                    <tr>
                                        <td class="mark"><img src="{{ public_path('brand/bildmarke_schwarz.png') }}" alt=""></td>
                                        <td><p class="caption">{{ __('Scan to check this product is genuine') }}</p></td>
                                    </tr>
                                </table>
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
