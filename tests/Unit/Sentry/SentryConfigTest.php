<?php

declare(strict_types=1);

use App\Sentry\StripQueryStrings;

it('keeps the sdk a no-op and stops location data reaching sentry', function () {
    expect(config('sentry.dsn'))->toBeEmpty()
        ->and(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.data_collection'))->toBeNull()
        ->and(config('sentry.max_request_body_size'))->toBe('never')
        ->and(config('sentry.breadcrumbs.cache'))->toBeFalse()
        ->and(config('sentry.breadcrumbs.livewire'))->toBeFalse()
        ->and(config('sentry.tracing.cache'))->toBeFalse()
        ->and(config('sentry.breadcrumbs.http_client_requests'))->toBeFalse()
        ->and(config('sentry.tracing.http_client_requests'))->toBeFalse()
        ->and(config('sentry.breadcrumbs.sql_bindings'))->toBeFalse()
        ->and(config('sentry.tracing.sql_bindings'))->toBeFalse()
        ->and(config('sentry.before_send'))->toBe([StripQueryStrings::class, 'beforeSend'])
        ->and(config('sentry.before_send_transaction'))->toBe([StripQueryStrings::class, 'beforeSendTransaction'])
        ->and(config('sentry.before_breadcrumb'))->toBe([StripQueryStrings::class, 'beforeBreadcrumb']);
});
