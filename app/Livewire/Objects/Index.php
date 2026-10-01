<?php

declare(strict_types=1);

namespace App\Livewire\Objects;

use App\Services\SolarApi\Data\Paginated;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\ObjectType;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Browseable, filterable catalogue. All filter and page state lives in the URL
 * (via #[Url]) so every view is linkable and back-button friendly — no hidden
 * cookie state. A lookahead row tells us whether another page is available;
 * the backend returns no total.
 */
#[Layout('components.layouts.app')]
final class Index extends Component
{
    private const PER_PAGE = 24;

    // Keep offset browsing bounded, as on the category pages. Large catalogue
    // walks use the dedicated asteroid browser's explicit ID-order mode.
    private const MAX_PAGE = 417;

    #[Url(as: 'type', except: '')]
    public mixed $type = '';

    #[Url(as: 'parent', except: '')]
    public mixed $parent = '';

    #[Url(as: 'size', except: '')]
    public mixed $size = '';

    #[Url(as: 'neo', except: false)]
    public mixed $neo = false;

    #[Url(as: 'named', except: false)]
    public mixed $named = false;

    #[Url(as: 'page', except: 1)]
    public mixed $page = 1;

    /** @var array<string, string> */
    #[Locked]
    public array $rawInputErrors = [];

    public function mount(): void
    {
        $query = request()->query();
        foreach (['type', 'parent', 'size', 'neo', 'named', 'page'] as $field) {
            if (array_key_exists($field, $query)) {
                // Validate before Livewire's URL decoding can turn literal
                // strings or malformed values into a different selection.
                $this->{$field} = $query[$field];
                $this->validateField($field, true);
            }
        }
    }

    /** Reset to the first page whenever a filter changes. */
    public function updated(string $property): void
    {
        if (! in_array($property, ['type', 'parent', 'size', 'neo', 'named', 'page'], true)) {
            return;
        }
        unset($this->rawInputErrors[$property]);
        $this->validateField($property);
        if ($property !== 'page') {
            $this->page = 1;
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['type', 'parent', 'size', 'neo', 'named', 'page', 'rawInputErrors']);
        $this->resetValidation();
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        unset($this->rawInputErrors['page']);
    }

    public function render(SolarApiClient $api): View
    {
        app(Seo::class)
            ->title(__('Browse all objects'))
            ->description(__('Filter the full catalogue of solar-system objects by type, parent body, size and near-Earth status.'));

        foreach (['type', 'parent', 'size', 'neo', 'named', 'page'] as $field) {
            $this->validateField($field);
        }
        $page = $this->page;
        $offset = ($page - 1) * self::PER_PAGE;

        $apiDown = false;
        $results = new Paginated([], self::PER_PAGE, $offset, false);

        try {
            if ($this->rawInputErrors === []) {
                $results = $api->objects($this->filters(), self::PER_PAGE, $offset);
            }
        } catch (SolarApiException) {
            $apiDown = true;
        }

        return view('livewire.objects.index', [
            'results' => $results,
            'apiDown' => $apiDown,
            'typeOptions' => $this->typeOptions(),
            'parentOptions' => $this->parentOptions(),
            'sizeOptions' => $this->sizeOptions(),
            'hasFilters' => $this->hasActiveFilters(),
            'firstUrl' => $this->pageUrl(1),
            'previousUrl' => $page > 1 ? $this->pageUrl($page - 1) : null,
            'nextUrl' => $page < self::MAX_PAGE ? $this->pageUrl($page + 1) : null,
            'atPageLimit' => $page >= self::MAX_PAGE,
        ]);
    }

    private function validateField(string $field, bool $fromUrl = false): void
    {
        $value = $this->{$field};
        $error = null;
        if (in_array($field, ['type', 'parent', 'size'], true)) {
            if ($value === null) {
                $value = '';
            }
            if (! is_string($value) || mb_strlen($value) > 200) {
                $error = __('Choose a single text value of 200 characters or fewer for :filter.', ['filter' => $field]);
            } elseif ($field === 'type' && $value !== '' && ! ObjectType::exists($value)) {
                $error = __('Choose a known object type.');
            } elseif ($field === 'size' && ! array_key_exists($value, $this->sizeOptions())) {
                $error = __('Choose a valid size range.');
            }
        } elseif (in_array($field, ['neo', 'named'], true)) {
            $allowed = $fromUrl ? [true, false, 1, 0, '1', '0', 'true', 'false'] : [true, false, 1, 0, '1', '0'];
            if (! in_array($value, $allowed, true)) {
                $error = __('Choose a valid on/off value for :filter.', ['filter' => $field]);
            } else {
                $value = in_array($value, [true, 1, '1', 'true'], true);
            }
        } elseif ($field === 'page') {
            if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^[1-9][0-9]*$/D', (string) $value)
                || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value > self::MAX_PAGE) {
                $error = __('Choose a whole page number from 1 to :max.', ['max' => self::MAX_PAGE]);
            } else {
                $value = (int) $value;
            }
        }
        if ($error !== null) {
            $this->rawInputErrors[$field] = $error;
            $this->reset($field);
        } else {
            $this->{$field} = $value;
        }
    }

    private function pageUrl(int $page): string
    {
        return route('objects.index', array_filter(['type' => $this->type, 'parent' => $this->parent,
            'size' => $this->size, 'neo' => (int) $this->neo, 'named' => (int) $this->named,
            'page' => $page], static fn ($value) => $value !== '' && $value !== 0));
    }

    /** @return array<string,mixed> */
    private function filters(): array
    {
        $filters = [
            'type' => $this->type !== '' ? $this->type : null,
            'parent' => $this->parent !== '' ? $this->parent : null,
            'neo' => $this->neo ?: null,
            'named_only' => $this->named ?: null,
        ];

        // Size buckets map onto the backend's radius range.
        $range = $this->sizeRange($this->size);
        $filters['min_radius_km'] = $range['min'];
        $filters['max_radius_km'] = $range['max'];

        return array_filter($filters, static fn ($v) => $v !== null);
    }

    /** @return array{min:?float,max:?float} */
    private function sizeRange(string $bucket): array
    {
        return match ($bucket) {
            'giant' => ['min' => 25000.0, 'max' => null],
            'large' => ['min' => 1000.0, 'max' => 25000.0],
            'medium' => ['min' => 100.0, 'max' => 1000.0],
            'small' => ['min' => 1.0, 'max' => 100.0],
            'tiny' => ['min' => null, 'max' => 1.0],
            default => ['min' => null, 'max' => null],
        };
    }

    /** @return array<string,string> */
    private function typeOptions(): array
    {
        $options = ['' => __('Any type')];
        foreach (ObjectType::FILTERABLE as $type) {
            $options[$type] = ObjectType::plural($type);
        }
        if ($this->type !== '' && ObjectType::exists($this->type)) {
            $options[$this->type] = ObjectType::plural($this->type);
        }

        return $options;
    }

    /** @return array<string,string> */
    private function parentOptions(): array
    {
        return [
            '' => __('Any parent body'),
            'Sun' => __('The Sun'),
            'Mercury' => 'Mercury', 'Venus' => 'Venus', 'Earth' => 'Earth',
            'Mars' => 'Mars', 'Jupiter' => 'Jupiter', 'Saturn' => 'Saturn',
            'Uranus' => 'Uranus', 'Neptune' => 'Neptune', 'Pluto' => 'Pluto',
        ] + ($this->parent !== '' ? [$this->parent => $this->parent] : []);
    }

    /** @return array<string,string> */
    private function sizeOptions(): array
    {
        return [
            '' => __('Any size'),
            'giant' => __('Giant (≥ 25,000 km radius)'),
            'large' => __('Large (1,000–25,000 km)'),
            'medium' => __('Medium (100–1,000 km)'),
            'small' => __('Small (1–100 km)'),
            'tiny' => __('Tiny (≤ 1 km radius)'),
        ];
    }

    private function hasActiveFilters(): bool
    {
        return $this->type !== '' || $this->parent !== '' || $this->size !== ''
            || $this->neo || $this->named;
    }
}
