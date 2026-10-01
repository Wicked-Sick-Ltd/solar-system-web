<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Services\SolarApi\Data\Exoplanet;
use App\Services\SolarApi\Data\ExoplanetFilters;
use App\Services\SolarApi\Data\Paginated;
use App\Services\SolarApi\Exceptions\SolarApiException;
use JsonException;

final class ExoplanetPageExport
{
    private const array UNITS = [
        'pl_rade' => 'Earth radii', 'pl_bmasse' => 'Earth masses', 'pl_orbper' => 'days',
        'pl_orbsmax' => 'AU', 'pl_eqt' => 'K', 'st_teff' => 'K', 'st_mass' => 'Solar masses',
        'st_rad' => 'Solar radii', 'sy_dist' => 'pc', 'ra' => 'degrees', 'dec' => 'degrees',
    ];

    /**
     * @param  Paginated<Exoplanet>  $page
     * @return array{metadata: array<string, mixed>, results: list<array<string, mixed>>}
     */
    public function document(Paginated $page, ExoplanetFilters $filters): array
    {
        if ($page->count() > ExoplanetFilters::PER_PAGE) {
            throw new SolarApiException('Invalid export page size.');
        }
        $records = [];
        foreach ($page->items as $planet) {
            if ($planet->id === '' || $planet->name === '' || $planet->hostId === '') {
                throw new SolarApiException('Invalid exoplanet export record.');
            }
            $records[] = [
                'id' => $planet->id, 'name' => $planet->name, 'host_id' => $planet->hostId, 'host_name' => $planet->hostName,
                'distance_pc' => $planet->distancePc, 'discovery_method' => $planet->discoveryMethod,
                'discovery_year' => $planet->discoveryYear, 'mass_provenance' => $planet->massProvenance,
                'controversial' => $planet->controversial, 'retrieved_at' => $planet->retrievedAt,
                'archive_url' => $planet->archiveUrl(), 'source_data' => (object) $planet->measurements,
            ];
        }

        return ['metadata' => [
            'schema' => 'public-universe.exoplanets.page.v1', 'scope' => 'current_filtered_page',
            'generated_at' => now()->utc()->toIso8601String(), 'snapshot_id' => $page->catalogueSnapshot?->catalogueId(),
            'catalogue_snapshot' => $page->catalogueSnapshot?->data,
            'snapshot_association' => $page->catalogueSnapshot === null ? 'unassociated' : 'same-read-transaction',
            'snapshot_note' => $page->catalogueSnapshot === null
                ? 'This scientific response is not atomically associated with an immutable catalogue snapshot. A separately observed catalogue identity cannot certify this page; the catalogue can change between requests.'
                : ($page->catalogueSnapshot->catalogueId() === null
                    ? 'The backend read this page and its identity metadata in one transaction, but the catalogue identity is unknown; the reported reason is retained. No immutable snapshot is certified.'
                    : 'The backend read this page and its reported catalogue identity in one transaction. This identity applies to this page only, which may be cached; it does not pin other pages or certify one consistent upstream astronomical model.'),
            'generation_note' => 'generated_at is export serialization time, not the time the backend rows were read. Each record retains its source retrieved_at separately.',
            'source' => 'NASA Exoplanet Archive', 'source_table' => 'PSCompPars',
            'source_url' => 'https://exoplanetarchive.ipac.caltech.edu/docs/PSCompPars.html',
            'filters' => (object) $filters->apiFilters(),
            'pagination' => ['page' => $filters->page, 'offset' => $filters->offset(), 'per_page' => ExoplanetFilters::PER_PAGE,
                'count' => $page->count(), 'has_more' => $page->hasMore],
            'units' => self::UNITS + ['distance_pc' => 'pc', 'discovery_year' => 'year'],
            'measurement_conventions' => [
                'err1' => 'Upper uncertainty, same units as measurement; source sign preserved.',
                'err2' => 'Lower uncertainty, same units as measurement; source sign preserved.',
                'lim' => '1: upper limit; -1: lower limit; 0: not a limit. Absent/null is unknown.',
                '_reflink' => 'Original archive reference text, which may contain HTML; treat as data.',
                'source_data' => 'Original source fields are preserved without rounding; absent keys remain absent and null values remain null.',
            ],
            'csv_conventions' => 'First row is metadata (record_type=metadata); subsequent rows are planets. Blank scalar cells mean missing/null; source_data_json retains the distinction. Potential spreadsheet formulas in strings have an apostrophe prefix. Actual numeric values are not prefixed.',
        ], 'results' => $records];
    }

    /** @param array{metadata: array<string, mixed>, results: list<array<string, mixed>>} $document */
    public function json(array $document): string
    {
        return $this->encode($document);
    }

    /** @param array{metadata: array<string, mixed>, results: list<array<string, mixed>>} $document */
    public function csv(array $document): string
    {
        $columns = ['record_type', 'id', 'name', 'host_id', 'host_name', 'distance_pc', 'discovery_method',
            'discovery_year', 'mass_provenance', 'controversial', 'retrieved_at', 'archive_url'];
        foreach (array_keys(self::UNITS) as $field) {
            foreach (['', 'err1', 'err2', 'lim', '_reflink'] as $suffix) {
                $columns[] = $field.$suffix;
            }
        }
        $columns = [...$columns, 'source_data_json', 'metadata_json'];
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new SolarApiException('Export stream unavailable.');
        }
        try {
            fputcsv($stream, $columns, ',', '"', '', "\r\n");
            $metadata = array_fill_keys($columns, null);
            $metadata['record_type'] = 'metadata';
            $metadata['metadata_json'] = $this->encode($document['metadata']);
            fputcsv($stream, $metadata, ',', '"', '', "\r\n");
            foreach ($document['results'] as $record) {
                $row = $record;
                $row['record_type'] = 'planet';
                $row['metadata_json'] = null;
                $row['source_data_json'] = $this->encode($record['source_data']);
                foreach ((array) $record['source_data'] as $key => $value) {
                    if (in_array($key, $columns, true) && ! array_key_exists($key, $row)) {
                        $row[$key] = $value;
                    }
                }
                $cells = array_map(fn ($column) => $this->csvCell($row[$column] ?? null), $columns);
                fputcsv($stream, $cells, ',', '"', '', "\r\n");
            }
            rewind($stream);
            $csv = stream_get_contents($stream);

            return $csv === false ? throw new SolarApiException('Export stream unreadable.') : $csv;
        } finally {
            fclose($stream);
        }
    }

    private function csvCell(mixed $value): string|int|null
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_string($value)) {
            return preg_match('/^[=+@\-\s\p{Z}\p{C}]/u', $value) ? "'".$value : $value;
        }
        if (is_float($value)) {
            // PHP's ordinary float-to-string cast can round at precision=14.
            // JSON's shortest round-trippable representation retains the value.
            return $this->encode($value);
        }
        if (is_int($value) || $value === null) {
            return $value;
        }

        return $this->encode($value);
    }

    private function encode(mixed $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new SolarApiException('Invalid data in export.', previous: $exception);
        }
    }
}
