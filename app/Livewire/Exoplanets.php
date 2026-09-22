<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\Paginated;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Exoplanets extends Component
{
    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $method = '';

    #[Url(except: '')]
    public string $distance = '';

    #[Url(except: 1)]
    public int $page = 1;

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->page = 1;
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'method', 'distance', 'page']);
    }

    public function render(SolarApiClient $api): View
    {
        app(Seo::class)->title(__('Exoplanets'))->description(__('Explore confirmed planets beyond our solar system, with measurements from the NASA Exoplanet Archive.'));
        $this->page = max(1, min(4000, $this->page));
        $offset = ($this->page - 1) * 24;
        $results = new Paginated([], 24, $offset, false);
        $apiDown = false;
        try {
            $results = $api->exoplanets([
                'q' => mb_substr(trim($this->q), 0, 200),
                'discovery_method' => mb_substr($this->method, 0, 100),
                'max_distance_pc' => in_array($this->distance, ['10', '25', '100', '1000'], true) ? $this->distance : null,
            ], 24, $offset);
        } catch (SolarApiException) {
            $apiDown = true;
        }

        return view('livewire.exoplanets', compact('results', 'apiDown'));
    }
}
