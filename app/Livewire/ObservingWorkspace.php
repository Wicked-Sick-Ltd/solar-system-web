<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\What3Words\What3WordsClient;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Personal workspace data stays in browser storage, never in Livewire state. */
#[Layout('components.layouts.app')]
final class ObservingWorkspace extends Component
{
    public function render(): View
    {
        app(Seo::class)->title(__('Your observatory'))
            ->description(__('Keep observing equipment and sites in this browser, without an account.'))
            ->noindex();

        return view('livewire.observing-workspace', [
            'what3words' => app(What3WordsClient::class)->enabled(),
        ]);
    }
}
