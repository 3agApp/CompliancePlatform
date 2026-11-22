<?php

namespace App\Enums;

/**
 * What a run of serialised labels is printed onto.
 *
 * A label printer takes a roll and wants one label per page at the label's
 * size. An office printer takes an A4 sheet of die-cut labels and wants the
 * whole sheet drawn at once, each label exactly where the sheet has it.
 */
enum LabelSheet: string
{
    /**
     * Points in a millimetre.
     */
    private const float POINTS_PER_MM = 72 / 25.4;

    /** One label per page, at the size set in `config/labels.php`. */
    case Roll = 'roll';

    /** HERMA No. 4357: A4, 4 x 10 labels of 48.5 x 25.4 mm, no gaps between them. */
    case Herma4357 = 'herma-4357';

    /**
     * Get where the labels sit on one page, in millimetres.
     *
     * A roll is a grid of one: the page is the label. The HERMA margins are
     * HERMA's own, from the sheet's packaging: 8 mm either side and 21.5 mm
     * top and bottom, which leaves the 4 x 10 grid flush.
     *
     * @return array{columns: int, rows: int, width: float, height: float, left: float, top: float}
     */
    public function grid(): array
    {
        return match ($this) {
            self::Roll => ['columns' => 1, 'rows' => 1, 'width' => (float) config('labels.width_mm'), 'height' => (float) config('labels.height_mm'), 'left' => 0.0, 'top' => 0.0],
            self::Herma4357 => ['columns' => 4, 'rows' => 10, 'width' => 48.5, 'height' => 25.4, 'left' => 8.0, 'top' => 21.5],
        };
    }

    /**
     * Get the paper to lay the grid out on, in points, as dompdf takes it.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function paper(): array
    {
        $grid = $this->grid();

        [$width, $height] = match ($this) {
            self::Roll => [$grid['width'], $grid['height']],
            self::Herma4357 => [210.0, 297.0],
        };

        return [0.0, 0.0, $width * self::POINTS_PER_MM, $height * self::POINTS_PER_MM];
    }
}
