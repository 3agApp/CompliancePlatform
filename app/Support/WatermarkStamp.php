<?php

namespace App\Support;

use Random\Randomizer;

/**
 * Where the watermark stamps go on one page or picture, and how each one's
 * letters bend.
 *
 * The area is split into horizontal bands, one stamp per band, so stamps
 * never overlap yet still cover the whole of it. Each stamp gets a random
 * angle and a random position inside its band, which makes it impossible
 * to hide them all behind a single patch laid over a known spot. Its text
 * also follows a random wave, so no straight-line tool can select or erase
 * it in one stroke.
 *
 * Unit-agnostic: points on a PDF page, pixels on a picture. Positions are
 * measured from the area's top-left corner with y running down, and angles
 * turn anticlockwise as the reader sees them.
 *
 * @phpstan-type Curve array{amplitude: float, cycles: float, phase: float}
 * @phpstan-type Placement array{text: string, fontSize: float, angleDegrees: float, x: float, y: float, curve: Curve}
 * @phpstan-type Glyph array{text: string, fontSize: float, x: float, y: float, angleDegrees: float}
 * @phpstan-type MarkPlacement array{x: float, y: float, size: float, angleDegrees: float}
 */
class WatermarkStamp
{
    private const string ELLIPSIS = '...';

    public readonly float $nominalFontSize;

    /**
     * @param  array{stamps: int, font_size_ratio: float, min_font_size: float, max_font_size: float, line_height_ratio: float, mark_size_ratio?: float}  $settings
     */
    public function __construct(
        public readonly float $areaWidth,
        public readonly float $areaHeight,
        public readonly array $settings,
    ) {
        $this->nominalFontSize = min(
            $settings['max_font_size'],
            max($settings['min_font_size'], min($areaWidth, $areaHeight) * $settings['font_size_ratio']),
        );
    }

    /**
     * Lay out every stamp for the area and return its letters, ready to
     * draw: the centre of each glyph and the angle that keeps it upright on
     * its curve.
     *
     * @param  callable(string, float): float  $measure  The width of a text at a font size, in the area's units
     * @return array<int, Glyph>
     */
    public function glyphsFor(string $text, callable $measure, Randomizer $randomizer): array
    {
        $glyphs = [];

        foreach ($this->placements($text, $measure, $randomizer) as $placement) {
            array_push($glyphs, ...$this->glyphs($placement, $measure));
        }

        return $glyphs;
    }

    /**
     * Place the logo mark: its centre, how wide its square is, and its tilt.
     *
     * A share of the shorter side across, tilted a little either way and
     * dropped anywhere it wholly fits, so it moves from copy to copy like
     * the text does.
     *
     * @return MarkPlacement
     */
    public function markPlacement(Randomizer $randomizer): array
    {
        $size = min($this->areaWidth, $this->areaHeight) * ($this->settings['mark_size_ratio'] ?? 0.0);
        $maxAngle = (float) config('watermark.mark.max_angle_degrees');
        $angle = $this->randomBetween($randomizer, -$maxAngle, $maxAngle);

        /** Half the rotated square's bounding box, so the mark never runs off the edge. */
        $radians = deg2rad($angle);
        $reach = $size * (abs(cos($radians)) + abs(sin($radians))) / 2;

        return [
            'x' => $this->randomBetween($randomizer, $reach, $this->areaWidth - $reach),
            'y' => $this->randomBetween($randomizer, $reach, $this->areaHeight - $reach),
            'size' => $size,
            'angleDegrees' => $angle,
        ];
    }

    /**
     * Pick a random angle, fit the label to it and place it somewhere random
     * inside each band.
     *
     * @param  callable(string, float): float  $measure
     * @return array<int, Placement>
     */
    public function placements(string $text, callable $measure, Randomizer $randomizer): array
    {
        $bandCount = max(1, $this->settings['stamps']);
        $bandHeight = $this->areaHeight / $bandCount;
        $placements = [];

        for ($band = 0; $band < $bandCount; $band++) {
            $angle = $this->randomAngle($randomizer);
            $label = $this->fitLabel($text, $measure, $angle, $this->areaWidth, $bandHeight);

            if ($label['text'] === '') {
                continue;
            }

            $labelWidth = $measure($label['text'], $label['fontSize']);
            $curve = $this->randomCurve($randomizer, $labelWidth);
            [$boxWidth, $boxHeight] = $this->boundingBox($labelWidth, $label['fontSize'], $angle, $curve['amplitude']);
            $bandTop = $band * $bandHeight;

            $placements[] = [
                ...$label,
                'angleDegrees' => $angle,
                'curve' => $curve,
                'x' => $this->randomBetween($randomizer, $boxWidth / 2, $this->areaWidth - ($boxWidth / 2)),
                'y' => $this->randomBetween($randomizer, $bandTop + ($boxHeight / 2), $bandTop + $bandHeight - ($boxHeight / 2)),
            ];
        }

        return $placements;
    }

    /**
     * Longest label width whose rotated bounding box still fits inside the
     * coverage share of a box at the given font size and angle.
     *
     * The wave can swing the text up to `max_amplitude_ratio` of its width
     * to either side of the baseline, so that much is reserved on top of
     * the line height.
     */
    public function maxLabelWidth(float $fontSize, float $angleDegrees, float $boxWidth, float $boxHeight): float
    {
        $coverage = (float) config('watermark.stamp.coverage');
        $swing = 2 * $this->maxAmplitudeRatio();
        $lineHeight = $this->lineHeight($fontSize);
        $angle = deg2rad($angleDegrees);
        $cos = abs(cos($angle));
        $sin = abs(sin($angle));

        $limits = [];

        if ($cos + ($swing * $sin) > 1e-9) {
            $limits[] = (($boxWidth * $coverage) - ($lineHeight * $sin)) / ($cos + ($swing * $sin));
        }

        if ($sin + ($swing * $cos) > 1e-9) {
            $limits[] = (($boxHeight * $coverage) - ($lineHeight * $cos)) / ($sin + ($swing * $cos));
        }

        return $limits === [] ? 0.0 : max(0.0, min($limits));
    }

    /**
     * Width and height of the rotated label's bounding box, with room for
     * the wave to swing either side of the baseline.
     *
     * @return array{0: float, 1: float}
     */
    public function boundingBox(float $labelWidth, float $fontSize, float $angleDegrees, float $curveAmplitude = 0.0): array
    {
        $thickness = $this->lineHeight($fontSize) + (2 * $curveAmplitude);
        $angle = deg2rad($angleDegrees);
        $cos = abs(cos($angle));
        $sin = abs(sin($angle));

        return [
            ($labelWidth * $cos) + ($thickness * $sin),
            ($labelWidth * $sin) + ($thickness * $cos),
        ];
    }

    /**
     * Lay a placement's characters along its wave. Spaces only advance the
     * pen.
     *
     * @param  Placement  $placement
     * @param  callable(string, float): float  $measure
     * @return array<int, Glyph>
     */
    public function glyphs(array $placement, callable $measure): array
    {
        $characters = mb_str_split($placement['text']);
        $widths = array_map(fn (string $character): float => $measure($character, $placement['fontSize']), $characters);
        $path = $this->wavePath(array_sum($widths), $placement['curve']);

        $angle = deg2rad($placement['angleDegrees']);
        [$cos, $sin] = [cos($angle), sin($angle)];
        $glyphs = [];
        $pen = 0.0;

        foreach ($characters as $index => $character) {
            [$u, $v, $tangent] = $this->pointAt($path, $pen + ($widths[$index] / 2));
            $pen += $widths[$index];

            if (trim($character) === '') {
                continue;
            }

            /*
             * The y axis points down, so "along the text" is (cos, -sin) and
             * "up from the text" is (-sin, -cos).
             */
            $glyphs[] = [
                'text' => $character,
                'fontSize' => $placement['fontSize'],
                'x' => $placement['x'] + ($u * $cos) - ($v * $sin),
                'y' => $placement['y'] - ($u * $sin) - ($v * $cos),
                'angleDegrees' => $placement['angleDegrees'] + rad2deg($tangent),
            ];
        }

        return $glyphs;
    }

    /**
     * Pick the largest font size at or below the nominal size that fits the
     * label in the box, truncating it when even the smallest size overflows.
     *
     * @param  callable(string, float): float  $measure
     * @return array{text: string, fontSize: float}
     */
    public function fitLabel(string $text, callable $measure, float $angleDegrees, float $boxWidth, float $boxHeight): array
    {
        $minimumFontSize = $this->settings['min_font_size'];
        $step = max(0.5, $this->nominalFontSize / 100);

        for ($fontSize = $this->nominalFontSize; $fontSize > $minimumFontSize; $fontSize -= $step) {
            if ($measure($text, $fontSize) <= $this->maxLabelWidth($fontSize, $angleDegrees, $boxWidth, $boxHeight)) {
                return ['text' => $text, 'fontSize' => $fontSize];
            }
        }

        $available = $this->maxLabelWidth($minimumFontSize, $angleDegrees, $boxWidth, $boxHeight);

        return [
            'text' => $this->truncateToWidth($text, $minimumFontSize, $available, $measure),
            'fontSize' => $minimumFontSize,
        ];
    }

    /**
     * Get how tall a line of text at the given font size stands.
     */
    private function lineHeight(float $fontSize): float
    {
        return $fontSize * $this->settings['line_height_ratio'];
    }

    /**
     * @return Curve
     */
    private function randomCurve(Randomizer $randomizer, float $labelWidth): array
    {
        return [
            'amplitude' => $labelWidth * $this->randomBetween(
                $randomizer,
                min((float) config('watermark.stamp.curve.min_amplitude_ratio'), $this->maxAmplitudeRatio()),
                $this->maxAmplitudeRatio(),
            ),
            'cycles' => $this->randomBetween(
                $randomizer,
                (float) config('watermark.stamp.curve.min_cycles'),
                (float) config('watermark.stamp.curve.max_cycles'),
            ),
            'phase' => $this->randomBetween($randomizer, 0.0, 2 * M_PI),
        ];
    }

    private function maxAmplitudeRatio(): float
    {
        return max(0.0, (float) config('watermark.stamp.curve.max_amplitude_ratio'));
    }

    /**
     * Sample a sine wave whose length along the curve equals the label
     * width, centred on the origin so the label stays centred on its
     * placement.
     *
     * @param  Curve  $curve
     * @return array<int, array{u: float, v: float, s: float}> points with their distance along the curve
     */
    private function wavePath(float $labelWidth, array $curve): array
    {
        $samples = 200;
        $span = $labelWidth;
        $path = [];

        /** The curve is longer than its span, so shrink the span until it fits. */
        for ($pass = 0; $pass < 4; $pass++) {
            $path = [];
            $length = 0.0;

            for ($index = 0; $index <= $samples; $index++) {
                $u = $span * (($index / $samples) - 0.5);
                $v = $curve['amplitude'] * sin((2 * M_PI * $curve['cycles'] * $index / $samples) + $curve['phase']);

                if ($index > 0) {
                    $previous = $path[$index - 1];
                    $length += hypot($u - $previous['u'], $v - $previous['v']);
                }

                $path[] = ['u' => $u, 'v' => $v, 's' => $length];
            }

            if ($length <= 0.0 || abs($length - $labelWidth) < 0.01) {
                break;
            }

            $span *= $labelWidth / $length;
        }

        $vs = array_column($path, 'v');
        $middle = (max($vs) + min($vs)) / 2;

        return array_map(fn (array $point): array => [...$point, 'v' => $point['v'] - $middle], $path);
    }

    /**
     * The point at a distance along the path, and the path's direction
     * there.
     *
     * @param  array<int, array{u: float, v: float, s: float}>  $path
     * @return array{0: float, 1: float, 2: float}
     */
    private function pointAt(array $path, float $distance): array
    {
        $last = count($path) - 1;
        $index = 1;

        while ($index < $last && $path[$index]['s'] < $distance) {
            $index++;
        }

        $from = $path[$index - 1];
        $to = $path[$index];
        $segment = $to['s'] - $from['s'];
        $share = $segment > 0.0 ? max(0.0, min(1.0, ($distance - $from['s']) / $segment)) : 0.0;

        return [
            $from['u'] + (($to['u'] - $from['u']) * $share),
            $from['v'] + (($to['v'] - $from['v']) * $share),
            atan2($to['v'] - $from['v'], $to['u'] - $from['u']),
        ];
    }

    /**
     * A random tilt in either direction, kept away from horizontal and
     * vertical.
     */
    private function randomAngle(Randomizer $randomizer): float
    {
        $angle = $this->randomBetween(
            $randomizer,
            (float) config('watermark.stamp.min_angle_degrees'),
            (float) config('watermark.stamp.max_angle_degrees'),
        );

        return $randomizer->getInt(0, 1) === 1 ? $angle : -$angle;
    }

    private function randomBetween(Randomizer $randomizer, float $minimum, float $maximum): float
    {
        if ($maximum <= $minimum) {
            return ($minimum + $maximum) / 2;
        }

        return $randomizer->getFloat($minimum, $maximum);
    }

    /**
     * @param  callable(string, float): float  $measure
     */
    private function truncateToWidth(string $text, float $fontSize, float $available, callable $measure): string
    {
        if ($measure($text, $fontSize) <= $available) {
            return $text;
        }

        $length = mb_strlen($text);

        while ($length > 0) {
            $candidate = rtrim(mb_substr($text, 0, $length)).self::ELLIPSIS;

            if ($measure($candidate, $fontSize) <= $available) {
                return $candidate;
            }

            $length--;
        }

        return '';
    }
}
