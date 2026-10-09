<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class HigherEducationPage extends Component
{
    public function render(): View
    {
        /** @var array<string, array<string, mixed>> $activities */
        $activities = config('higher-education.activities', []);

        app(Seo::class)
            ->title(__('Higher education'))
            ->description(__('Free undergraduate astronomy activities and printable handouts: orbital models, exoplanet selection effects and reproducible research using real catalogue data.'))
            ->jsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => __('Higher education'),
                'url' => route('higher-education'),
                'isAccessibleForFree' => true,
                'hasPart' => array_map(fn (string $id, array $activity): array => [
                    '@type' => 'LearningResource',
                    'name' => $activity['title'],
                    'description' => $activity['summary'],
                    'url' => route('higher-education.handout', ['activity' => $id]),
                    'learningResourceType' => 'activity',
                    'educationalLevel' => 'Undergraduate',
                    'encodingFormat' => 'text/html',
                    'isAccessibleForFree' => true,
                    'inLanguage' => 'en-GB',
                ], array_keys($activities), array_values($activities)),
            ]);

        return view('livewire.higher-education-page', ['activities' => $activities]);
    }
}
