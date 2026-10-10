<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\ObjectDetail;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\Exceptions\SolarApiUnavailableException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\ObjectHighlights;
use App\Support\ObjectOfTheDay;
use App\Support\Seo;
use App\Support\ShareImage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** The dated object-of-the-day permalink: one featured body, its fun fact and key stats, shareable. */
#[Layout('components.layouts.app')]
final class Today extends Component
{
    public string $date;

    public function mount(string $date): void
    {
        if (ObjectOfTheDay::parse($date) === null) {
            abort(404);
        }

        $this->date = $date;
    }

    public function render(SolarApiClient $api): View
    {
        $day = ObjectOfTheDay::parse($this->date) ?? abort(404);

        try {
            $object = $api->object(ObjectOfTheDay::slugFor($day));
        } catch (SolarApiUnavailableException) {
            app(Seo::class)->title(__('Object of the day'))->noindex();

            return view('livewire.today', $this->viewData($day, null) + ['apiDown' => true]);
        } catch (SolarApiException) {
            $object = null;
        }

        if (! $object instanceof ObjectDetail) {
            abort(404);
        }

        $fact = ObjectHighlights::funFact($object);
        $url = ObjectOfTheDay::url($day);

        app(Seo::class)
            ->title(__('Object of the day: :name', ['name' => $object->name]))
            ->description(trim(($fact ?? '').' '.__('Object of the day for :date on :site.', [
                'date' => $day->format('j F Y'),
                'site' => config('site.name'),
            ])))
            ->type('article')
            ->canonical($url)
            ->image(ShareImage::todayUrl($day))
            ->imageAlt(__('Share card: :name, object of the day for :date', ['name' => $object->name, 'date' => $day->format('j F Y')]))
            ->jsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => __('Object of the day: :name', ['name' => $object->name]),
                'datePublished' => $day->format('Y-m-d'),
                'url' => $url,
                'image' => ShareImage::todayUrl($day),
                'about' => ['@type' => 'Thing', 'name' => $object->name, 'url' => route('objects.show', $object->slug())],
            ]);

        return view('livewire.today', $this->viewData($day, $object) + [
            'apiDown' => false,
            'fact' => $fact,
            'stats' => ObjectHighlights::keyStats($object),
            'shareUrl' => $url,
        ]);
    }

    /** @return array<string,mixed> */
    private function viewData(CarbonImmutable $day, ?ObjectDetail $object): array
    {
        return [
            'day' => $day,
            'object' => $object,
            'isToday' => $day->equalTo(ObjectOfTheDay::today()),
            'previous' => ObjectOfTheDay::previous($day),
            'next' => ObjectOfTheDay::next($day),
        ];
    }
}
