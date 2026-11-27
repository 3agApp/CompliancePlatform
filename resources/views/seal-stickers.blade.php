{{--
    Sheets of the round Swiss Product Seal sticker: HERMA round 40 mm
    labels, A4 with a grid of die-cut circles from config('labels.seal_sheet'),
    in millimetres.

    Each seal is placed by its own coordinates rather than flowed, centred on
    its circle and drawn a little larger than it (the bleed), so a sheet fed
    a fraction off still shows the red ring to the cut. Print at actual size:
    a driver's "fit to page" shrinks the grid off the circles.

    Expects $grid, $sheets and $seal, the path to the artwork.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Swiss Product Seal</title>
    <style>
        @page { margin: 0; }

        body { margin: 0; }

        .sheet {
            position: relative;
            page-break-after: always;
        }

        .sheet:last-child { page-break-after: auto; }

        .seal {
            position: absolute;
            width: {{ $grid['diameter'] + 2 * $grid['bleed'] }}mm;
            height: {{ $grid['diameter'] + 2 * $grid['bleed'] }}mm;
        }
    </style>
</head>
<body>
    @for ($sheet = 0; $sheet < $sheets; $sheet++)
        <div class="sheet">
            @for ($row = 0; $row < $grid['rows']; $row++)
                @for ($column = 0; $column < $grid['columns']; $column++)
                    <img
                        class="seal"
                        src="{{ $seal }}"
                        alt=""
                        style="left: {{ $grid['left'] + $column * ($grid['diameter'] + $grid['gap']) - $grid['bleed'] }}mm; top: {{ $grid['top'] + $row * ($grid['diameter'] + $grid['gap']) - $grid['bleed'] }}mm;"
                    >
                @endfor
            @endfor
        </div>
    @endfor
</body>
</html>
