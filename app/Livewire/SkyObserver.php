<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\VisibilityAlert;
use App\Services\SolarApi\Data\SkyPosition;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Services\Weather\OpenMeteoClient;
use App\Services\What3Words\What3WordsClient;
use App\Services\What3Words\What3WordsException;
use App\Support\LocationParser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The observer half of the "In the sky" panel: alt/az, whether it's up after
 * dark, and rise/transit/set for the visitor's own location. The location is
 * obtained by the browser (geolocation or typed in) and remembered only in
 * localStorage; the server sees it just for the calculation.
 */
final class SkyObserver extends Component
{
    /**
     * Per-IP ceiling on what3words conversions in a minute. Nothing is cached
     * to absorb repeats, so this is the only bound on the quota an anonymous
     * caller can spend; the counter holds an IP and a tally, never the words.
     */
    private const LOOKUPS_PER_MINUTE = 20;

    #[Locked]
    public string $objectId;

    public mixed $lat = null;

    public mixed $lon = null;

    public bool $failed = false;

    public bool $alertSaved = false;

    /** The pasted location text (coordinates, a Google Maps link, or a what3words address). */
    public string $text = '';

    public function mount(string $objectId): void
    {
        $this->objectId = $objectId;
    }

    /** @return array{lat:float,lon:float} */
    public function setLocation(mixed $lat, mixed $lon): array
    {
        $this->lat = $lat;
        $this->lon = $lon;
        $this->failed = false;
        $this->alertSaved = false;
        $this->resetErrorBag(['text', 'lat', 'lon']);

        $this->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $this->lat = round((float) $this->lat, 2);
        $this->lon = round((float) $this->lon, 2);

        return ['lat' => $this->lat, 'lon' => $this->lon];
    }

    /**
     * One paste box, three inputs: decimal or DMS coordinates, a Google Maps
     * link, or a what3words address (only when a key is configured — the
     * words are sent to what3words to convert them, which the panel says).
     *
     * @return array{lat:float,lon:float}|null
     */
    public function setFromText(string $text, ?What3WordsClient $w3w = null): ?array
    {
        $this->text = trim($text);
        $this->resetErrorBag('text');
        $w3w ??= app(What3WordsClient::class);

        if (($words = LocationParser::what3words($this->text)) !== null) {
            if (! $w3w->enabled()) {
                $this->addError('text', __('what3words addresses aren\'t available here — paste coordinates instead.'));

                return null;
            }

            $key = 'w3w:'.(request()->ip() ?? 'unknown');
            if (RateLimiter::tooManyAttempts($key, self::LOOKUPS_PER_MINUTE)) {
                $this->addError('text', __('Too many what3words lookups — please try again in a minute, or paste coordinates.'));

                return null;
            }
            RateLimiter::hit($key, 60);

            try {
                $coords = $w3w->toCoordinates($words);
            } catch (What3WordsException $e) {
                $this->addError('text', $e->getMessage());

                return null;
            }

            return $this->setLocation($coords['lat'], $coords['lon']);
        }

        $coords = LocationParser::parse($this->text);
        if ($coords === null) {
            $this->addError('text', $w3w->enabled()
                ? __('Sorry, we couldn\'t read that. Try "51.51, -0.13", a Google Maps link, or ///three.word.address.')
                : __('Sorry, we couldn\'t read that. Try "51.51, -0.13" or a Google Maps link.'));

            return null;
        }

        return $this->setLocation($coords['lat'], $coords['lon']);
    }

    public function forget(): void
    {
        $this->text = '';
        $this->lat = null;
        $this->lon = null;
        $this->failed = false;
        $this->alertSaved = false;
        $this->resetErrorBag();
    }

    public function saveAlert(): void
    {
        if (! Auth::check()) {
            $this->addError('alert', __('Please sign in to save alerts.'));

            return;
        }

        $this->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        VisibilityAlert::query()->updateOrCreate(
            [
                'user_id' => Auth::id(),
                'object_id' => $this->objectId,
                'latitude' => round((float) $this->lat, 2),
                'longitude' => round((float) $this->lon, 2),
            ],
            [
                'active' => true,
            ],
        );

        $this->resetErrorBag('alert');
        $this->alertSaved = true;
    }

    public function removeAlert(): void
    {
        if (! Auth::check() || ! $this->validCoordinates()) {
            return;
        }

        VisibilityAlert::query()
            ->where('user_id', Auth::id())
            ->where('object_id', $this->objectId)
            ->where('latitude', round((float) $this->lat, 2))
            ->where('longitude', round((float) $this->lon, 2))
            ->delete();

        $this->alertSaved = false;
    }

    public function render(SolarApiClient $api): View
    {
        $sky = null;
        $weather = null;
        // Both public properties can be changed directly in a Livewire request.
        // Validate before any API, weather or saved-alert lookup, not only in actions.
        $validLocation = $this->validCoordinates();
        $invalidLocation = ! $validLocation && ($this->lat !== null || $this->lon !== null);
        $this->failed = false;
        $this->alertSaved = false;
        if ($validLocation && ! $this->getErrorBag()->hasAny(['text', 'lat', 'lon'])) {
            try {
                $sky = $api->sky($this->objectId, null, (float) $this->lat, (float) $this->lon);
            } catch (SolarApiException) {
                $this->failed = true;
            }
            $this->failed = ! $sky instanceof SkyPosition || $sky->observer === null;

            if (! $this->failed && $sky->observer !== null) {
                $weather = app(OpenMeteoClient::class)->tonightOutlook(
                    (float) $this->lat,
                    (float) $this->lon,
                    $this->forecastReferenceTime($sky),
                );
            }
        }

        if (Auth::check() && $validLocation) {
            $this->alertSaved = VisibilityAlert::query()
                ->where('user_id', Auth::id())
                ->where('object_id', $this->objectId)
                ->where('latitude', round((float) $this->lat, 2))
                ->where('longitude', round((float) $this->lon, 2))
                ->exists();
        }

        return view('livewire.sky-observer', [
            'sky' => $sky,
            'weather' => $weather,
            'invalidLocation' => $invalidLocation,
            'what3words' => app(What3WordsClient::class)->enabled(),
        ]);
    }

    private function forecastReferenceTime(SkyPosition $sky): ?string
    {
        $observer = $sky->observer;
        if ($observer === null) {
            return null;
        }

        return $observer->transitUtc ?? $observer->riseUtc ?? $observer->setUtc;
    }

    private function validCoordinates(): bool
    {
        foreach (['lat' => 90, 'lon' => 180] as $field => $limit) {
            $value = $this->{$field};
            if (! is_numeric($value) || ! is_finite((float) $value) || abs((float) $value) > $limit) {
                return false;
            }
        }

        return true;
    }
}
