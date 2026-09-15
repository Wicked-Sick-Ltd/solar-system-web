<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class PrivacyPage extends Component
{
    /** Bump when the policy text materially changes. */
    public const string LAST_UPDATED = '2026-09-15';

    public function render(): View
    {
        app(Seo::class)
            ->title(__('Privacy & cookies'))
            ->description(__('What this site collects, which cookies it sets, the third parties involved, and your rights under UK GDPR.'));

        return view('livewire.privacy-page', [
            'operator' => config('site.operator'),
            'email' => config('site.contact_email'),
            'analyticsEnabled' => (bool) config('site.analytics.ga_measurement_id'),
            'updated' => self::LAST_UPDATED,
        ]);
    }
}
