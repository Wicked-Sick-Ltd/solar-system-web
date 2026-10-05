<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Og\OgImageRenderer;
use App\Services\SolarApi\Data\ObjectDetail;
use App\Services\SolarApi\SolarApiClient;
use App\Support\ObjectType;
use App\Support\ShareImage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Serves a per-object Open Graph share card. The card is rendered once and
 * cached on the configured disk (Ceph RGW S3 in production); subsequent hits
 * stream it straight back. The app serves the bytes itself so the bucket stays
 * private, and the public URL lives on our own domain.
 *
 * It never errors out: a missing object or a render failure falls back to the
 * static site card, so a crawler always gets a valid image.
 */
final class OgImageController extends Controller
{
    public function __invoke(string $slug, SolarApiClient $api, OgImageRenderer $renderer): Response
    {
        $disk = Storage::disk((string) config('og.disk'));
        $path = 'og/'.ShareImage::version().'/'.sha1($slug).'.png';

        try {
            if ($disk->exists($path) && ($cached = $disk->get($path)) !== null) {
                return $this->png($cached);
            }

            $object = $api->object($slug);
            if (! $object instanceof ObjectDetail) {
                return $this->fallback();
            }

            $png = $renderer->render(
                title: $object->name,
                subtitle: $this->subtitle($object),
                discColour: $object->visual?->safeColourHex(),
            );

            $disk->put($path, $png);

            return $this->png($png);
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

    private function png(string $bytes): Response
    {
        return response($bytes)->withHeaders(['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=' . (int) config('og.ttl') . ', immutable']);
    }

    /** The committed static site card, used whenever a per-object render isn't possible. */
    private function fallback(): Response
    {
        $bytes = @file_get_contents(public_path(ShareImage::DEFAULT_PATH)) ?: '';

        return response($bytes, $bytes === '' ? 404 : 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
