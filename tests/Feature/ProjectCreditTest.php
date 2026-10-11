<?php

declare(strict_types=1);

beforeEach(fn () => fakeSolar());

it('credits Grok Bot, xAI and Cursor on the about page and in the site-wide footer', function (string $path) {
    $this->get($path)->assertOk()
        ->assertSee('Built with Grok Bot (xAI) and Cursor, a Wicked Sick pro bono project.');
})->with(['/', '/about', '/plugin']);

it('credits Grok Bot, xAI and Cursor in the README', function () {
    expect(file_get_contents(base_path('README.md')))
        ->toContain('Built with Grok Bot (xAI) and Cursor, a Wicked Sick pro bono project.');
});
