<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** The same bounded, validated selection for the catalogue page and its exports. */
final readonly class ExoplanetFilters
{
    public const int PER_PAGE = 24;

    public function __construct(public string $q, public string $method, public string $distance, public int $page) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        foreach (['q', 'method', 'distance'] as $field) {
            $input[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : ($input[$field] ?? '');
        }
        if (! array_key_exists('page', $input)) {
            $input['page'] = 1;
        }
        $values = Validator::make($input, [
            'q' => ['nullable', 'string', 'max:200'],
            'method' => ['nullable', 'string', 'max:100'],
            'distance' => ['nullable', 'string', Rule::in(['', '10', '25', '100', '1000'])],
            'page' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_int($value) && (! is_string($value) || ! preg_match('/^[1-9][0-9]*$/D', $value))) {
                    $fail('The page must be a whole number from 1 to 4000.');
                }
            }, 'integer', 'min:1', 'max:4000'],
        ])->validate();

        return new self($values['q'] ?? '', $values['method'] ?? '', $values['distance'] ?? '', (int) $values['page']);
    }

    public function offset(): int
    {
        return ($this->page - 1) * self::PER_PAGE;
    }

    /** @return array<string, string> */
    public function apiFilters(): array
    {
        return array_filter(['q' => $this->q, 'discovery_method' => $this->method, 'max_distance_pc' => $this->distance], static fn ($value) => $value !== '');
    }

    /** @return array<string, string|int> */
    public function query(): array
    {
        return array_filter(['q' => $this->q, 'method' => $this->method, 'distance' => $this->distance, 'page' => $this->page], static fn ($value) => $value !== '');
    }
}
