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

/**
 * Search results from the solar-system and exoplanet catalogues. The query lives in the URL so a
 * search is a shareable, back-button-friendly link. Search-as-you-type updates
 * via a debounced live binding; with JS off the page still works as a plain
 * server-rendered results page for whatever ?q= it was loaded with.
 */
#[Layout('components.layouts.app')]
final class SearchPage extends Component
{
    #[Url(as: 'q', except: '')]
    public mixed $q = '';

    public const int QUERY_LIMIT = 200;

    private const int RESULTS_PER_CATALOGUE = 20;

    public function mount(): void
    {
        // URL hydration interprets JSON-looking text (such as "true"). A name
        // search must retain the original literal string and reject arrays.
        $this->q = request()->query()['q'] ?? '';
    }

    public function updatedQ(): void
    {
        if ($this->q === null) {
            $this->q = '';
        }
    }

    public function render(SolarApiClient $api): View
    {
        $query = is_string($this->q) ? trim($this->q) : '';
        $queryTooLong = mb_strlen($query) > self::QUERY_LIMIT;
        $queryError = ! is_string($this->q) ? __('Enter a single search term.') : ($queryTooLong
            ? __('Please use a search of :limit characters or fewer.', ['limit' => self::QUERY_LIMIT]) : null);

        app(Seo::class)
            ->title($query !== '' && ! $queryTooLong ? __('Search: :q', ['q' => $query]) : __('Search'))
            ->description(__('Search solar-system objects, exoplanets and their host systems by name.'))
            ->noindex();

        $results = [];
        $solarUnavailable = false;
        $solarHasMore = false;
        $exoplanets = new Paginated([], self::RESULTS_PER_CATALOGUE, 0, false);
        $exoplanetsUnavailable = false;

        if ($query !== '' && $queryError === null) {
            try {
                $results = $api->search($query, self::RESULTS_PER_CATALOGUE + 1);
                $solarHasMore = count($results) > self::RESULTS_PER_CATALOGUE;
                $results = array_slice($results, 0, self::RESULTS_PER_CATALOGUE);
            } catch (SolarApiException) {
                $solarUnavailable = true;
            }

            try {
                $exoplanets = $api->exoplanets(['q' => $query], self::RESULTS_PER_CATALOGUE);
            } catch (SolarApiException) {
                $exoplanetsUnavailable = true;
            }
        }

        return view('livewire.search-page', [
            'results' => $results,
            'solarUnavailable' => $solarUnavailable,
            'solarHasMore' => $solarHasMore,
            'exoplanets' => $exoplanets,
            'exoplanetsUnavailable' => $exoplanetsUnavailable,
            'queryTooLong' => $queryTooLong,
            'queryError' => $queryError,
            'query' => $query,
        ]);
    }
}
