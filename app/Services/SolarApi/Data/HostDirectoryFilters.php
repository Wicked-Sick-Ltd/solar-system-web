<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class HostDirectoryFilters
{
    public const int PER_PAGE = 24;

    public function __construct(public string $q, public string $radius, public string $order, public int $page) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $input += ['q' => '', 'radius' => 'all', 'order' => 'distance', 'page' => 1];
        if (is_string($input['q'])) {
            $input['q'] = trim($input['q']);
        }
        $values = Validator::make($input, [
            'q' => ['nullable', 'string', 'max:200'],
            'radius' => ['required', 'string', Rule::in(['25', '100', '1000', 'all'])],
            'order' => ['required', 'string', Rule::in(['distance', 'name'])],
            'page' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_int($value) && (! is_string($value) || ! preg_match('/^[1-9][0-9]*$/D', $value))) {
                    $fail('The page must be a whole number from 1 to 10000.');
                }
            }, 'integer', 'min:1', 'max:10000'],
        ])->validate();

        return new self($values['q'] ?? '', $values['radius'], $values['order'], (int) $values['page']);
    }

    public function select(GalaxyMap $map): HostDirectoryPage
    {
        $hosts = array_map(MeasuredHost::fromArray(...), $map->hosts);
        $query = mb_strtolower($this->q);
        $hosts = array_values(array_filter($hosts, fn (MeasuredHost $host) => ($query === '' || str_contains(mb_strtolower($host->name), $query))
            && ($this->radius === 'all' || $host->distancePc <= (float) $this->radius)));
        usort($hosts, function (MeasuredHost $a, MeasuredHost $b): int {
            $comparison = $this->order === 'name'
                ? strcmp(mb_strtolower($a->name), mb_strtolower($b->name))
                : $a->distancePc <=> $b->distancePc;

            return $comparison !== 0 ? $comparison : strcmp($a->id, $b->id);
        });
        $total = count($hosts);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($this->page > $pages) {
            throw ValidationException::withMessages(['page' => __('Page :page is outside these results. Choose a page from 1 to :pages, or return to the first page.', ['page' => $this->page, 'pages' => $pages])]);
        }

        return new HostDirectoryPage(array_slice($hosts, ($this->page - 1) * self::PER_PAGE, self::PER_PAGE), $total, $pages);
    }

    /** @return array<string, string|int> */
    public function query(int $page): array
    {
        return array_filter(['q' => $this->q, 'radius' => $this->radius, 'order' => $this->order, 'page' => $page], static fn ($value) => $value !== '');
    }
}
