<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\SkyPosition;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Services\Weather\Data\WeatherOutlook;
use App\Services\Weather\OpenMeteoClient;
use App\Services\What3Words\What3WordsClient;
use App\Services\What3Words\What3WordsException;
use App\Support\LocationParser;
use Illuminate\Contracts\View\View;
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

    public ?float $lat = null;

    public ?float $lon = null;

    public bool $failed = false;

    /** The pasted location text (coordinates, a Google Maps link, or a what3words address). */
    public string $text = '';

    public function mount(string $objectId): void
    {
        $this->objectId = $objectId;
    }

    public function setLocation(float $lat, float $lon): void
    {
        $this->lat = $lat;
        $this->lon = $lon;
        $this->failed = false;

        $this->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);
    }

    /**
     * One paste box, three inputs: decimal or DMS coordinates, a Google Maps
     * link, or a what3words address (only when a key is configured — the
     * words are sent to what3words to convert them, which the panel says).
     */
    public function setFromText(string $text, ?What3WordsClient $w3w = null): void
    {
        $this->text = trim($text);
        $this->resetErrorBag('text');
        $w3w ??= app(What3WordsClient::class);

        if (($words = LocationParser::what3words($this->text)) !== null) {
            if (! $w3w->enabled()) {
                $this->addError('text', __('what3words addresses aren\'t available here — paste coordinates instead.'));

                return;
            }

            $key = 'w3w:'.(request()->ip() ?? 'unknown');
            if (RateLimiter::tooManyAttempts($key, self::LOOKUPS_PER_MINUTE)) {
                $this->addError('text', __('Too many what3words lookups — please try again in a minute, or paste coordinates.'));

                return;
            }
            RateLimiter::hit($key, 60);

            try {
                $coords = $w3w->toCoordinates($words);
            } catch (What3WordsException $e) {
                $this->addError('text', $e->getMessage());

                return;
            }
            $this->setLocation($coords['lat'], $coords['lon']);

            return;
        }

        $coords = LocationParser::parse($this->text);
        if ($coords === null) {
            $this->addError('text', __('Sorry, we couldn\'t read that. Try "51.51, -0.13", a Google Maps link, or ///three.word.address.'));

            return;
        }

        $this->setLocation($coords['lat'], $coords['lon']);
    }

    public function forget(): void
    {
        $this->lat = null;
        $this->lon = null;
        $this->failed = false;
        $this->resetErrorBag();
    }

    public function render(SolarApiClient $api): View
    {
        $sky = null;
        $weather = null;
        $kitReadyNudge = null;
        if ($this->lat !== null && $this->lon !== null && $this->getErrorBag()->isEmpty()) {
            try {
                $sky = $api->sky($this->objectId, null, $this->lat, $this->lon);
            } catch (SolarApiException) {
                $this->failed = true;
            }
            $this->failed = $this->failed || ! $sky instanceof SkyPosition || $sky->observer === null;

            if (! $this->failed && $sky->observer !== null) {
                $weather = app(OpenMeteoClient::class)->tonightOutlook(
                    $this->lat,
                    $this->lon,
                    $this->bestHourForOutlook($sky),
                );
                $kitReadyNudge = $this->kitReadyNudge($sky, $weather);
            }
        }

        return view('livewire.sky-observer', [
            'sky' => $sky,
            'weather' => $weather,
            'kitReadyNudge' => $kitReadyNudge,
            'what3words' => app(What3WordsClient::class)->enabled(),
        ]);
    }

    private function bestHourForOutlook(SkyPosition $sky): ?string
    {
        $observer = $sky->observer;
        if ($observer === null) {
            return null;
        }

        return $observer->transitUtc ?? $observer->riseUtc ?? $observer->setUtc;
    }

    private function kitReadyNudge(SkyPosition $sky, ?WeatherOutlook $weather): ?string
    {
        if ($weather === null || $sky->observer === null) {
            return null;
        }
        if ($weather->verdict !== __('Clear')) {
            return null;
        }

        $name = $sky->name ?? __('This object');
        $from = $sky->observer->riseUtc;

        if ($from !== null) {
            return __('Clear tonight from your location; :name up from :time. Get the kit out.', [
                'name' => $name,
                'time' => $from,
            ]);
        }

        return __('Clear tonight from your location; :name is observable. Get the kit out.', ['name' => $name]);
    }
}
