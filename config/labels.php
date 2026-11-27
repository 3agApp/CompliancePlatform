<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Label Sheet
    |--------------------------------------------------------------------------
    |
    | The die-cut sticker paper serialised labels are printed on, in
    | millimetres: HERMA No. 4357, A4 with 4 x 10 labels of 48.5 x 25.4 mm
    | and no gaps between them. The margins are HERMA's own, from the
    | packaging: 8 mm either side and 21.5 mm top and bottom.
    |
    */

    'sheet' => [
        'columns' => 4,
        'rows' => 10,
        'width' => 48.5,
        'height' => 25.4,
        'left' => 8.0,
        'top' => 21.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Seal Sheet
    |--------------------------------------------------------------------------
    |
    | The round stickers the seal itself is printed on, in millimetres:
    | HERMA's round 40 mm labels, A4 with 4 x 6 of them. Margins and gaps
    | are HERMA's own, from the template: 16 mm either side, 13.5 mm top and
    | bottom, and 6 mm between the stickers both ways.
    |
    | The bleed is how far the artwork runs past the die-cut, all round, so
    | a sheet fed a fraction off still shows no white rim.
    |
    */

    'seal_sheet' => [
        'columns' => 4,
        'rows' => 6,
        'diameter' => 40.0,
        'left' => 16.0,
        'top' => 13.5,
        'gap' => 6.0,
        'bleed' => 0.4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Most Seal Sheets
    |--------------------------------------------------------------------------
    |
    | The most sheets of seals one print may ask for: 480 seals.
    |
    */

    'max_seal_sheets' => 20,

    /*
    |--------------------------------------------------------------------------
    | Largest Batch
    |--------------------------------------------------------------------------
    |
    | The most packets one print run may ask for. A shipment larger than
    | this is printed as several runs. Rendering is the limit: four hundred
    | labels is about eight seconds and 125 MB to draw.
    |
    */

    'max_batch' => 400,

    /*
    |--------------------------------------------------------------------------
    | Unusual Checks
    |--------------------------------------------------------------------------
    |
    | How many checks of one serial the overview flags as unusual. A buyer
    | checks a new box once or twice; a label copied onto many boxes, or
    | handed round, is checked far more often than that.
    |
    */

    'unusual_checks' => 20,

];
