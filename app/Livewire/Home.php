<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\Stats;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Home extends Component
{
    /**
     * A curated pool of well-known bodies for the "featured today" panel. The
     * pick is deterministic per UTC day so a shared link stays stable, but
     * rotates so the homepage feels alive.
     */
    private const FEATURED_POOL = [
        'planet-mercury', 'planet-venus', 'planet-earth', 'planet-mars',
        'planet-jupiter', 'planet-saturn', 'planet-uranus', 'planet-neptune',
        'dwarf-ceres', 'dwarf-pluto', 'dwarf-eris', 'dwarf-makemake', 'dwarf-haumea',
        'moon-luna', 'moon-titan', 'moon-europa', 'moon-io', 'moon-ganymede',
        'moon-triton', 'moon-enceladus', 'moon-phobos', 'comet-1p-halley',
    ];

    public function render(SolarApiClient $api): View
    {
        app(Seo::class)
            ->title(null)
            ->description(config('site.description'))
            ->jsonLd($this->websiteSchema());

        $stats = null;
        $featured = null;
        $apiDown = false;

        try {
            $stats = $api->stats();
            $featured = $api->object($this->featuredSlug());
        } catch (SolarApiException) {
            $apiDown = true;
        }

        return view('livewire.home', [
            'stats' => $stats,
            'featured' => $featured,
            'apiDown' => $apiDown,
            'sections' => $this->sections($stats),
        ]);
    }

    private function featuredSlug(): string
    {
        $day = gmdate('Y-z');               // year + day-of-year, UTC
        $index = crc32($day) % count(self::FEATURED_POOL);

        return self::FEATURED_POOL[$index];
    }

    /** @return list<array{label:string,route:string,params?:array<string,string>,count:?int,blurb:string}> */
    private function sections(?Stats $stats): array
    {
        return [
            ['label' => __('Planets'), 'route' => 'planets.index', 'count' => $stats?->planets(),
                'blurb' => __('The eight worlds of the Sun, from scorched Mercury to deep-blue Neptune.')],
            ['label' => __('Dwarf planets'), 'route' => 'dwarf-planets', 'count' => $stats?->dwarfPlanets(),
                'blurb' => __('Ceres, Pluto and the icy worlds of the outer system.')],
            ['label' => __('Moons'), 'route' => 'objects.index', 'params' => ['type' => 'moon'], 'count' => $stats?->moons(),
                'blurb' => __('Hundreds of natural satellites, from Luna to the shepherd moons of Saturn.')],
            ['label' => __('Asteroids'), 'route' => 'asteroids', 'count' => $stats?->asteroids(),
                'blurb' => __('Rocky remnants of the early solar system, including the near-Earth objects.')],
            ['label' => __('Comets'), 'route' => 'comets', 'count' => $stats?->comets(),
                'blurb' => __('Icy visitors on long, looping orbits — Halley and its many cousins.')],
            ['label' => __('Trans-Neptunian'), 'route' => 'tnos', 'count' => $stats?->transNeptunian(),
                'blurb' => __('The Kuiper Belt, scattered disc and the centaurs beyond Neptune.')],
        ];
    }

    /** @return array<string,mixed> */
    private function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('site.name'),
            'url' => route('home'),
            'description' => config('site.description'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('search').'?q={query}',
                'query-input' => 'required name=query',
            ],
        ];
    }
}
