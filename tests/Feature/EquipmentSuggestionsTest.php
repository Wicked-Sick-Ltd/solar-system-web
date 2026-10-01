<?php

declare(strict_types=1);

it('renders accessible local-only equipment comparison without form submission fields', function () {
    $html = view('observing.equipment-suggestions')->render();
    expect($html)->toContain('data-equipment-suggestions', 'disabled data-suggestions-controls', '<noscript>', 'type="button" data-suggestions-reload', 'data-suggestions-order', 'aria-labelledby="equipment-suggestions-heading"', 'id="equipment-suggestions-error"', 'aria-describedby="equipment-suggestions-error"', 'not saved or sent', 'data-suggestions-context')
        ->not->toContain('<form', 'type="submit"', 'name="', 'wire:model', 'wire:click');
    expect($html)->toContain(route('observatory'), 'optional', 'Catalogue major axis, when reported');
});
