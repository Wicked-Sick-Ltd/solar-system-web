<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Analytics;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class PrivacyPage extends Component
{
    /** Bump when the policy text materially changes. */
    public const string LAST_UPDATED = '2026-10-09';

    public function render(): View
    {
        app(Seo::class)
            ->title(__('Privacy & cookies'))
            ->description(__('What this site collects, which cookies it sets, the third parties involved, and your rights under UK GDPR.'));

        return view('livewire.privacy-page', [
            'operator' => config('site.operator'),
            'email' => config('site.contact_email'),
            'analyticsEnabled' => Analytics::measurementId() !== null,
            'updated' => self::LAST_UPDATED,
        ]);
    }
}
