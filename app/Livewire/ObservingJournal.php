<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ObservingJournal extends Component
{
    public function render(): View
    {
        app(Seo::class)->title(__('Observing lists and journal'))->description(__('Keep private observing lists and records in this browser.'))->noindex();

        return view('livewire.observing-journal');
    }
}
