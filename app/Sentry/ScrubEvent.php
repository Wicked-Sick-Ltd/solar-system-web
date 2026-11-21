<?php

namespace App\Sentry;

use Illuminate\Database\QueryException;
use PDOException;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;

class ScrubEvent
{
    /**
     * Sentry before_send hook: removes what the SDK options cannot switch off.
     */
    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        // A URL path can itself be a credential (reset, invite and share links). The
        // matched route is still reported as the event's transaction.
        $request = $event->getRequest();
        unset($request['url'], $request['query_string']);
        $event->setRequest($request);

        // Database exception messages carry real values (Laravel inlines the bindings,
        // the driver echoes duplicate keys): send the SQLSTATE code and placeholder SQL.
        $safe = [];

        // Keyed by class: two database exceptions of one class in a chain share a message.
        for ($e = $hint?->exception; $e !== null; $e = $e->getPrevious()) {
            if ($e instanceof QueryException) {
                $safe[$e::class] = sprintf('SQLSTATE[%s] (Connection: %s, SQL: %s)', $e->getCode(), $e->getConnectionName(), $e->getSql());
            } elseif ($e instanceof PDOException) {
                $safe[$e::class] = sprintf('SQLSTATE[%s]', $e->getCode());
            }
        }

        foreach ($event->getExceptions() as $exception) {
            if (isset($safe[$exception->getType()])) {
                $exception->setValue($safe[$exception->getType()]);

                continue;
            }

            // HTTP client failures put the outbound URL, query included, in the message.
            $exception->setValue(self::withoutQuery($exception->getValue()));
        }

        // The same messages reach later events as log breadcrumbs when those are enabled.
        $event->setBreadcrumb(array_map(
            fn (Breadcrumb $crumb) => str_contains((string) $crumb->getMessage(), 'SQLSTATE[')
                ? $crumb->withMessage('[database error message removed]')
                : $crumb,
            $event->getBreadcrumbs(),
        ));

        return $event;
    }

    /**
     * Drop the query and fragment from absolute and path-only URLs in text.
     * A lone "?" (SQL placeholders) is left as it is.
     */
    private static function withoutQuery(string $value): string
    {
        if (! str_contains($value, '?') && ! str_contains($value, '#')) {
            return $value;
        }

        $stripped = preg_replace(
            '~(\b[a-z][a-z0-9+.-]*://[^\s\'"<>?#]+|/[^\s\'"<>?#]*)(?:\?[^\s\'"<>#]*(?:#[^\s\'"<>]*)?|#[^\s\'"<>]*)~i',
            '$1',
            $value,
        );

        return is_string($stripped) ? $stripped : $value;
    }
}
