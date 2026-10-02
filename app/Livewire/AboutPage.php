<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class AboutPage extends Component
{
    public function render(SolarApiClient $api): View
    {
        app(Seo::class)
            ->title(__('About'))
            ->description(__('What this site is, where the data comes from, how it is licensed, and how to use the API yourself.'));

        $sources = [];
        try {
            $sources = $api->sources();
        } catch (SolarApiException) {
            // The page is editorial; a missing live source list is fine.
        }

        return view('livewire.about-page', ['sources' => $sources, 'catalogueIdentity' => $api->catalogueIdentity(), 'downloadManifest' => $api->downloadManifest()]);
    }
}
