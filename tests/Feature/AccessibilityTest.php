<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => fakeSolar());

function accessibilityDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

it('provides a keyboard focus destination for the skip link on main journeys', function (string $path) {
    $document = accessibilityDocument($this->get($path)->assertOk()->getContent());
    $target = $document->evaluate('string(//body/a[1]/@href)');

    expect($target)->toBe('#main')
        ->and($document->evaluate('count(//main[@id="main"][@tabindex="-1"])'))->toBe(1.0);
})->with(['/', '/explore', '/observe', '/learn', '/search?q=Saturn', '/login']);

it('offers a labelled mobile search with a native submit action and retained query', function () {
    $document = accessibilityDocument($this->get('/search?q=Saturn')->assertOk()->getContent());
    $form = '//nav[@id="mobile-nav"]/div/form[@role="search"]';

    expect($document->evaluate("string($form/@action)"))->toBe(route('search'))
        ->and(strtoupper($document->evaluate("string($form/@method)")))->toBe('GET')
        ->and($document->evaluate("string($form/input[@name='q']/@value)"))->toBe('Saturn')
        ->and($document->evaluate("string($form/label[@for='mobile-search'])"))->toBe('Search the catalogue')
        ->and(trim($document->evaluate("string($form/button[@type='submit'])")))->toBe('Search');
});

it('renders named icon controls and a disclosure connected to the mobile navigation', function () {
    $document = accessibilityDocument($this->get('/explore')->assertOk()->getContent());

    expect($document->evaluate('count(//header//button[@aria-label="Switch to light theme"])'))->toBe(1.0)
        ->and($document->evaluate('count(//header//button[@aria-label="Menu"][@aria-expanded="false"][@aria-controls="mobile-nav"])'))->toBe(1.0)
        ->and($document->evaluate('count(//nav[@id="mobile-nav"]//a[@aria-current="page"])'))->toBe(1.0);
});

it('associates server validation errors with the invalid account fields', function (string $path, array $fields) {
    $this->from($path)->post($path, [])->assertRedirect($path)->assertSessionHasErrors($fields);
    // Carry the session cookie across the redirect, as a browser does.
    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($path);
    $document = accessibilityDocument($response->assertOk()->getContent());
    foreach ($fields as $field) {
        expect($document->evaluate("string(//input[@id='$field']/@aria-invalid)"))->toBe('true');
        $descriptionId = $document->evaluate("string(//input[@id='$field']/@aria-describedby)");
        expect($descriptionId)->not->toBe('')
            ->and($document->evaluate("count(//*[@id='$descriptionId'])"))->toBe(1.0)
            ->and(trim($document->evaluate("string(//*[@id='$descriptionId'])")))->not->toBe('');
    }
})->with([
    'sign in' => ['/login', ['email', 'password']],
    'registration' => ['/register', ['name', 'email', 'password']],
]);

it('does not mark untouched account fields invalid', function (string $path) {
    $document = accessibilityDocument($this->get($path)->assertOk()->getContent());

    expect($document->evaluate('count(//input[@aria-invalid="true"])'))->toBe(0.0)
        ->and($document->evaluate('count(//input[@aria-describedby])'))->toBe(0.0);
})->with(['/login', '/register']);
