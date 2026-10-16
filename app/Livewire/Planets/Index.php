<?php

declare(strict_types=1);

namespace App\Livewire\Planets;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Schema;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Index extends Component
{
    public function render(SolarApiClient $api): View
    {
        app(Seo::class)
            ->title(__('The Eight Planets of the Solar System: Data, Orbits & Moons'))
            ->description(__('The eight planets of the solar system, in order from the Sun — size, orbit and a way in to each one.'))
            ->jsonLd(Schema::dataset(__(':site planets', ['site' => config('site.name')]), __('The eight planets of the solar system with orbital elements, physical properties, atmospheres, moons and rings from NASA/JPL.'), route('planets.index'), 'planets, solar system, orbits, moons, rings'));

        $planets = [];
        $apiDown = false;

        try {
            // The eight planets, already ordered by semi-major axis upstream.
            $planets = $api->objects(['type' => 'planet'], 12, 0)->items;
        } catch (SolarApiException) {
            $apiDown = true;
        }

        return view('livewire.planets.index', [
            'planets' => $planets,
            'apiDown' => $apiDown,
        ]);
    }
}
