{{--
    The A6 sheet a product's code is printed from.

    Written for dompdf rather than for a browser: no flexbox, no grid, no
    external stylesheet and no webfont, because none of them survive the
    renderer. Everything is a table, inline styles and points -- which is
    ugly to read and is the only thing that comes out of the printer looking
    like what is written here.
--}}
{{--
    Nothing about where the compliance check stands is printed here, on
    purpose. This sheet goes into a box and stays there; the seal moves --
    an approved product can be edited, withdrawn and sent back the same
    afternoon. A sticker still claiming "verified" a year after the check
    that earned it was reopened would be the one dishonest thing in the
    whole system, so the status lives only on the page the code points at,
    where it is read fresh every time somebody scans it.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $product->name }}</title>
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
        }

        .sheet { padding: 28px 26px; }

        .brand {
            font-size: 8pt;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #6b7280;
            margin: 0 0 2px;
        }

        .name {
            font-size: 15pt;
            font-weight: bold;
            margin: 0 0 10px;
            line-height: 1.25;
        }

        .numbers { width: 100%; border-collapse: collapse; margin-bottom: 12px; }

        .numbers td {
            font-size: 8pt;
            padding: 2px 0;
            vertical-align: top;
        }

        .numbers .label { color: #6b7280; width: 92px; }
        .numbers .value { font-family: DejaVu Sans Mono, monospace; }

        .qr { text-align: center; }
        .qr img { width: 150px; height: 150px; }

        .scan {
            font-size: 8pt;
            color: #374151;
            margin: 6px 0 0;
            text-align: center;
        }

        .url {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 6.5pt;
            color: #6b7280;
            margin: 3px 0 0;
            text-align: center;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="sheet">
        @if ($product->brand)
            <p class="brand">{{ $product->brand->name }}</p>
        @endif

        <p class="name">{{ $product->name }}</p>

        @if ($product->ean || $product->internal_article_number)
            <table class="numbers">
                @if ($product->ean)
                    <tr>
                        <td class="label">EAN</td>
                        <td class="value">{{ $product->ean }}</td>
                    </tr>
                @endif

                @if ($product->internal_article_number)
                    <tr>
                        <td class="label">Article no.</td>
                        <td class="value">{{ $product->internal_article_number }}</td>
                    </tr>
                @endif
            </table>
        @endif

        <div class="qr">
            <img src="{{ $qr }}" alt="">
        </div>

        <p class="scan">Scan for current compliance information</p>
        <p class="url">{{ $url }}</p>
    </div>
</body>
</html>
