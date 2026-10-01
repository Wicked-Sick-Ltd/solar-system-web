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
    public string $q = '';

    public const int QUERY_LIMIT = 200;

    private const int RESULTS_PER_CATALOGUE = 20;

    public function render(SolarApiClient $api): View
    {
        $query = trim($this->q);
        $queryTooLong = mb_strlen($query) > self::QUERY_LIMIT;

        app(Seo::class)
            ->title($query !== '' && ! $queryTooLong ? __('Search: :q', ['q' => $query]) : __('Search'))
            ->description(__('Search solar-system objects, exoplanets and their host systems by name.'))
            ->noindex();

        $results = [];
        $solarUnavailable = false;
        $solarHasMore = false;
        $exoplanets = new Paginated([], self::RESULTS_PER_CATALOGUE, 0, false);
        $exoplanetsUnavailable = false;

        if ($query !== '' && ! $queryTooLong) {
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
            'query' => $query,
        ]);
    }
}
