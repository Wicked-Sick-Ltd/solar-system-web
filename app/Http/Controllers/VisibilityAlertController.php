<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\VisibilityAlert;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class VisibilityAlertController extends Controller
{
    public function index(Request $request): View
    {
        app(Seo::class)->title(__('Your visibility alerts'))->noindex();
        $alerts = $request->user()->visibilityAlerts()->latest()->get();

        return view('alerts.index', ['alerts' => $alerts]);
    }

    public function destroy(Request $request, VisibilityAlert $alert): RedirectResponse
    {
        abort_unless($alert->user_id === $request->user()->id, 404);

        $alert->delete();

        return redirect()->route('alerts.index')->with('status', __('Alert removed.'));
    }
}
