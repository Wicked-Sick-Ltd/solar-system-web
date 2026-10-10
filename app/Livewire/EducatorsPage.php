<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Handout;
use App\Support\KeyStage;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Downloadable classroom handouts for teachers. Purely static: the handouts
 * are declared in config/educators.php and served from public/handouts/.
 */
#[Layout('components.layouts.app')]
final class EducatorsPage extends Component
{
    public function render(): View
    {
        $handouts = Handout::all();

        app(Seo::class)
            ->title(__('Educators'))
            ->description(__('Free A4 classroom handouts about the solar system for primary and secondary schools, built on NASA/JPL data. No adverts, no pupil accounts.'))
            ->jsonLd($this->schema($handouts));

        return view('livewire.educators-page', [
            'handouts' => $handouts,
            'posters' => config('educators.posters', []),
            'stageLinks' => $this->stageLinks($handouts),
        ]);
    }

    /**
     * @param  list<Handout>  $handouts
     * @return array<string,mixed>
     */
    private function schema(array $handouts): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => __('Educators'),
            'url' => route('educators'),
            'isAccessibleForFree' => true,
            'hasPart' => array_map(fn (Handout $h): array => [
                '@type' => 'LearningResource',
                'name' => $h->title,
                'description' => $h->description,
                'url' => url($h->url()),
                'thumbnailUrl' => url($h->thumbnailUrl()),
                'learningResourceType' => 'handout',
                'educationalLevel' => $h->educationalLevel,
                'typicalAgeRange' => $h->typicalAgeRange,
                'encodingFormat' => 'application/pdf',
                'isAccessibleForFree' => true,
                'inLanguage' => 'en-GB',
            ], $handouts),
        ];
    }

    /**
     * Jump targets for the Key Stage index. Each code links to the first handout that includes it.
     *
     * @param  list<Handout>  $handouts
     * @return array<string, string>
     */
    private function stageLinks(array $handouts): array
    {
        $anchors = [];

        foreach ($handouts as $handout) {
            foreach ($handout->stages as $code) {
                $anchors[$code] ??= $handout->id;
            }
        }

        if ($anchors === []) {
            return [];
        }

        $links = [];

        foreach (KeyStage::normalize(array_keys($anchors)) as $code) {
            $links[$code] = $anchors[$code];
        }

        return $links;
    }
}
