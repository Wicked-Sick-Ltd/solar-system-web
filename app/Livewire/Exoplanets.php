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
    public mixed $q = '';

    #[Url(except: '')]
    public mixed $method = '';

    #[Url(except: '')]
    public mixed $distance = '';

    #[Url(except: 1)]
    // Keep raw update types until validation; Livewire's int synthesizer can
    // otherwise coerce booleans and decimals before the updating hook.
    public mixed $page = 1;

    /** @var array<string, list<string>> */
    #[Locked]
    public array $initialFilterErrors = [];

    public function mount(): void
    {
        // Validate each original URL field independently. A malformed distance
        // must not prevent restoring a literal q="true" after URL hydration.
        $query = request()->query();
        foreach (['q', 'method', 'distance', 'page'] as $field) {
            $value = array_key_exists($field, $query) ? $query[$field] : ($field === 'page' ? 1 : '');
            $this->{$field} = $value;
            try {
                $filters = ExoplanetFilters::fromInput([$field => $value]);
                $this->{$field} = $filters->{$field};
            } catch (ValidationException $exception) {
                $this->initialFilterErrors[$field] = $exception->errors()[$field];
            }
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
        if (in_array($property, ['q', 'method', 'distance'], true) && $this->{$property} === null) {
            $this->{$property} = '';
        }
        if ($property !== 'page') {
            $this->page = 1;
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'method', 'distance', 'page', 'initialFilterErrors']);
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        unset($this->initialFilterErrors['page']);
        $this->resetValidation('page');
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

        // Hostile updates retain raw values for validation, never for HTML.
        $displayFilters = [];
        foreach (['q', 'method', 'distance'] as $field) {
            $displayFilters[$field] = is_string($this->{$field}) ? $this->{$field} : '';
        }

        return view('livewire.exoplanets', compact('results', 'apiDown', 'filterErrors', 'exportQuery', 'displayFilters'));
    }
}
