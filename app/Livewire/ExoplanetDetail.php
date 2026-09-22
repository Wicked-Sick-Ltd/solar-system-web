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
final class ExoplanetDetail extends Component
{
    #[Locked]
    public string $id;

    public function mount(string $id): void
    {
        $this->id = $id;
    }

    public function render(SolarApiClient $api): View
    {
        $planet = null;
        $apiDown = false;
        try {
            $planet = $api->exoplanet($this->id);
            abort_if($planet === null, 404);
        } catch (SolarApiException) {
            $apiDown = true;
        }
        app(Seo::class)->title($planet->name ?? __('Exoplanet'))->description(__('Measurements and discovery details from the NASA Exoplanet Archive.'));

        return view('livewire.exoplanet-detail', compact('planet', 'apiDown'));
    }
}
