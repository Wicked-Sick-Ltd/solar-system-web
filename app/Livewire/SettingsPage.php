<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Seo;
use App\Support\SettingsPayload;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Your settings": everything this site remembers about a visitor, all of it
 * in their own browser's local storage — theme, observing location, display
 * preferences. The page lists it, lets them change or clear any of it, and
 * makes a share link so the same settings can be applied on another device
 * without copying values manually. The server never sees the stored values or the share
 * token: that token lives in the URL fragment and is decoded only in the
 * browser, so it is absent from requests, access logs and Referer.
 */
#[Layout('components.layouts.app')]
final class SettingsPage extends Component
{
    public function render(): View
    {
        app(Seo::class)
            ->title(__('Your settings'))
            ->description(__('See, change and clear everything this site remembers about you — all of it stored in your own browser, none of it on our servers.'))
            ->noindex();

        return view('livewire.settings-page', [
            'themes' => SettingsPayload::THEMES,
        ]);
    }
}
