<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\SkyPosition;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Contracts\View\View;
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
    #[Locked]
    public string $objectId;

    public ?float $lat = null;

    public ?float $lon = null;

    public bool $failed = false;

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
        if ($this->lat !== null && $this->lon !== null && $this->getErrorBag()->isEmpty()) {
            try {
                $sky = $api->sky($this->objectId, null, $this->lat, $this->lon);
            } catch (SolarApiException) {
                $this->failed = true;
            }
            $this->failed = $this->failed || ! $sky instanceof SkyPosition || $sky->observer === null;
        }

        return view('livewire.sky-observer', ['sky' => $sky]);
    }
}
