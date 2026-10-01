<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Observing\ObservingAccountScope;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ObservingSyncPageController
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        app(Seo::class)->title(__('Private observing backup'))->noindex();

        return response()->view('observing.sync', [
            'accountScope' => ObservingAccountScope::forUser($user),
            'accountEmail' => $user->email,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
