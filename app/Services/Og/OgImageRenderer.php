<?php

declare(strict_types=1);

namespace App\Services\Og;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Renders 1200×630 Open Graph share cards with Imagick. Every card shares one
 * backdrop — a deep-space gradient, soft nebulae and a seeded starfield, so
 * the same card always renders the same bytes — and a left-aligned text column.
 *
 * - render(): a per-object card (lit sphere in the object's own colour).
 * - renderSite(): the site card, with a top-down solar system whose planets
 *   sit at their mean longitudes for the given date, plus catalogue figures.
 * - renderToday(): the object-of-the-day card, with a fun fact and key stats.
 *
 * Pure rendering — no I/O, no model access.
 */
final class OgImageRenderer
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const LEFT = 80;

    private const TEXT_WIDTH = 560;

    private const INK = '#eef0f6';

    private const MUTED = '#a4adc2';

    private const FAINT = '#7c859b';

    private const AMBER = '#e0b872';

    /**
     * J2000 mean longitude (deg), its rate (deg per Julian century), semi-major
     * axis (AU), drawn radius (px) and colour for the eight planets — enough to
     * place each within a few degrees of its true heliocentric longitude.
     */
    private const PLANETS = [
        'Mercury' => [252.25032, 149472.67411, 0.387, 4.0, '#a7a09a'],
        'Venus' => [181.97910, 58517.81539, 0.723, 6.0, '#e8b873'],
        'Earth' => [100.46457, 35999.37245, 1.000, 6.5, '#5b9be6'],
        'Mars' => [355.44657, 19140.30268, 1.524, 5.0, '#d0643c'],
        'Jupiter' => [34.39644, 3034.74613, 5.203, 15.0, '#d9b48c'],
        'Saturn' => [49.95424, 1222.49362, 9.537, 12.5, '#e6cf96'],
        'Uranus' => [313.23810, 428.48203, 19.189, 9.0, '#a6dde4'],
        'Neptune' => [304.87997, 218.45945, 30.070, 9.0, '#5a7fe8'],
    ];

    /** Scene geometry for the site card: Sun position, AU→px scale (on a^COMPRESSION) and the orbital plane's tilt. */
    private const SUN_X = 905;

    private const SUN_Y = 330;

    private const SCALE = 92.0;

    private const COMPRESSION = 0.45;

    private const FORESHORTEN = 0.38;

    private const PLANE_ROTATION_DEG = -14.0;

    /** @return string PNG binary */
    public function render(string $title, ?string $subtitle = null, ?string $discColour = null, bool $giant = false): string
    {
        $colour = $this->safeHex($discColour) ?? self::AMBER;
        $img = $this->backdrop(crc32($title), $colour);

        $this->glow($img, 930, 300, 330, $colour, 0.55);
        $this->sphere($img, 930, 300, 150, $colour, -0.55, -0.45, bands: $giant);
        $this->scrim($img);

        $size = $this->fitFontSize($img, $this->serif(), $title, 132, self::TEXT_WIDTH, 56);
        $this->text($img, $this->serif(), $size, self::INK, self::LEFT, 320, $title);

        if ($subtitle !== null && $subtitle !== '') {
            $this->text($img, $this->sans(), 36, self::MUTED, self::LEFT + 4, 385, $this->truncate($img, $this->sans(), 36, $subtitle, self::TEXT_WIDTH));
        }

        $this->rule($img, self::LEFT + 4, 450);
        $this->text($img, $this->sans(), 26, self::AMBER, self::LEFT + 4, 540, (string) config('site.name'));

        return $this->finish($img);
    }

    /**
     * @param  list<array{value: string, label: string}>  $figures  catalogue counts, shown left to right
     * @return string PNG binary
     */
    public function renderSite(string $name, string $tagline, array $figures, ?string $domain = null, ?CarbonInterface $at = null): string
    {
        $img = $this->backdrop(crc32($name), '#5d6fd6');
        $this->solarSystem($img, $at ?? CarbonImmutable::now('UTC'));
        $this->scrim($img);

        if ($domain !== null && $domain !== '') {
            $this->text($img, $this->sans(), 21, self::AMBER, self::LEFT + 3, 108, mb_strtoupper($domain), kerning: 4);
        }

        $size = $this->fitFontSize($img, $this->serif(), $name, 104, 640, 60);
        $this->text($img, $this->serif(), $size, self::INK, self::LEFT, 212, $name);
        $this->text($img, $this->sans(), 34, self::MUTED, self::LEFT + 3, 268, $this->truncate($img, $this->sans(), 34, $tagline, 640));

        if ($figures !== []) {
            $this->rule($img, self::LEFT + 3, 330);
            $this->figures($img, $figures, self::LEFT + 3, 410, 50, 17);
        }

        $this->text($img, $this->sans(), 21, self::FAINT, self::LEFT + 3, 560, __('Free open data · REST API · MCP server · Rebuilt nightly'));

        return $this->finish($img);
    }

    /**
     * @param  list<array{value: string, label: string}>  $stats
     * @return string PNG binary
     */
    public function renderToday(
        string $kicker,
        string $title,
        ?string $subtitle,
        ?string $fact,
        array $stats,
        ?string $colour,
        string $footer,
        bool $rings = false,
        bool $giant = false,
    ): string {
        $colour = $this->safeHex($colour) ?? self::AMBER;
        $img = $this->backdrop(crc32($title.$kicker), $colour);

        $cx = 940;
        $cy = 300;
        $radius = $rings ? 128 : 165;
        $this->glow($img, $cx, $cy, (int) ($radius * 2.1), $colour, 0.5);
        if ($rings) {
            $this->rings($img, $cx, $cy, $radius, $colour, back: true);
        }
        $this->sphere($img, $cx, $cy, $radius, $colour, -0.55, -0.45, bands: $giant);
        if ($rings) {
            $this->rings($img, $cx, $cy, $radius, $colour, back: false);
        }
        $this->scrim($img);

        $this->text($img, $this->sans(), 21, self::AMBER, self::LEFT + 3, 96, mb_strtoupper($kicker), kerning: 3);

        $size = $this->fitFontSize($img, $this->serif(), $title, 104, self::TEXT_WIDTH, 52);
        $this->text($img, $this->serif(), $size, self::INK, self::LEFT, 196, $title);

        if ($subtitle !== null && $subtitle !== '') {
            $this->text($img, $this->sans(), 28, self::MUTED, self::LEFT + 3, 246, $this->truncate($img, $this->sans(), 28, $subtitle, self::TEXT_WIDTH));
        }

        $y = 318;
        if ($fact !== null && $fact !== '') {
            foreach ($this->wrap($img, $this->sans(), 29, $fact, self::TEXT_WIDTH, 3) as $line) {
                $this->text($img, $this->sans(), 29, self::INK, self::LEFT + 3, $y, $line);
                $y += 41;
            }
        }

        if ($stats !== []) {
            $this->figures($img, $stats, self::LEFT + 3, max(440, $y + 46), 30, 15);
        }

        $this->text($img, $this->sans(), 21, self::AMBER, self::LEFT + 3, 574, $footer);

        return $this->finish($img);
    }

    // -- Scene pieces ---------------------------------------------------------

    /** Deep-space gradient, two soft nebulae (one tinted) and a seeded starfield. */
    private function backdrop(int $seed, string $tint): Imagick
    {
        $img = new Imagick;
        $img->newPseudoImage(self::WIDTH, self::HEIGHT, 'gradient:#0b1124-#04060c');
        $img->setImageFormat('png');

        $this->glow($img, 1040, 80, 520, '#3a4fa8', 0.45);
        $this->glow($img, 640, 640, 560, '#5b2f86', 0.3);
        $this->glow($img, 1150, 560, 420, $tint, 0.22);

        $random = new Randomizer(new Mt19937($seed));
        $draw = new ImagickDraw;
        for ($i = 0; $i < 900; $i++) {
            $x = $random->getInt(0, self::WIDTH * 100) / 100;
            $y = $random->getInt(0, self::HEIGHT * 100) / 100;
            $roll = $random->getInt(0, 999);
            $alpha = $roll < 940 ? 0.15 + $roll / 940 * 0.45 : 0.85;
            $r = $roll < 940 ? 0.55 : 1.1 + ($roll - 940) / 60;
            $warm = $random->getInt(0, 5) === 0;
            $draw->setFillColor(new ImagickPixel($warm ? "rgba(255,226,190,{$alpha})" : "rgba(220,230,255,{$alpha})"));
            $draw->circle($x, $y, $x + $r, $y);
            if ($roll >= 985) {
                $draw->setFillColor(new ImagickPixel('rgba(200,215,255,0.12)'));
                $draw->circle($x, $y, $x + $r * 3.2, $y);
            }
        }
        $img->drawImage($draw);

        return $img;
    }

    /**
     * A soft radial glow. The gradient runs to pure black and is composited with
     * SCREEN, so the square sprite has no visible edge on the dark backdrop.
     */
    private function glow(Imagick $img, int $x, int $y, int $radius, string $colour, float $strength): void
    {
        $glow = new Imagick;
        $glow->newPseudoImage($radius * 2, $radius * 2, 'radial-gradient:'.$this->scale($colour, $strength).'-black');
        $img->compositeImage($glow, Imagick::COMPOSITE_SCREEN, $x - $radius, $y - $radius);
        $glow->clear();
    }

    /**
     * A lit sphere: off-centre radial shading towards $lightX/$lightY (a unit-ish
     * vector), a deepened night side, optional cloud bands and a faint atmosphere.
     */
    private function sphere(Imagick $img, int $x, int $y, int $radius, string $colour, float $lightX, float $lightY, bool $bands = false): void
    {
        $d = $radius * 2;
        $span = $radius * 3;
        $shade = new Imagick;
        $shade->newPseudoImage($span, $span, 'radial-gradient:'.$this->mix($colour, '#ffffff', 0.4).'-'.$this->scale($colour, 0.04));
        $shade->cropImage($d, $d, (int) round($radius * (0.5 - 0.45 * $lightX)), (int) round($radius * (0.5 - 0.45 * $lightY)));
        $shade->setImagePage(0, 0, 0, 0);
        $shade->sigmoidalContrastImage(true, 3.0, 0.42 * Imagick::getQuantum());

        if ($bands && $radius >= 20) {
            $random = new Randomizer(new Mt19937(crc32($colour)));
            $stripes = new ImagickDraw;
            for ($band = $radius * 0.18; $band < $d; $band += $radius * (0.1 + $random->getInt(0, 12) / 100)) {
                $tone = $random->getInt(0, 1) === 0 ? '#ffffff' : '#000000';
                $stripes->setFillColor(new ImagickPixel($this->rgba($tone, 0.02 + $random->getInt(0, 5) / 100)));
                $stripes->rectangle(0, $band, $d, $band + $radius * (0.03 + $random->getInt(0, 7) / 100));
            }
            $shade->drawImage($stripes);
            $shade->blurImage(0, max(1.0, $radius / 35));
        }

        $mask = new Imagick;
        $mask->newImage($d, $d, new ImagickPixel('transparent'));
        $circle = new ImagickDraw;
        $circle->setFillColor(new ImagickPixel('white'));
        $circle->circle($radius, $radius, $radius, 0.5);
        $mask->drawImage($circle);

        $shade->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $shade->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);
        $img->compositeImage($shade, Imagick::COMPOSITE_OVER, $x - $radius, $y - $radius);
        $shade->clear();
        $mask->clear();

        if ($radius >= 20) {
            $rim = new ImagickDraw;
            $rim->setFillOpacity(0);
            $rim->setStrokeColor(new ImagickPixel($this->rgba($this->mix($colour, '#ffffff', 0.6), 0.16)));
            $rim->setStrokeWidth($radius / 45);
            $rim->setStrokeAntialias(true);
            $rim->circle($x, $y, $x + $radius, $y);
            $img->drawImage($rim);
        }
    }

    /** Ring system around a sphere; the back half is drawn before the planet, the front half after. */
    private function rings(Imagick $img, int $x, int $y, int $radius, string $colour, bool $back): void
    {
        $draw = new ImagickDraw;
        $draw->translate($x, $y);
        $draw->rotate(-16);
        $draw->setFillOpacity(0);
        $draw->setStrokeAntialias(true);
        [$start, $end] = $back ? [180, 360] : [0, 180];
        $ringColour = $this->mix($colour, '#fff4dc', 0.35);
        foreach ([[1.30, 0.20, 3], [1.45, 0.45, 9], [1.62, 0.55, 12], [1.80, 0.0, 0], [1.93, 0.4, 9], [2.08, 0.25, 6]] as [$scale, $alpha, $width]) {
            if ($alpha === 0.0) {
                continue;
            }
            $draw->setStrokeColor(new ImagickPixel($this->rgba($ringColour, $alpha)));
            $draw->setStrokeWidth($width);
            $draw->ellipse(0, 0, $radius * $scale, $radius * $scale * 0.26, $start, $end);
        }
        $img->drawImage($draw);
    }

    /** Top-down solar system with the Sun, belts, orbits and planets at their mean longitudes on $at. */
    private function solarSystem(Imagick $img, CarbonInterface $at): void
    {
        $centuries = ($at->getTimestamp() / 86400 + 2440587.5 - 2451545.0) / 36525;
        $random = new Randomizer(new Mt19937(0x5017A12));

        $belts = new ImagickDraw;
        foreach ([[2.1, 3.3, 1100, '#c8b597', 0.4], [32.0, 48.0, 1500, '#9fb3d9', 0.28]] as [$inner, $outer, $count, $tone, $maxAlpha]) {
            for ($i = 0; $i < $count; $i++) {
                $a = $inner + ($outer - $inner) * $random->getInt(0, 10000) / 10000;
                [$px, $py] = $this->project($a, $random->getInt(0, 36000) / 100);
                $belts->setFillColor(new ImagickPixel($this->rgba($tone, 0.08 + $maxAlpha * $random->getInt(0, 100) / 100)));
                $belts->circle($px, $py, $px + 0.7, $py);
            }
        }
        $img->drawImage($belts);

        $orbits = new ImagickDraw;
        $orbits->translate(self::SUN_X, self::SUN_Y);
        $orbits->rotate(self::PLANE_ROTATION_DEG);
        $orbits->setFillOpacity(0);
        $orbits->setStrokeAntialias(true);
        foreach (self::PLANETS as $planet => [, , $au]) {
            $rx = self::SCALE * $au ** self::COMPRESSION;
            $orbits->setStrokeColor(new ImagickPixel($planet === 'Earth' ? 'rgba(110,160,240,0.55)' : 'rgba(170,188,230,0.24)'));
            $orbits->setStrokeWidth($planet === 'Earth' ? 1.6 : 1.2);
            $orbits->ellipse(0, 0, $rx, $rx * self::FORESHORTEN, 0, 360);
        }
        $img->drawImage($orbits);

        $this->glow($img, self::SUN_X, self::SUN_Y, 150, '#f2a54a', 0.7);
        $this->glow($img, self::SUN_X, self::SUN_Y, 52, '#fff1c9', 0.95);
        $this->sphere($img, self::SUN_X, self::SUN_Y, 22, '#ffd98a', 0.0, 0.0);

        foreach (self::PLANETS as $planet => [$l0, $rate, $au, $radius, $colour]) {
            $longitude = fmod($l0 + $rate * $centuries, 360.0);
            [$px, $py] = $this->project($au, $longitude);
            $toSunX = self::SUN_X - $px;
            $toSunY = self::SUN_Y - $py;
            $length = max(1.0, hypot($toSunX, $toSunY));
            $this->glow($img, (int) $px, (int) $py, (int) ($radius * 3), $colour, 0.35);
            if ($planet === 'Saturn') {
                $this->rings($img, (int) $px, (int) $py, (int) $radius, $colour, back: true);
            }
            $this->sphere($img, (int) $px, (int) $py, (int) $radius, $colour, $toSunX / $length, $toSunY / $length, bands: $radius >= 9);
            if ($planet === 'Saturn') {
                $this->rings($img, (int) $px, (int) $py, (int) $radius, $colour, back: false);
            }
        }
    }

    /**
     * Canvas position of a point on a circular orbit of $au at ecliptic longitude
     * $deg, with distance compressed on a^0.45 so Mercury and Neptune share a frame.
     *
     * @return array{0: float, 1: float}
     */
    private function project(float $au, float $deg): array
    {
        $rx = self::SCALE * $au ** self::COMPRESSION;
        $t = deg2rad($deg);
        $lx = $rx * cos($t);
        $ly = -$rx * self::FORESHORTEN * sin($t);
        $rot = deg2rad(self::PLANE_ROTATION_DEG);

        return [
            self::SUN_X + $lx * cos($rot) - $ly * sin($rot),
            self::SUN_Y + $lx * sin($rot) + $ly * cos($rot),
        ];
    }

    /** Darkens the left of the card so the text column always reads against the scene. */
    private function scrim(Imagick $img): void
    {
        $scrim = new Imagick;
        $scrim->setOption('gradient:direction', 'East');
        $scrim->newPseudoImage(760, self::HEIGHT, 'gradient:rgba(4,6,12,0.88)-rgba(4,6,12,0)');
        $img->compositeImage($scrim, Imagick::COMPOSITE_OVER, 0, 0);
        $scrim->clear();
    }

    // -- Typography -------------------------------------------------------------

    /**
     * Large values with small uppercase labels beneath, laid out left to right.
     *
     * @param  list<array{value: string, label: string}>  $figures
     */
    private function figures(Imagick $img, array $figures, int $x, int $y, int $valueSize, int $labelSize): void
    {
        $gap = $valueSize >= 40 ? 56 : 44;
        foreach ($figures as $figure) {
            $label = mb_strtoupper($figure['label']);
            $this->text($img, $this->serif(), $valueSize, self::INK, $x, $y, $figure['value']);
            $this->text($img, $this->sans(), $labelSize, self::MUTED, $x + 1, $y + (int) ($labelSize * 2.1), $label, kerning: 2);
            $width = max(
                $this->measure($img, $this->serif(), $valueSize, $figure['value']),
                $this->measure($img, $this->sans(), $labelSize, $label, kerning: 2),
            );
            $x += (int) ceil($width) + $gap;
            if ($x > self::LEFT + 680) {
                break;
            }
        }
    }

    private function text(Imagick $img, string $font, int $size, string $colour, int $x, int $y, string $text, float $kerning = 0.0): void
    {
        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($size);
        $draw->setFillColor(new ImagickPixel($colour));
        $draw->setTextAntialias(true);
        if ($kerning !== 0.0) {
            $draw->setTextKerning($kerning);
        }
        $img->annotateImage($draw, $x, $y, 0, $text);
    }

    private function rule(Imagick $img, int $x, int $y): void
    {
        $rule = new ImagickDraw;
        $rule->setStrokeColor(new ImagickPixel(self::AMBER));
        $rule->setStrokeWidth(3);
        $rule->line($x, $y, $x + 120, $y);
        $img->drawImage($rule);
    }

    private function measure(Imagick $img, string $font, int $size, string $text, float $kerning = 0.0): float
    {
        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($size);
        if ($kerning !== 0.0) {
            $draw->setTextKerning($kerning);
        }

        return (float) $img->queryFontMetrics($draw, $text)['textWidth'];
    }

    /** Shrink the font size until the text fits within $maxWidth. */
    private function fitFontSize(Imagick $img, string $font, string $text, int $start, int $maxWidth, int $min): int
    {
        $size = $start;
        while ($size > $min && $this->measure($img, $font, $size, $text) > $maxWidth) {
            $size -= 4;
        }

        return max($size, $min);
    }

    private function truncate(Imagick $img, string $font, int $size, string $text, int $maxWidth): string
    {
        if ($this->measure($img, $font, $size, $text) <= $maxWidth) {
            return $text;
        }

        while (mb_strlen($text) > 1) {
            $text = mb_substr($text, 0, -1);
            if ($this->measure($img, $font, $size, rtrim($text).'…') <= $maxWidth) {
                break;
            }
        }

        return rtrim($text).'…';
    }

    /**
     * Greedy word wrap; the last allowed line is truncated with an ellipsis.
     *
     * @return list<string>
     */
    private function wrap(Imagick $img, string $font, int $size, string $text, int $maxWidth, int $maxLines): array
    {
        $lines = [];
        $line = '';
        $words = preg_split('/\s+/', trim($text)) ?: [];
        foreach ($words as $index => $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($line === '' || $this->measure($img, $font, $size, $candidate) <= $maxWidth) {
                $line = $candidate;

                continue;
            }
            if (count($lines) === $maxLines - 1) {
                $rest = implode(' ', array_slice($words, $index));

                return [...$lines, $this->truncate($img, $font, $size, $line.' '.$rest, $maxWidth)];
            }
            $lines[] = $line;
            $line = $word;
        }

        return $line === '' ? $lines : [...$lines, $this->truncate($img, $font, $size, $line, $maxWidth)];
    }

    // -- Utilities --------------------------------------------------------------

    private function finish(Imagick $img): string
    {
        $img->setImageFormat('png');
        $img->setImageDepth(8);
        $img->setOption('png:compression-level', '9');
        $img->stripImage();
        $png = $img->getImageBlob();
        $img->clear();

        return $png;
    }

    private function serif(): string
    {
        return (string) config('og.fonts.serif');
    }

    private function sans(): string
    {
        return (string) config('og.fonts.sans');
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    /** The colour scaled towards black by $factor (0–1), as #rrggbb. */
    private function scale(string $hex, float $factor): string
    {
        return vsprintf('#%02x%02x%02x', array_map(static fn (int $c): int => (int) round(min(255, $c * $factor)), $this->rgb($hex)));
    }

    /** Linear blend of two colours, $amount of the way from $a to $b. */
    private function mix(string $a, string $b, float $amount): string
    {
        $from = $this->rgb($a);
        $to = $this->rgb($b);

        return vsprintf('#%02x%02x%02x', array_map(
            static fn (int $c, int $d): int => (int) round($c + ($d - $c) * $amount),
            $from,
            $to,
        ));
    }

    private function rgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = $this->rgb($hex);

        return "rgba({$r},{$g},{$b},".round($alpha, 3).')';
    }

    private function safeHex(?string $hex): ?string
    {
        return ($hex !== null && preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) ? strtolower($hex) : null;
    }
}
