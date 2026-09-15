# "In the sky" panel (web) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show every propagatable object's RA/Dec, constellation and hemisphere on its detail page, with a one-click, browser-geolocated observer view (alt/az, up-after-dark, rise/transit/set) remembered only in localStorage.

**Architecture:** `SolarApiClient::sky()` wraps the backend `GET /api/v1/sky/{id}` (solar-system-db PR #5) into `SkyPosition` / `ObserverView` DTOs with the same soft-TTL cache as positions. The object page renders an "In the sky" section when the DTO is non-null (404 → null → section hidden, so this PR can merge before the backend deploys). A Livewire child `SkyObserver` owns the observer state; Alpine handles geolocation and localStorage.

**Tech Stack:** Laravel 13, Livewire 4 (+ bundled Alpine), Pest 4, Tailwind 4.

**Spec:** `solar-system-db/docs/superpowers/specs/2026-09-15-sky-position-design.md`

## Global Constraints

- No accounts, no server-side location storage; location lives in `localStorage['observer_location']` only.
- Observer requests round lat/lon to 0.1° and the datetime down to 5 minutes so the 5-minute cache is effective; geocentric requests round the datetime down to the hour.
- Section hidden (not errored) on 404 / API exceptions; the page never breaks because of the sky call.
- Copy avoids "hazardous/ominous" tone; accuracy note comes from the API.

---

### Task 1: DTOs + client method + `Format::bearing`

**Files:** Create `app/Services/SolarApi/Data/SkyPosition.php`, `app/Services/SolarApi/Data/ObserverView.php`; Modify `app/Services/SolarApi/SolarApiClient.php` (after `position()`), `app/Support/Format.php`, `tests/Pest.php` (fake `/sky/` route); Test `tests/Feature/SolarApiClientTest.php`, `tests/Unit/FormatTest.php`.

**Interfaces (produces):**
```php
SolarApiClient::sky(string $idOrName, ?string $datetime = null, ?float $lat = null, ?float $lon = null): ?SkyPosition
SkyPosition { id fields..., raHms, decDms, raDeg, decDeg, constellationAbbr, constellationName, hemisphere, visibleFrom,
              distanceFromEarthAu, elongationDeg, accuracyNote, ?ObserverView $observer; elongationReading(): string }
ObserverView { lat, lon, altitudeDeg, azimuthDeg, isUp, sunAltitudeDeg, isDark, riseUtc, transitUtc, setUtc, circumpolar, neverRises }
Format::bearing(float $azimuthDeg): string   // 16-point compass: 0→N, 22.5→NNE, 281→WNW
```

- [ ] Step 1: Failing tests
```php
// tests/Unit/FormatTest.php
it('labels azimuths with 16-point compass bearings', function () {
    expect(Format::bearing(0.0))->toBe('N')->and(Format::bearing(22.5))->toBe('NNE')
        ->and(Format::bearing(281.0))->toBe('WNW')->and(Format::bearing(359.0))->toBe('N');
});
// tests/Feature/SolarApiClientTest.php
it('fetches a sky position and maps the observer block', function () {
    $sky = app(SolarApiClient::class)->sky('planet-saturn', '2026-09-15T21:00:00Z', 51.5, -0.12);
    expect($sky)->toBeInstanceOf(SkyPosition::class)
        ->and($sky->raHms)->toBe('23h 12m 04s')->and($sky->constellationName)->toBe('Aquarius')
        ->and($sky->observer?->isUp)->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), '/sky/planet-saturn') && $r['lat'] === 51.5);
});
it('returns null for a sky 404 so the panel simply hides', function () {
    expect(app(SolarApiClient::class)->sky('missing-sky'))->toBeNull();
});
```
- [ ] Step 2: `php artisan test --filter=sky` → fails (method missing).
- [ ] Step 3: Implement (see code in the commit); fake in `tests/Pest.php`:
```php
str_contains($path, '/sky/missing-sky') => Http::response(['detail' => 'not found'], 404),
str_contains($path, '/sky/') => Http::response(skyPayload(observer: isset($request['lat']))),
```
- [ ] Step 4: tests pass. Step 5: commit `feat(api-client): sky position DTOs and client method`.

### Task 2: "In the sky" section on the object page

**Files:** Modify `app/Livewire/Objects/Show.php` (fetch `$sky`), `resources/views/livewire/objects/show.blade.php` (section after "Where is it now"); Test `tests/Feature/SkyPanelTest.php`.

- [ ] Step 1: Failing tests
```php
it('shows the sky panel with RA, Dec, constellation and hemisphere', function () {
    $this->get('/objects/planet-saturn')->assertOk()->assertSee('In the sky')
        ->assertSee('23h 12m 04s')->assertSee('Aquarius')->assertSee('both hemispheres');
});
it('hides the sky panel when the backend has no sky data', function () {
    $this->get('/objects/missing-sky')->assertOk()->assertDontSee('In the sky');
});
```
- [ ] Step 2: fail. Step 3: in `Show::render`, after `$position`:
```php
$sky = null;
if ($object->orbital?->isPropagatable() || $object->objectType === 'moon' || $object->id === 'sun') {
    try { $sky = $api->sky($object->id); } catch (SolarApiException) { /* section hidden */ }
}
```
and pass `'sky' => $sky`. Blade: `<section aria-labelledby="sky-heading">` with a `<dl>` of RA / Dec / Constellation (Wikipedia link `https://en.wikipedia.org/wiki/<name>`) / Distance from Earth / Elongation + reading, the `visibleFrom` sentence, then `<livewire:sky-observer :object-id="$object->id" :key="'sky-'.$object->id" />`, then the accuracy note.
- [ ] Step 4: pass. Step 5: commit `feat(objects): In the sky panel`.

### Task 3: `SkyObserver` Livewire child with geolocation + localStorage

**Files:** Create `app/Livewire/SkyObserver.php`, `resources/views/livewire/sky-observer.blade.php`; Test `tests/Feature/SkyObserverTest.php`.

**Interfaces:** props `string $objectId`; public `?float $lat = null, ?float $lon = null`; `?SkyPosition $sky` is NOT stored (not serialisable) — re-fetched in `render()` when lat/lon set; methods `setLocation(float $lat, float $lon): void`, `forget(): void`.

- [ ] Step 1: Failing tests
```php
it('starts idle with a call to action', fn () => Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
    ->assertSee('Get precise data for my location')->assertDontSee('Altitude'));
it('renders the observer view for a location', fn () => Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
    ->call('setLocation', 51.5, -0.12)->assertSet('lat', 51.5)->assertSee('Altitude')->assertSee('Up now')->assertSee('WNW'));
it('rejects impossible coordinates', fn () => Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
    ->call('setLocation', 123.0, 0.0)->assertHasErrors(['lat']));
it('forgets the location', fn () => Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
    ->call('setLocation', 51.5, -0.12)->call('forget')->assertSet('lat', null)->assertSee('Get precise data for my location'));
```
- [ ] Step 2: fail. Step 3: implement component + Blade (Alpine: `x-data="skyObserver()"`, `x-init` reads localStorage and calls `$wire.setLocation`; button uses `navigator.geolocation.getCurrentPosition`; manual lat/lon form; times rendered with `x-text="new Date(iso).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})"`).
- [ ] Step 4: pass; Pint; PHPStan; `npm run build`. Step 5: commit `feat(objects): observer view with browser geolocation`.

## Self-review
- Spec coverage: client+DTOs ✔ T1; panel fields, elongation reading, hemisphere sentence ✔ T2; observer button, geolocation, manual entry, localStorage, forget link, local-time rendering, accuracy note ✔ T3; null-guard on 404 ✔ T1/T2; tests listed in spec ✔.
- Placeholders: none. Types: `sky()` signature identical in T1/T2/T3; `ObserverView` field names match the API JSON (`is_up` → `isUp`, etc.).
