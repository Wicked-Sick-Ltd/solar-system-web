<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Handout;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Downloadable classroom handouts for teachers. Purely static: the handouts
 * are declared in config/educators.php and served from public/educators/.
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

        return view('livewire.educators-page', ['handouts' => $handouts]);
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
                'encodingFormat' => 'application/pdf',
                'isAccessibleForFree' => true,
                'inLanguage' => 'en-GB',
            ], $handouts),
        ];
    }
}
