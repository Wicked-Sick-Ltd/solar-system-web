<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ObjectOfTheDay;
use Illuminate\Http\RedirectResponse;

/** /today → today's dated permalink, so whatever gets shared keeps pointing at the same object. */
final class TodayRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()
            ->to(ObjectOfTheDay::url(ObjectOfTheDay::today()))
            ->header('Cache-Control', 'no-cache, private');
    }
}
