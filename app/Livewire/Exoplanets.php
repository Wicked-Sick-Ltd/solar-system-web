<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\ExoplanetFilters;
use App\Services\SolarApi\Data\Paginated;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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
    // Keep raw update types until validation; Livewire's int synthesizer can
    // otherwise coerce booleans and decimals before the updating hook.
    public mixed $page = 1;

    /** @var array<string, list<string>> */
    #[Locked]
    public array $initialFilterErrors = [];

    public function mount(): void
    {
        // URL hydration may silently coerce/drop invalid values. Validate the
        // original query and restore literal string searches such as "true".
        try {
            $filters = ExoplanetFilters::fromInput(request()->query());
            $this->q = $filters->q;
            $this->method = $filters->method;
            $this->distance = $filters->distance;
            $this->page = $filters->page;
        } catch (ValidationException $exception) {
            $this->initialFilterErrors = $exception->errors();
        }
    }

    public function updating(string $property, mixed $value): void
    {
        if (! in_array($property, ['q', 'method', 'distance', 'page'], true)) {
            return;
        }
        unset($this->initialFilterErrors[$property]);
        try {
            ExoplanetFilters::fromInput([$property => $value] + ['q' => $this->q, 'method' => $this->method, 'distance' => $this->distance, 'page' => $this->page]);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()[$property])) {
                $this->initialFilterErrors[$property] = $exception->errors()[$property];
                throw $exception;
            }
        }
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->page = 1;
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'method', 'distance', 'page', 'initialFilterErrors']);
    }

    public function render(SolarApiClient $api): View
    {
        app(Seo::class)->title(__('Exoplanets'))->description(__('Explore confirmed planets beyond our solar system, with measurements from the NASA Exoplanet Archive.'));
        $results = new Paginated([], ExoplanetFilters::PER_PAGE, 0, false);
        $apiDown = false;
        $filterErrors = array_merge([], ...array_values($this->initialFilterErrors));
        $exportQuery = [];
        try {
            if ($filterErrors === []) {
                $filters = ExoplanetFilters::fromInput(['q' => $this->q, 'method' => $this->method, 'distance' => $this->distance, 'page' => $this->page]);
                $exportQuery = $filters->query();
                $results = $api->exoplanets($filters->apiFilters(), ExoplanetFilters::PER_PAGE, $filters->offset());
            }
        } catch (ValidationException $exception) {
            $filterErrors = $exception->validator->errors()->all();
        } catch (SolarApiException) {
            $apiDown = true;
        }

        return view('livewire.exoplanets', compact('results', 'apiDown', 'filterErrors', 'exportQuery'));
    }
}
