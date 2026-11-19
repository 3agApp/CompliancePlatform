<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Watermark text
    |--------------------------------------------------------------------------
    |
    | What is stamped on every picture and PDF the public product page hands
    | out. Left empty, it is the site's own domain, read from APP_URL.
    |
    */

    'text' => env('WATERMARK_TEXT'),

    /*
    |--------------------------------------------------------------------------
    | PDFs
    |--------------------------------------------------------------------------
    |
    | Each page is split into `stamps` horizontal bands and every band gets
    | one stamp at a random position and tilt, so there is no fixed spot to
    | cover up. The stamps blend with Multiply: on white paper they show as a
    | faint tint of `colour`, while black text crossing them stays black.
    | Sizes are in points; the font scales with the page's shorter side,
    | 36pt on A4.
    |
    */

    'pdf' => [
        'stamps' => (int) env('WATERMARK_PDF_STAMPS', 3),
        'colour' => env('WATERMARK_PDF_COLOUR', '#2F5D9B'),
        'opacity' => (float) env('WATERMARK_PDF_OPACITY', 0.12),
        'blend_mode' => 'Multiply',
        'font_size_ratio' => 36 / 595,
        'min_font_size' => 8.0,
        'max_font_size' => 120.0,
        'line_height_ratio' => 1.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pictures
    |--------------------------------------------------------------------------
    |
    | One stamp per picture, white with a faint dark edge so it reads on a
    | pale photo and a dark one alike. Sizes are in pixels. A picture larger
    | than `max_edge` is scaled down first: the full resolution stays behind
    | the login.
    |
    */

    'image' => [
        'stamps' => 1,
        'opacity' => (float) env('WATERMARK_IMAGE_OPACITY', 0.45),
        'font_size_ratio' => 0.07,
        'min_font_size' => 10.0,
        'max_font_size' => 140.0,
        'line_height_ratio' => 96 / 72,
        'max_edge' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stamp shape
    |--------------------------------------------------------------------------
    |
    | Every stamp is tilted by a random angle either way, kept away from
    | horizontal and vertical, and its text follows a sine wave with a random
    | swing (a share of the label's width to either side), length and phase,
    | so no straight-line tool can select or erase it in one stroke. Set
    | `max_amplitude_ratio` to 0 for straight stamps. `coverage` is the share
    | of its band a stamp may fill.
    |
    */

    'stamp' => [
        'min_angle_degrees' => 15.0,
        'max_angle_degrees' => 65.0,
        'coverage' => 0.9,
        'curve' => [
            'min_amplitude_ratio' => 0.04,
            'max_amplitude_ratio' => (float) env('WATERMARK_CURVE', 0.12),
            'min_cycles' => 0.5,
            'max_cycles' => 1.25,
        ],
    ],

];
