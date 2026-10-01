<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ExplorePage extends Component
{
    public function render(): View
    {
        app(Seo::class)->title(__('Explore'))->description(__('Find a world, follow its moons, or explore known planetary systems across our galaxy.'));

        return view('livewire.explore-page');
    }
}
