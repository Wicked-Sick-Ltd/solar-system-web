<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Seo;
use App\Support\SettingsPayload;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Your settings": everything this site remembers about a visitor, all of it
 * in their own browser's local storage — theme, observing location, display
 * preferences. The page lists it, lets them change or clear any of it, and
 * makes a share link so the same settings can be applied on another device
 * without an account. The server never sees the stored values; it only
 * validates a share token the visitor chose to open.
 */
#[Layout('components.layouts.app')]
final class SettingsPage extends Component
{
    /** Share token (?s=…) — validated server-side, applied only when the visitor clicks. */
    #[Url(as: 's', except: '')]
    public string $share = '';

    public function render(): View
    {
        app(Seo::class)
            ->title(__('Your settings'))
            ->description(__('See, change and clear everything this site remembers about you — all of it stored in your own browser, none of it on our servers.'))
            ->noindex();

        $import = $this->share !== '' ? SettingsPayload::decode($this->share) : null;

        return view('livewire.settings-page', [
            'import' => $import,
            'importFailed' => $this->share !== '' && $import === null,
            'themes' => SettingsPayload::THEMES,
            'timeFormats' => SettingsPayload::TIME_FORMATS,
        ]);
    }
}
