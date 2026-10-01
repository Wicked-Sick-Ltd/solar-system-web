<?php

declare(strict_types=1);

namespace App\Services\Observing;

use DateTimeImmutable;
use DateTimeZone;
use stdClass;

/** Strict browser document boundary; never include private input in exceptions. */
final class SyncPayload
{
    public const int WORKSPACE_BYTES = 262144;

    public const int JOURNAL_BYTES = 1048576;

    public const int MAX_REVISION = 9007199254740990;

    /** @return array{equipmentWorkspace: array<string,mixed>, journal: array<string,mixed>} */
    public function validate(mixed $value): array
    {
        $row = $this->shape($value, ['equipmentWorkspace', 'journal']);

        return ['equipmentWorkspace' => $this->workspace($row['equipmentWorkspace']), 'journal' => $this->journal($row['journal'])];
    }

    /** @return array<string,mixed> */
    public function envelope(mixed $value, bool $upload): array
    {
        $row = $this->shape($value, $upload ? ['expectedRevision', 'payload'] : ['expectedRevision']);
        if (! is_int($row['expectedRevision']) || $row['expectedRevision'] < 0 || $row['expectedRevision'] > self::MAX_REVISION) {
            throw new InvalidSyncPayload;
        }
        if ($upload) {
            $row['payload'] = $this->validate($row['payload']);
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private function workspace(mixed $value, bool $allowLegacy = false): array
    {
        $row = $this->shape($value, ['schemaVersion', 'equipment', 'sites', 'activeSiteId']);
        $version = $row['schemaVersion'];
        if ($version !== 2 && ! ($allowLegacy && $version === 1)) {
            throw new InvalidSyncPayload;
        }
        $equipment = array_map(fn (mixed $entry): array => $this->equipment($entry, $version), $this->list($row['equipment'], 100));
        $sites = array_map(fn (mixed $entry): array => $this->site($entry, $version), $this->list($row['sites'], 100));
        $active = $row['activeSiteId'] === null ? null : $this->uuid($row['activeSiteId']);
        $ids = array_column([...$equipment, ...$sites], 'id');
        if (count(array_unique($ids)) !== count($ids) || ($active !== null && ! in_array($active, array_column($sites, 'id'), true))) {
            throw new InvalidSyncPayload;
        }
        $out = ['schemaVersion' => 2, 'equipment' => $equipment, 'sites' => $sites, 'activeSiteId' => $active];
        if ($version === 1) {
            $legacy = $out;
            $legacy['schemaVersion'] = 1;
            foreach ($legacy['sites'] as &$site) {
                unset($site['horizonMask']);
            }
            unset($site);
            $this->bound($legacy, 131072);
        }
        $this->bound($out, self::WORKSPACE_BYTES);

        return $out;
    }

    /** @return array<string,mixed> */
    private function equipment(mixed $value, int $version): array
    {
        $fields = ['telescope' => ['apertureMm', 'focalLengthMm'], 'binocular' => ['apertureMm', 'magnification'],
            'eyepiece' => ['focalLengthMm', 'apparentFovDeg', 'fieldStopMm'], 'barlow' => ['factor'], 'reducer' => ['factor'],
            'camera' => ['sensorWidthMm', 'sensorHeightMm', 'pixelSizeUm']];
        if (! $value instanceof stdClass || ! is_string($value->kind ?? null) || ! isset($fields[$value->kind]) || ($version === 1 && $value->kind === 'camera')) {
            throw new InvalidSyncPayload;
        }
        $row = $this->shape($value, ['id', 'name', 'kind', ...$fields[$value->kind]]);
        $out = ['id' => $this->uuid($row['id']), 'name' => $this->name($row['name']), 'kind' => $row['kind']];
        $limits = ['apertureMm' => [1, 10000, false], 'focalLengthMm' => [0.1, 100000, false],
            'magnification' => [0.1, 1000, false], 'apparentFovDeg' => [0.1, 180, true], 'fieldStopMm' => [0.1, 500, true],
            'factor' => [$row['kind'] === 'barlow' ? 1 : 0.01, $row['kind'] === 'barlow' ? 20 : 1, false],
            'sensorWidthMm' => [0.01, 1000, false], 'sensorHeightMm' => [0.01, 1000, false], 'pixelSizeUm' => [0.01, 1000, true]];
        foreach ($fields[$row['kind']] as $field) {
            $out[$field] = $this->number($row[$field], ...$limits[$field]);
        }
        if ($row['kind'] === 'camera' && $out['pixelSizeUm'] !== null && min($out['sensorWidthMm'], $out['sensorHeightMm']) < $out['pixelSizeUm'] / 1000) {
            throw new InvalidSyncPayload;
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function site(mixed $value, int $version): array
    {
        $row = $this->shape($value, ['id', 'name', 'latitude', 'longitude', 'timezone', 'minAltitudeDeg', ...($version === 2 ? ['horizonMask'] : [])]);
        if (! is_string($row['timezone']) || ! preg_match('~^[A-Za-z_]+(?:/[A-Za-z0-9_+\-]+)*$~D', $row['timezone'])) {
            throw new InvalidSyncPayload;
        }
        $mask = null;
        if ($version === 2 && $row['horizonMask'] !== null) {
            $mask = [];
            foreach ($this->list($row['horizonMask'], 72) as $point) {
                $point = $this->shape($point, ['azimuthDeg', 'minAltitudeDeg']);
                $azimuth = $this->number($point['azimuthDeg'], 0, 360);
                $mask[] = ['azimuthDeg' => $azimuth == 360 ? 0 : $azimuth, 'minAltitudeDeg' => $this->number($point['minAltitudeDeg'], -90, 90)];
            }
            usort($mask, fn (array $a, array $b): int => $a['azimuthDeg'] <=> $b['azimuthDeg']);
            if (count($mask) < 2 || count(array_unique(array_column($mask, 'azimuthDeg'), SORT_REGULAR)) !== count($mask)) {
                throw new InvalidSyncPayload;
            }
        }

        return ['id' => $this->uuid($row['id']), 'name' => $this->name($row['name']),
            // Match Math.round ties toward positive infinity, including negatives.
            'latitude' => floor($this->number($row['latitude'], -90, 90) * 100 + 0.5) / 100,
            'longitude' => floor($this->number($row['longitude'], -180, 180) * 100 + 0.5) / 100,
            'timezone' => $this->timezone($row['timezone']), 'minAltitudeDeg' => $this->number($row['minAltitudeDeg'], 0, 90), 'horizonMask' => $mask];
    }

    /** @return array<string,mixed> */
    private function journal(mixed $value): array
    {
        $row = $this->shape($value, ['schemaVersion', 'lists', 'observations']);
        if ($row['schemaVersion'] !== 1) {
            throw new InvalidSyncPayload;
        }
        $ids = [];
        $itemCount = 0;
        $lists = [];
        foreach ($this->list($row['lists'], 20) as $list) {
            $list = $this->shape($list, ['id', 'name', 'items']);
            $id = $this->uuid($list['id']);
            $ids[] = $id;
            $targets = [];
            $items = [];
            foreach ($this->list($list['items'], 200) as $item) {
                $item = $this->shape($item, ['id', 'target', 'status']);
                $itemId = $this->uuid($item['id']);
                $ids[] = $itemId;
                $itemCount++;
                $target = $this->target($item['target']);
                $targetKey = $target['catalogue'].':'.$target['id'];
                if (isset($targets[$targetKey])) {
                    throw new InvalidSyncPayload;
                }
                $targets[$targetKey] = true;
                $items[] = ['id' => $itemId, 'target' => $target, 'status' => $this->choice($item['status'], ['planned', 'observed', 'skipped'])];
            }
            $lists[] = ['id' => $id, 'name' => $this->text($list['name'], 100), 'items' => $items];
        }
        if ($itemCount > 2000) {
            throw new InvalidSyncPayload;
        }
        $observations = [];
        foreach ($this->list($row['observations'], 1000) as $observation) {
            $o = $this->shape($observation, ['id', 'target', 'observedAtUtc', 'timezone', 'outcome', 'notes', 'equipmentAndSite']);
            $id = $this->uuid($o['id']);
            $ids[] = $id;
            $snapshot = $o['equipmentAndSite'] === null ? null : $this->workspace($o['equipmentAndSite'], true);
            if ($snapshot !== null && count($snapshot['sites']) > 1) {
                throw new InvalidSyncPayload;
            }
            $observations[] = ['id' => $id, 'target' => $this->target($o['target']), 'observedAtUtc' => $this->instant($o['observedAtUtc']),
                'timezone' => $this->timezone($o['timezone']), 'outcome' => $this->choice($o['outcome'], ['seen', 'not_seen', 'uncertain']),
                'notes' => $this->text($o['notes'], 4000, true), 'equipmentAndSite' => $snapshot];
        }
        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidSyncPayload;
        }
        $out = ['schemaVersion' => 1, 'lists' => $lists, 'observations' => $observations];
        $this->bound($out, self::JOURNAL_BYTES);

        return $out;
    }

    /** @return array{catalogue:string,id:string,label:string} */
    private function target(mixed $value): array
    {
        $row = $this->shape($value, ['catalogue', 'id', 'label']);
        if (! is_string($row['id']) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9:_.+\-]{0,159}$/D', $row['id'])) {
            throw new InvalidSyncPayload;
        }

        return ['catalogue' => $this->choice($row['catalogue'], ['solar', 'starter', 'exoplanet']), 'id' => $row['id'], 'label' => $this->text($row['label'], 200)];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string,mixed>
     */
    private function shape(mixed $value, array $keys): array
    {
        if (! $value instanceof stdClass) {
            throw new InvalidSyncPayload;
        }
        $row = get_object_vars($value);
        if (array_diff(array_keys($row), $keys) !== [] || array_diff($keys, array_keys($row)) !== []) {
            throw new InvalidSyncPayload;
        }

        return $row;
    }

    /** @return list<mixed> */
    private function list(mixed $value, int $max): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > $max) {
            throw new InvalidSyncPayload;
        }

        return $value;
    }

    private function uuid(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $value)) {
            throw new InvalidSyncPayload;
        }

        return strtolower($value);
    }

    private function number(mixed $value, float $min, float $max, bool $nullable = false): int|float|null
    {
        if ($nullable && $value === null) {
            return null;
        }
        if ((! is_int($value) && ! is_float($value)) || ! is_finite($value) || $value < $min || $value > $max) {
            throw new InvalidSyncPayload;
        }

        return $value;
    }

    private function name(mixed $value): string
    {
        if (! is_string($value) || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidSyncPayload;
        }

        return $this->text($this->trim($value), 100);
    }

    private function text(mixed $value, int $max, bool $empty = false): string
    {
        if (! is_string($value) || strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')) / 2 > $max
            || (! $empty && $this->trim($value) === '') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
            throw new InvalidSyncPayload;
        }

        return $this->trim($value);
    }

    private function trim(string $value): string
    {
        // ECMAScript WhiteSpace + LineTerminator, rather than PHP ASCII trim.
        return preg_replace('/^[\x09-\x0d\x20\x{00a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}\x{feff}]+|[\x09-\x0d\x20\x{00a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}\x{feff}]+$/u', '', $value) ?? $value;
    }

    /** @param list<string> $choices */
    private function choice(mixed $value, array $choices): string
    {
        if (! is_string($value) || ! in_array($value, $choices, true)) {
            throw new InvalidSyncPayload;
        }

        return $value;
    }

    private function timezone(mixed $value): string
    {
        if (! is_string($value) || strlen($value) > 100 || ! preg_match('~^[A-Za-z_]+(?:/[A-Za-z0-9_+\-]+)*$~D', $value)) {
            throw new InvalidSyncPayload;
        }
        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC) as $zone) {
            if (strcasecmp($value, $zone) === 0) {
                return in_array($zone, ['Etc/UTC', 'Etc/GMT', 'GMT', 'UCT', 'Universal', 'Zulu'], true) ? 'UTC' : $zone;
            }
        }

        throw new InvalidSyncPayload;
    }

    private function instant(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value)) {
            throw new InvalidSyncPayload;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d\TH:i:s\Z') !== $value) {
            throw new InvalidSyncPayload;
        }

        return $value;
    }

    /** @param array<string,mixed> $value */
    private function bound(array $value, int $max): void
    {
        if (strlen(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS)) > $max) {
            throw new InvalidSyncPayload;
        }
    }
}
