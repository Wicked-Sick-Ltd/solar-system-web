<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class LearnPage extends Component
{
    public function render(): View
    {
        app(Seo::class)->title(__('Learn'))->description(__('Start with three questions: how far away are stars, how do we find planets, and what do the measurements mean?'));

        return view('livewire.learn-page');
    }
}
