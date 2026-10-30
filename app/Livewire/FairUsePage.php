<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class FairUsePage extends Component
{
    public function render(): View
    {
        app(Seo::class)
            ->title(__('Fair use'))
            ->description(__('Public Universe is free for everyone. Anonymous use is welcome. Bulk and automated use needs an API key. Verified search and AI crawlers are welcome.'));

        return view('livewire.fair-use-page');
    }
}
