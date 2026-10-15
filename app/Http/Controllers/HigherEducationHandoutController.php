<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Schema;
use App\Support\Seo;
use Illuminate\Contracts\View\View;

final class HigherEducationHandoutController
{
    public function __invoke(string $activity): View
    {
        /** @var array<string, array<string, mixed>> $activities */
        $activities = config('higher-education.activities', []);
        abort_unless(array_key_exists($activity, $activities), 404);
        $worksheet = $activities[$activity];

        app(Seo::class)
            ->title((string) $worksheet['title'])
            ->description((string) $worksheet['summary'])
            ->jsonLd(Schema::learningResource((string) $worksheet['title'], (string) $worksheet['summary'], route('higher-education.handout', ['activity' => $activity]), (string) ($worksheet['level'] ?? 'Undergraduate')));

        return view('education.higher-education-handout', [
            'activity' => $worksheet,
            'activityId' => $activity,
        ]);
    }
}
