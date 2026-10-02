<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\MeteorCatalogue;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class MeteorShowers extends Component
{
    #[Url(as: 'active_on', except: '')]
    public mixed $activeOn = '';

    #[Url(as: 'established_only', except: false)]
    public mixed $establishedOnly = false;

    /** @var array<string, string> */
    #[Locked]
    public array $rawInputErrors = [];

    public function mount(): void
    {
        foreach (['active_on' => 'activeOn', 'established_only' => 'establishedOnly'] as $query => $field) {
            if (! request()->query->has($query)) {
                continue;
            }
            $value = request()->query()[$query];
            if (($error = $this->rawInputError($field, $value, true)) !== null) {
                $this->rawInputErrors[$field] = $error;
            } elseif ($field === 'establishedOnly') {
                $this->establishedOnly = in_array($value, [true, 1, '1', 'true'], true);
            } else {
                $this->activeOn = $value ?? '';
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
            if ($property === 'establishedOnly') {
                $this->establishedOnly = in_array($this->establishedOnly, [true, 1, '1'], true);
            }
        }
    }

    public function clearFilters(): void
    {
        $this->rawInputErrors = [];
        $this->resetValidation();
        $this->reset(['activeOn', 'establishedOnly']);
    }

    public function render(SolarApiClient $api): View
    {
        // URL and Livewire payloads retain their raw types until validated.
        // Reset bad controls only after retaining an error that blocks queries.
        foreach (['activeOn', 'establishedOnly'] as $field) {
            if (($error = $this->rawInputError($field, $this->{$field})) !== null) {
                $this->rawInputErrors[$field] = $error;
                $this->reset($field);
            }
        }
        app(Seo::class)->title(__('Meteor showers'))->description(__('Explore IAU Meteor Data Center showers, their observation campaigns and approximate seasonal activity.'));
        $results = new MeteorCatalogue([], 0, false);
        $apiDown = false;
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $this->activeOn)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $this->activeOn) : false;
        $invalidDate = $this->activeOn !== '' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $this->activeOn)
            || ! $date || $date->format('Y-m-d') !== $this->activeOn || (int) $date->format('Y') < 1);
        if (! $invalidDate && $this->rawInputErrors === []) {
            try {
                $results = $api->meteorShowers($this->establishedOnly, $this->activeOn ?: null);
            } catch (SolarApiException) {
                $apiDown = true;
            }
        }

        return view('livewire.meteor-showers', compact('results', 'apiDown', 'invalidDate'));
    }

    private function rawInputError(string $field, mixed $value, bool $fromUrl = false): ?string
    {
        if ($field === 'activeOn' && ! is_string($value) && ($value !== null || ! $fromUrl)) {
            return __('Choose a single activity date in YYYY-MM-DD format.');
        }
        if ($field === 'establishedOnly') {
            $allowed = $fromUrl ? [true, false, 1, 0, '1', '0', 'true', 'false'] : [true, false, 1, 0, '1', '0'];
            if (! in_array($value, $allowed, true)) {
                return __('Choose a valid on/off value for established showers.');
            }
        }

        return null;
    }
}
