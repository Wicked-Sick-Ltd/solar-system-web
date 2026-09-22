<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Galaxy extends Component
{
    public function render(SolarApiClient $api): View
    {
        app(Seo::class)->title(__('Galaxy explorer'))->description(__('Locate known exoplanet systems around the Sun and in a schematic Milky Way overview.'));
        $map = null;
        $apiDown = false;
        try {
            $map = $api->galaxyMap();
        } catch (SolarApiException) {
            $apiDown = true;
        }

        return view('livewire.galaxy', compact('map', 'apiDown'));
    }
}
