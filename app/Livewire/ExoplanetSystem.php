<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ExoplanetSystem extends Component
{
    #[Locked]
    public string $id;

    public function mount(string $id): void
    {
        $this->id = $id;
    }

    public function render(SolarApiClient $api): View
    {
        $host = null;
        $apiDown = false;
        try {
            $host = $api->exoplanetHost($this->id);
            abort_if($host === null, 404);
        } catch (SolarApiException) {
            $apiDown = true;
        }
        app(Seo::class)->title($host->name ?? __('Planetary system'))->description(__('A host system and its known exoplanets.'));

        return view('livewire.exoplanet-system', compact('host', 'apiDown'));
    }
}
