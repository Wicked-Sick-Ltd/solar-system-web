<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Og\CatalogueFigures;
use App\Services\Og\OgImageRenderer;
use App\Services\SolarApi\Data\ObjectDetail;
use App\Services\SolarApi\SolarApiClient;
use App\Support\ObjectHighlights;
use App\Support\ObjectOfTheDay;
use App\Support\ObjectType;
use App\Support\ShareImage;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Serves Open Graph share cards: per object, per object-of-the-day date and
 * the site card. Each is rendered once and cached on the configured disk
 * (Ceph RGW S3 in production); subsequent hits stream it straight back. The
 * app serves the bytes itself so the bucket stays private, and the public URL
 * lives on our own domain.
 *
 * It never errors out: a missing object or a render failure falls back to the
 * committed static site card, so a crawler always gets a valid image.
 */
final class OgImageController extends Controller
{
    /** Bodies whose rings are bright enough to draw at card scale. */
    private const RINGED = ['planet-saturn'];

    /** Above this diameter a body is drawn as a banded giant planet. */
    private const GIANT_DIAMETER_KM = 40000.0;

    /** The site card follows the nightly catalogue counts, so it can't be immutable. */
    private const SITE_MAX_AGE = 86400;

    public function object(string $slug, SolarApiClient $api, OgImageRenderer $renderer): Response
    {
        return $this->cached(sha1($slug).'.png', function () use ($slug, $api, $renderer): ?string {
            $object = $api->object($slug);
            if (! $object instanceof ObjectDetail) {
                return null;
            }

            return $renderer->render(
                title: $object->name,
                subtitle: $this->subtitle($object),
                discColour: $object->visual?->safeColourHex(),
                giant: $this->isGiant($object),
            );
        });
    }

    public function today(string $date, SolarApiClient $api, OgImageRenderer $renderer): Response
    {
        $day = ObjectOfTheDay::parse($date);
        if ($day === null) {
            return $this->fallback();
        }

        return $this->cached('today/'.$day->format('Y-m-d').'.png', function () use ($day, $api, $renderer): ?string {
            $object = $api->object(ObjectOfTheDay::slugFor($day));
            if (! $object instanceof ObjectDetail) {
                return null;
            }

            return $renderer->renderToday(
                kicker: __('Object of the day').' · '.$day->format('D j M Y'),
                title: $object->name,
                subtitle: $this->subtitle($object),
                fact: ObjectHighlights::funFact($object),
                stats: ObjectHighlights::keyStats($object),
                colour: $object->visual?->safeColourHex(),
                footer: ShareImage::printable(ObjectOfTheDay::url($day)),
                rings: in_array($object->id, self::RINGED, true),
                giant: $this->isGiant($object),
            );
        });
    }

    public function site(CatalogueFigures $catalogue, OgImageRenderer $renderer): Response
    {
        try {
            $figures = $catalogue->all();
        } catch (Throwable) {
            $figures = [];
        }

        // Without live counts the committed card (rendered with them) is the better image.
        if ($figures === []) {
            return $this->fallback();
        }

        return $this->cached('site/'.sha1((string) json_encode($figures)).'.png', fn (): string => $renderer->renderSite(
            name: (string) config('site.name'),
            tagline: (string) config('site.tagline'),
            figures: $figures,
            domain: ShareImage::domain(),
        ), self::SITE_MAX_AGE);
    }

    /** @param  Closure(): ?string  $render  PNG bytes, or null when there is nothing to draw */
    private function cached(string $name, Closure $render, ?int $maxAge = null): Response
    {
        $disk = Storage::disk((string) config('og.disk'));
        $path = 'og/'.ShareImage::version().'/'.$name;

        try {
            if ($disk->exists($path) && ($cached = $disk->get($path)) !== null) {
                return $this->png($cached, $maxAge);
            }

            $png = $render();
            if ($png === null) {
                return $this->fallback();
            }

            $disk->put($path, $png);

            return $this->png($png, $maxAge);
        } catch (Throwable) {
            return $this->fallback();
        }
    }

    private function subtitle(ObjectDetail $object): string
    {
        $parts = array_filter([
            $object->typeLabel(),
            $object->designation !== $object->name ? $object->designation : null,
        ]);

        return implode(' · ', $parts) ?: ObjectType::label('star');
    }

    private function isGiant(ObjectDetail $object): bool
    {
        return ($object->physical?->diameterKm() ?? 0.0) > self::GIANT_DIAMETER_KM;
    }

    private function png(string $bytes, ?int $maxAge = null): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => $maxAge === null
                ? 'public, max-age='.(int) config('og.ttl').', immutable'
                : 'public, max-age='.$maxAge,
        ]);
    }

    /** The committed static site card, used whenever a live render isn't possible. */
    private function fallback(): Response
    {
        $bytes = @file_get_contents(public_path(ShareImage::DEFAULT_PATH)) ?: '';

        return response($bytes, $bytes === '' ? 404 : 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
