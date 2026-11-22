<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Watermark text
    |--------------------------------------------------------------------------
    |
    | Every picture and PDF the public product page hands out carries the
    | Swiss Product Seal logo (see "Logo" below). Set a text here to stamp it
    | beside the logo as well, bent along a wave; left empty, there is none.
    |
    */

    'text' => env('WATERMARK_TEXT'),

    /*
    |--------------------------------------------------------------------------
    | PDFs
    |--------------------------------------------------------------------------
    |
    | Every page gets `stamps` stamps (one band each) at a random position
    | and tilt, so there is no fixed spot to cover up. The stamps blend with
    | Multiply: on white paper they show as a faint tint of `colour`, while
    | black text crossing them stays black. Sizes are in points; the font
    | scales with the page's shorter side, 48pt on A4.
    |
    | The stamps are drawn as shapes, not text, so no "remove text" tool
    | finds them. A page with at most `scan_characters` characters of text
    | is a scan: it is flattened into one JPEG of `scan_dpi` with the stamp
    | in its pixels, so the stamp cannot be lifted off the picture beneath.
    | The whole file is then slimmed with Ghostscript's `quality` preset
    | and, with `lock`, encrypted so it opens and prints but cannot be
    | edited or taken apart.
    |
    */

    'pdf' => [
        'stamps' => (int) env('WATERMARK_PDF_STAMPS', 1),
        'colour' => env('WATERMARK_PDF_COLOUR', '#2F5D9B'),
        'opacity' => (float) env('WATERMARK_PDF_OPACITY', 0.05),
        'blend_mode' => 'Multiply',
        'font_size_ratio' => 48 / 595,
        'min_font_size' => 8.0,
        'max_font_size' => 120.0,
        'line_height_ratio' => 1.0,
        'scan_characters' => 20,
        'scan_dpi' => 150,
        'scan_quality' => 60,
        'quality' => 'ebook',
        'lock' => (bool) env('WATERMARK_PDF_LOCK', true),
        'logo_width_ratio' => 0.6,
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
        'opacity' => (float) env('WATERMARK_IMAGE_OPACITY', 0.28),
        'font_size_ratio' => 0.045,
        'min_font_size' => 10.0,
        'max_font_size' => 90.0,
        'line_height_ratio' => 96 / 72,
        'max_edge' => 2000,
        'logo_width_ratio' => 0.4,
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

    /*
    |--------------------------------------------------------------------------
    | Logo
    |--------------------------------------------------------------------------
    |
    | The watermark is the Swiss Product Seal logo -- the fingerprint and tick
    | with the name beside it -- in the colour and opacity set above for PDFs
    | and pictures. It is the type's `logo_width_ratio` of the shorter side
    | wide: across most of a PDF page, smaller on a product picture, where
    | the article should stay the thing to look at. Each copy tilts it up to
    | `max_angle_degrees` either way and places it at random, so no one patch
    | over a known spot hides it. Its artwork is in resources/brand: the SVG
    | for PDFs, which draw it as shapes, and the white PNG for pictures.
    |
    */

    'logo' => [
        'enabled' => (bool) env('WATERMARK_LOGO', true),
        'max_angle_degrees' => 35.0,
    ],

];
