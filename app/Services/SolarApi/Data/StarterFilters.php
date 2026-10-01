<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class StarterFilters
{
    public function __construct(public string $q, public string $family, public int $page) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input): self
    {
        $input += ['q' => '', 'family' => '', 'page' => 1];
        $values = Validator::make($input, [
            'q' => ['nullable', 'string', 'max:200'],
            'family' => ['nullable', 'string', Rule::in(array_keys(StarterTarget::FAMILIES))],
            'page' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_int($value) && (! is_string($value) || ! preg_match('/^[1-9][0-9]*$/D', $value))) {
                    $fail(__('The page must be a whole number from 1 to 42.'));
                }
            }, 'integer', 'min:1', 'max:42'],
        ])->validate();

        return new self($values['q'] ?? '', $values['family'] ?? '', (int) $values['page']);
    }

    /** @return array<string,string|int> */
    public function query(int $page): array
    {
        return array_filter(['q' => $this->q, 'family' => $this->family, 'page' => $page], static fn ($v) => $v !== '');
    }
}
