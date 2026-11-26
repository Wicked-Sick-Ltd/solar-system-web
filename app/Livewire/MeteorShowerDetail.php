<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\MeteorShowerPage;
use App\Support\Seo;
use App\Support\Sky\UkMeteorViewing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class MeteorShowerDetail extends Component
{
    #[Locked]
    public string $code;

    public function mount(string $code): void
    {
        $this->code = $code;
    }

    public function render(SolarApiClient $api): View
    {
        if (MeteorShowerPage::canonicalCode($this->code) === '') {
            abort(404);
        }

        $shower = null;
        $apiDown = false;
        try {
            $shower = $api->meteorShower($this->code);
        } catch (SolarApiException) {
            $apiDown = true;
        }
        if ($shower === null && ! $apiDown) {
            abort(404);
        }

        $code = MeteorShowerPage::canonicalCode($shower !== null ? $shower->code : $this->code);
        $seo = app(Seo::class)->noindex($shower === null);
        if ($code !== '') {
            $seo->canonical(route('meteor-showers.show', ['code' => $code]));
        }
        $viewing = null;
        $viewingCopy = [];
        $viewingFaq = [];
        if ($shower !== null) {
            $seo->title(MeteorShowerPage::title($shower))
                ->description(MeteorShowerPage::description($shower));
            $viewing = UkMeteorViewing::forShower($shower);
            if ($viewing !== null) {
                $configured = config('meteor_viewing.showers.'.$code, []);
                $viewingCopy = is_array($configured) ? $configured : [];
                $extra = $viewingCopy['faq'] ?? [];
                $viewingFaq = MeteorShowerPage::ukFaq($shower, $viewing, is_array($extra) ? array_values($extra) : []);
                $seo->jsonLd([
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => array_map(static fn (array $item): array => [
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
                    ], $viewingFaq),
                ]);
            }
        } else {
            $seo->title(__('Meteor shower'))
                ->description(__('IAU Meteor Data Center parameter sets, parent bodies and source references.'));
        }

        return view('livewire.meteor-shower-detail', compact('shower', 'apiDown', 'viewing', 'viewingCopy', 'viewingFaq'));
    }
}
