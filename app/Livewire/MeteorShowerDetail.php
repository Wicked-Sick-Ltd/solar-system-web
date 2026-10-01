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
final class MeteorShowerDetail extends Component
{
    #[Locked]
    public string $code;

    public function mount(string $code): void
    {
        $this->code = $code;
    }

    public function render(SolarApiClient $api): View
    {
        $shower = null;
        $apiDown = false;
        try {
            $shower = $api->meteorShower($this->code);
        } catch (SolarApiException) {
            $apiDown = true;
        }
        app(Seo::class)->title($shower->name ?? __('Meteor shower'))
            ->description(__('IAU Meteor Data Center parameter sets, parent bodies and source references.'))
            ->noindex($shower === null);

        return view('livewire.meteor-shower-detail', compact('shower', 'apiDown'));
    }
}
