<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ObservePage extends Component
{
    public function render(): View
    {
        app(Seo::class)->title(__('Observe'))->description(__('Plan your next look at the sky with object positions, local observing conditions and astronomical events.'));

        return view('livewire.observe-page');
    }
}
