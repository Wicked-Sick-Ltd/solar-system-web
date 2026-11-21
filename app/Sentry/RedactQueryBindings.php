<?php

namespace App\Sentry;

use Illuminate\Database\QueryException;
use PDOException;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;

class RedactQueryBindings
{
    /**
     * Sentry before_send hook. Database exception messages carry real values (Laravel
     * inlines the bindings, the driver echoes duplicate keys), so send the SQLSTATE
     * code and the SQL with its placeholders instead.
     */
    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
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
            }
        }

        // The same messages reach later events as log breadcrumbs once Laravel logs the exception.
        $event->setBreadcrumb(array_map(
            fn (Breadcrumb $crumb) => str_contains((string) $crumb->getMessage(), 'SQLSTATE[')
                ? $crumb->withMessage('[database error message removed]')
                : $crumb,
            $event->getBreadcrumbs(),
        ));

        return $event;
    }
}
