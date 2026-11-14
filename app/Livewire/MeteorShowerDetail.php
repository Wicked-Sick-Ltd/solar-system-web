<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\MeteorShowerPage;
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
        if (MeteorShowerPage::canonicalCode($this->code) === '') {
            abort(404);
        }

        $shower = null;
        $apiDown = false;
        try {
            $shower = $api->meteorShower($this->code);
        } catch (SolarApiException) {
            $apiDown = true;
        }
        if ($shower === null && ! $apiDown) {
            abort(404);
        }

        $code = MeteorShowerPage::canonicalCode($shower !== null ? $shower->code : $this->code);
        $seo = app(Seo::class)->noindex($shower === null);
        if ($code !== '') {
            $seo->canonical(route('meteor-showers.show', ['code' => $code]));
        }
        if ($shower !== null) {
            $seo->title(MeteorShowerPage::title($shower))
                ->description(MeteorShowerPage::description($shower));
        } else {
            $seo->title(__('Meteor shower'))
                ->description(__('IAU Meteor Data Center parameter sets, parent bodies and source references.'));
        }

        return view('livewire.meteor-shower-detail', compact('shower', 'apiDown'));
    }
}
