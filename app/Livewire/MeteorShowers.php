<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\MeteorCatalogue;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class MeteorShowers extends Component
{
    #[Url(as: 'active_on', except: '')]
    public string $activeOn = '';

    #[Url(as: 'established_only', except: false)]
    public bool $establishedOnly = false;

    public function clearFilters(): void
    {
        $this->reset(['activeOn', 'establishedOnly']);
    }

    public function render(SolarApiClient $api): View
    {
        app(Seo::class)->title(__('Meteor showers'))->description(__('Explore IAU Meteor Data Center showers, their observation campaigns and approximate seasonal activity.'));
        $results = new MeteorCatalogue([], 0, false);
        $apiDown = false;
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $this->activeOn)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $this->activeOn) : false;
        $invalidDate = $this->activeOn !== '' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $this->activeOn)
            || ! $date || $date->format('Y-m-d') !== $this->activeOn || (int) $date->format('Y') < 1);
        if (! $invalidDate) {
            try {
                $results = $api->meteorShowers($this->establishedOnly, $this->activeOn ?: null);
            } catch (SolarApiException) {
                $apiDown = true;
            }
        }

        return view('livewire.meteor-showers', compact('results', 'apiDown', 'invalidDate'));
    }
}
