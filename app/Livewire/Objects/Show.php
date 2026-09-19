<?php

declare(strict_types=1);

namespace App\Livewire\Objects;

use App\Services\SolarApi\Data\ObjectDetail;
use App\Services\SolarApi\Data\ObjectSummary;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\Exceptions\SolarApiUnavailableException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The single canonical detail template for any object, whatever its type. For
 * planet-like bodies it also lists moons (a client-sortable table) and rings.
 * Reached by both /objects/{slug} and /planets/{slug}; the canonical URL is
 * always the id-based /objects/{id} so the two never compete in search.
 */
#[Layout('components.layouts.app')]
final class Show extends Component
{
    public string $slug;

    /** Moon table sort state (kept here, not in the URL — it's a minor view nicety). */
    public string $sortField = 'semiMajorAxisAu';

    public string $sortDir = 'asc';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortField = $field;
        $this->sortDir = 'asc';
    }

    public function render(SolarApiClient $api): View
    {
        // Resolve the object. A genuine miss is a 404; an unreachable backend
        // is a degradation panel, not a 404.
        try {
            $object = $api->object($this->slug);
        } catch (SolarApiUnavailableException) {
            app(Seo::class)->title(__('Temporarily unavailable'))->noindex();

            return view('livewire.objects.show', ['object' => null, 'apiDown' => true]);
        } catch (SolarApiException) {
            $object = null;
        }

        if (! $object instanceof ObjectDetail) {
            abort(404);
        }

        $this->applySeo($object);

        $parent = null;
        if ($object->hasParent()) {
            try {
                $parent = $api->object($object->parentId);
            } catch (SolarApiException) {
                // parent link just won't render
            }
        }

        // Position (where is it now) and child listings — each degrades alone.
        // The figure needs the object AND Earth for the same date, fetched as
        // one batch so the second body costs no extra round trip. A moon's
        // elements are about its parent, not the Sun, so a moon is plotted
        // at its parent's heliocentric position and the caption says so.
        $position = null;
        $earth = null;
        $plotBody = null;   // the ObjectDetail whose orbit is drawn
        $plotNote = null;
        $moons = [];
        $rings = [];

        if ($object->objectType === 'moon') {
            if ($parent?->orbital?->isPropagatable()) {
                $plotBody = $parent;
                $plotNote = __(':moon is shown at its parent, :parent.', ['moon' => $object->name, 'parent' => $parent->name]);
            }
        } elseif ($object->orbital?->isPropagatable()) {
            $plotBody = $object;
        }

        if ($plotBody !== null) {
            try {
                $batch = $api->positionsBatch([$plotBody->id, 'planet-earth'], now()->utc()->toDateString());
                $position = $batch[$plotBody->id] ?? null;
                $earth = $batch['planet-earth'] ?? null;
            } catch (SolarApiException) {
                // leave both null; the panel simply won't render
            }
        }

        // Where it appears in the sky. Moons report their parent's position;
        // the Sun is special-cased by the backend. A 404 (or any API error)
        // just hides the section.
        $sky = null;
        if ($object->orbital?->isPropagatable() || $object->objectType === 'moon' || $object->id === 'sun') {
            try {
                $sky = $api->sky($object->id);
            } catch (SolarApiException) {
                // leave $sky null
            }
        }

        if ($object->isPlanetLike()) {
            try {
                $moons = $this->sortMoons($api->moons($object->id));
                $rings = $api->rings($object->id);
            } catch (SolarApiException) {
                // partial page is fine
            }
        }

        return view('livewire.objects.show', [
            'object' => $object,
            'apiDown' => false,
            'position' => $position,
            'earth' => $earth,
            'plotBody' => $plotBody,
            'plotNote' => $plotNote,
            'sky' => $sky,
            'moons' => $moons,
            'rings' => $rings,
            'parent' => $parent,
        ]);
    }

    /**
     * @param  list<ObjectSummary>  $moons
     * @return list<ObjectSummary>
     */
    private function sortMoons(array $moons): array
    {
        $field = $this->sortField;

        usort($moons, function ($a, $b) use ($field) {
            $av = $a->{$field} ?? null;
            $bv = $b->{$field} ?? null;

            // Nulls always sort last.
            if ($av === null && $bv === null) {
                return 0;
            }
            if ($av === null) {
                return 1;
            }
            if ($bv === null) {
                return -1;
            }

            $cmp = is_string($av) ? strcasecmp($av, (string) $bv) : $av <=> $bv;

            return $this->sortDir === 'desc' ? -$cmp : $cmp;
        });

        return $moons;
    }

    private function applySeo(ObjectDetail $object): void
    {
        $description = $object->notes
            ? Str::limit($object->notes, 180)
            : __(':name — :type. Orbital, physical and observational data, sources and current position.', [
                'name' => $object->name,
                'type' => $object->typeLabel() ?? __('solar-system object'),
            ]);

        app(Seo::class)
            ->title($object->name)
            ->description($description)
            ->type('article')
            ->canonical(route('objects.show', $object->id))
            ->image(route('og.object', $object->id))   // per-object share card
            ->jsonLd($this->schema($object, $description));
    }

    /** @return array<string,mixed> */
    private function schema(ObjectDetail $object, string $description): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Thing',
            'name' => $object->name,
            'description' => $description,
            'url' => route('objects.show', $object->id),
        ];

        if ($object->typeLabel()) {
            $schema['additionalType'] = $object->typeLabel();
        }

        if ($object->wikipediaUrl) {
            $schema['sameAs'] = $object->wikipediaUrl;
        }

        return $schema;
    }
}
