<?php

use App\Services\Observing\InvalidSyncPayload;
use App\Services\Observing\SyncPayload;

it('matches the browser acceptance contract in shared privacy schema fixtures', function () {
    $cases = json_decode(file_get_contents(base_path('tests/fixtures/observing-sync/schema-cases.json')), false, flags: JSON_THROW_ON_ERROR);
    $validator = new SyncPayload;
    foreach ($cases as $case) {
        if (! $case->valid) {
            expect(fn () => $validator->validate($case->payload))->toThrow(InvalidSyncPayload::class);

            continue;
        }
        $result = $validator->validate($case->payload);
        expect($result['equipmentWorkspace']['schemaVersion'])->toBe(2);
        expect($validator->validate(json_decode(json_encode($result, JSON_THROW_ON_ERROR))))->toEqual($result);
        if (str_starts_with($case->name, 'U0085')) {
            expect($result['journal']['observations'][0]['notes'])->toBe("\u{0085}Private\u{0085}");
        }
    }
});
