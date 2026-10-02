<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\Paginated;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A category landing page — a filtered, copy-led view over the catalogue. One
 * component drives /dwarf-planets, /asteroids, /comets and /tnos; the `kind`
 * comes from the route definition.
 */
#[Layout('components.layouts.app')]
final class Category extends Component
{
    private const PER_PAGE = 24;

    // Keep legacy orbit-ordered page links within a bounded offset. The API's
    // keyset mode uses a different order, so it must be selected explicitly.
    private const MAX_PAGE = 417;

    #[Url(except: '')]
    public mixed $orbit = '';

    #[Url(except: false)]
    public mixed $neo = false;

    #[Url(except: false)]
    public mixed $pha = false;

    #[Url(except: false)]
    public mixed $named = false;

    #[Url(except: '')]
    public mixed $diameter = '';

    #[Url(except: '')]
    public mixed $moid = '';

    #[Url(except: '')]
    public mixed $quality = '';

    #[Url(except: '')]
    public mixed $discovered = '';

    #[Url(except: 'orbit')]
    public mixed $order = 'orbit';

    #[Url(except: '')]
    public mixed $after = '';

    #[Locked]
    public string $kind;

    /** @var array<string, string> */
    #[Locked]
    public array $rawInputErrors = [];

    #[Url(as: 'page', except: 1)]
    public mixed $page = 1;

    public function mount(string $kind): void
    {
        $this->kind = $kind;
        $fields = $kind === 'asteroid'
            ? ['orbit', 'neo', 'pha', 'named', 'diameter', 'moid', 'quality', 'discovered', 'order', 'after', 'page']
            : ['page'];
        foreach ($fields as $field) {
            if (! request()->query->has($field)) {
                continue;
            }
            $value = request()->query()[$field];
            if (($error = $this->rawInputError($field, $value, true)) !== null) {
                $this->rawInputErrors[$field] = $error;
            } elseif (in_array($field, ['neo', 'pha', 'named'], true)) {
                $this->{$field} = in_array($value, [true, 1, '1', 'true'], true);
            } elseif ($field === 'page') {
                $this->page = (int) $value;
            } else {
                // Preserve literal text instead of Livewire's JSON coercion.
                $this->{$field} = $value ?? '';
            }
        }
    }

    public function updated(string $property): void
    {
        if (($error = $this->rawInputError($property, $this->{$property})) !== null) {
            $this->rawInputErrors[$property] = $error;
            $this->addError($property, $error);
            $this->reset($property);
        } else {
            unset($this->rawInputErrors[$property]);
            $this->resetValidation($property);
        }
        if (! in_array($property, ['page', 'after'], true)) {
            unset($this->rawInputErrors['page'], $this->rawInputErrors['after']);
            $this->reset(['page', 'after']);
        }
    }

    public function clearFilters(): void
    {
        $this->rawInputErrors = [];
        $this->resetValidation();
        if (! in_array($this->order, ['orbit', 'id'], true)) {
            $this->reset('order');
        }
        $this->reset(['orbit', 'neo', 'pha', 'named', 'diameter', 'moid', 'quality', 'discovered', 'page', 'after']);
    }

    public function applyFilters(): void
    {
        unset($this->rawInputErrors['page'], $this->rawInputErrors['after']);
        $this->resetValidation(['page', 'after']);
        $this->reset(['page', 'after']);
    }

    public function render(SolarApiClient $api): View
    {
        // URL and Livewire payloads retain their raw types until validated.
        // Reset bad controls only after retaining an error that blocks queries.
        foreach (['orbit', 'neo', 'pha', 'named', 'diameter', 'moid', 'quality', 'discovered', 'order', 'after', 'page'] as $field) {
            if (($error = $this->rawInputError($field, $this->{$field})) !== null) {
                $this->rawInputErrors[$field] = $error;
                $this->reset($field);
            }
        }
        $copy = $this->copy();

        app(Seo::class)->title($copy['title'])->description($copy['lead']);

        $inputErrors = $this->inputErrors();
        $page = max(1, min(self::MAX_PAGE, $this->page));
        $offset = ($page - 1) * self::PER_PAGE;

        $apiDown = false;
        $results = new Paginated([], self::PER_PAGE, $offset, false);

        try {
            if ($inputErrors !== []) {
                // Invalid links never become a different, unfiltered query.
            } elseif ($this->kind === 'dwarf_planet') {
                // Small, curated set — show them all, candidates included.
                $items = $api->dwarfPlanets(true);
                $results = new Paginated($items, max(self::PER_PAGE, count($items)), 0, false);
            } else {
                $results = $api->objects($this->filters(), self::PER_PAGE, $offset, $this->kind === 'asteroid' && $this->order === 'id' ? $this->after : null);
            }
        } catch (SolarApiException) {
            $apiDown = true;
        }

        return view('livewire.category', [
            'results' => $results,
            'apiDown' => $apiDown,
            'copy' => $copy,
            'inputErrors' => $inputErrors,
            'filterOptions' => $this->filterOptions(),
            'nextUrl' => $this->pageUrl($page + 1, $results->nextAfter),
            'previousUrl' => $this->pageUrl(max(1, $page - 1)),
            'firstUrl' => $this->pageUrl(1),
            'fullCatalogueUrl' => route('asteroids', $this->urlFilters() + ['order' => 'id']),
            'atPageLimit' => $page >= self::MAX_PAGE,
            'paginated' => $this->kind !== 'dwarf_planet',
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        if ($this->kind !== 'asteroid') {
            return ['type' => $this->kind];
        }

        return ['type' => 'asteroid', 'orbit_class' => $this->orbit,
            'neo' => $this->neo, 'pha' => $this->pha, 'named_only' => $this->named,
            'min_diameter_km' => $this->diameter, 'max_moid_au' => $this->moid,
            'max_condition_code' => $this->quality,
            'discovered_after' => $this->discovered !== '' ? $this->discovered.'-01-01' : null];
    }

    /** @return list<string> */
    private function inputErrors(): array
    {
        $errors = array_values($this->rawInputErrors);
        if ($this->page < 1 || $this->page > self::MAX_PAGE) {
            $errors[] = __('Please use a page from 1 to :max, or browse the full catalogue by ID.', ['max' => self::MAX_PAGE]);
        }
        if ($this->kind !== 'asteroid') {
            return $errors;
        }
        foreach ($this->filterOptions() as $field => $options) {
            if (! array_key_exists($this->{$field}, $options)) {
                $errors[] = __('Choose a valid value for :filter.', ['filter' => $field]);
            }
        }
        if ($this->discovered !== '' && (! preg_match('/^\d{4}$/D', $this->discovered) || (int) $this->discovered < 1600 || (int) $this->discovered > (int) date('Y'))) {
            $errors[] = __('Choose a discovery year from 1600 to :year.', ['year' => date('Y')]);
        }
        if ($this->after !== '' && ($this->order !== 'id' || ! preg_match('/^[A-Za-z0-9_.-]{1,200}$/D', $this->after))) {
            $errors[] = __('This catalogue position is invalid. Return to the first results.');
        }

        return $errors;
    }

    private function rawInputError(string $field, mixed $value, bool $fromUrl = false): ?string
    {
        if ($field === 'page') {
            if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^[1-9][0-9]*$/D', (string) $value)
                || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value > self::MAX_PAGE) {
                return __('Choose a whole page number from 1 to :max.', ['max' => self::MAX_PAGE]);
            }
        } elseif (in_array($field, ['neo', 'pha', 'named'], true)) {
            $allowed = $fromUrl ? [true, false, 1, 0, '1', '0', 'true', 'false'] : [true, false, 1, 0, '1', '0'];
            if (! in_array($value, $allowed, true)) {
                return __('Choose a valid on/off value for :filter.', ['filter' => $field]);
            }
        } elseif (in_array($field, ['orbit', 'diameter', 'moid', 'quality', 'discovered', 'order', 'after'], true)) {
            if (! is_string($value) && ($value !== null || ! $fromUrl)) {
                return __('Choose a single text value for :filter.', ['filter' => $field]);
            }
        }

        return null;
    }

    /** @return array<string, array<string|int, string>> */
    private function filterOptions(): array
    {
        return [
            'orbit' => ['' => __('Any orbit class'), 'MBA' => __('Main-belt asteroid'), 'IMB' => __('Inner main-belt asteroid'),
                'OMB' => __('Outer main-belt asteroid'), 'MCA' => __('Mars-crossing asteroid'), 'ATE' => __('Aten'), 'APO' => __('Apollo'),
                'AMO' => __('Amor'), 'IEO' => __('Atira'), 'TJN' => __('Jupiter Trojan'), 'CEN' => __('Centaur'), 'TNO' => __('Trans-Neptunian object')],
            'diameter' => ['' => __('Any diameter'), '1' => __('At least 1 km'), '10' => __('At least 10 km'), '100' => __('At least 100 km')],
            'moid' => ['' => __('Any Earth MOID'), '0.05' => __('At most 0.05 AU'), '0.1' => __('At most 0.1 AU'), '0.5' => __('At most 0.5 AU')],
            'quality' => ['' => __('Any orbit uncertainty'), '2' => __('Well determined (code 0–2)'), '5' => __('Code 0–5')],
            'order' => ['orbit' => __('Orbital distance'), 'id' => __('Catalogue ID (full catalogue)')],
        ];
    }

    /** @return array<string, string|int> */
    private function urlFilters(): array
    {
        return array_filter(['orbit' => $this->orbit, 'neo' => (int) $this->neo, 'pha' => (int) $this->pha,
            'named' => (int) $this->named, 'diameter' => $this->diameter, 'moid' => $this->moid,
            'quality' => $this->quality, 'discovered' => $this->discovered], static fn ($value) => $value !== '' && $value !== 0);
    }

    private function pageUrl(int $page, ?string $after = null): string
    {
        if ($this->kind !== 'asteroid') {
            return route(match ($this->kind) {
                'comet' => 'comets',
                'tno' => 'tnos',
                'dwarf_planet' => 'dwarf-planets',
                default => 'objects.index',
            }, ['page' => $page]);
        }

        return route('asteroids', $this->urlFilters() + ($this->order === 'id'
            ? ['order' => 'id', 'after' => $after ?? ''] : ['page' => $page]));
    }

    /** @return array{title:string,eyebrow:string,lead:string} */
    private function copy(): array
    {
        return match ($this->kind) {
            'dwarf_planet' => [
                'title' => __('Dwarf planets'),
                'eyebrow' => __('Rounded by their own gravity'),
                'lead' => __('Worlds massive enough to pull themselves round, but which never cleared their orbital neighbourhood — Ceres in the asteroid belt, and Pluto, Eris, Haumea and Makemake out beyond Neptune. Candidates are included.'),
            ],
            'asteroid' => [
                'title' => __('Asteroids'),
                'eyebrow' => __('Rocky remnants'),
                'lead' => __('The rocky leftovers of planet formation — most circling the Sun in the main belt between Mars and Jupiter, some swinging close to Earth.'),
            ],
            'comet' => [
                'title' => __('Comets'),
                'eyebrow' => __('Icy visitors'),
                'lead' => __('Balls of ice and dust on long, elongated orbits that grow tails as they near the Sun — from short-period regulars like Halley to one-time passers-by.'),
            ],
            'tno' => [
                'title' => __('Trans-Neptunian objects'),
                'eyebrow' => __('Beyond Neptune'),
                'lead' => __('The frozen bodies of the outer system — the Kuiper Belt, the scattered disc and the centaurs that wander between the giant planets.'),
            ],
            default => [
                'title' => __('Objects'),
                'eyebrow' => __('Catalogue'),
                'lead' => __('A selection from the catalogue.'),
            ],
        };
    }
}
