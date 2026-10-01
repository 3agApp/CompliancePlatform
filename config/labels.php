<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Label Size
    |--------------------------------------------------------------------------
    |
    | The size of one label on the roll, in millimetres. Serialised labels
    | are laid out one per page at exactly this size, so they print through
    | any label printer's ordinary driver without being scaled.
    |
    */

    'width_mm' => (float) env('LABEL_WIDTH_MM', 50),

    'height_mm' => (float) env('LABEL_HEIGHT_MM', 30),

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

];
