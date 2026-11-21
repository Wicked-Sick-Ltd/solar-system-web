<?php

declare(strict_types=1);

namespace App\Sentry;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Tracing\Span;

/**
 * Removes query strings and fragments before an event leaves the process.
 *
 * send_default_pii false does not do this. With data_collection left null
 * (so the SDK stays on the conservative legacy policy), the incoming request
 * URL and http.query on breadcrumbs and spans still carry observer coordinates
 * and what3words addresses. Path secrets, such as a Slack webhook, are kept
 * out by leaving HTTP client collection off; this class only strips the query.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/data-management/sensitive-data/
 */
class StripQueryStrings
{
    /**
     * before_send. Also reduces database exception text to placeholder SQL.
     */
    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        self::stripEvent($event);

        return RedactQueryBindings::beforeSend($event, $hint);
    }

    /**
     * before_send_transaction. Transactions do not carry the exception text
     * the SQL hook rewrites, but they do carry the request URL and spans.
     */
    public static function beforeSendTransaction(Event $event, ?EventHint $hint = null): Event
    {
        self::stripEvent($event);

        return $event;
    }

    /**
     * before_breadcrumb. Runs as the breadcrumb is recorded, including log
     * lines that quote an outbound URL.
     */
    public static function beforeBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        $message = $breadcrumb->getMessage();
        $strippedMessage = $message === null ? null : self::stripQueriesInText($message);
        $metadata = self::omitQueryKeys(self::stripMap($breadcrumb->getMetadata()));

        if ($strippedMessage === $message && $metadata === $breadcrumb->getMetadata()) {
            return $breadcrumb;
        }

        return new Breadcrumb(
            $breadcrumb->getLevel(),
            $breadcrumb->getType(),
            $breadcrumb->getCategory(),
            $strippedMessage,
            $metadata,
            $breadcrumb->getTimestamp(),
        );
    }

    private static function stripEvent(Event $event): void
    {
        $request = $event->getRequest();
        if ($request !== []) {
            $event->setRequest(self::omitQueryKeys(self::stripMap($request)));
        }

        $transaction = $event->getTransaction();
        if (is_string($transaction)) {
            $stripped = self::stripQueriesInText($transaction);
            if ($stripped !== $transaction) {
                $event->setTransaction($stripped);
            }
        }

        $message = $event->getMessage();
        if (is_string($message)) {
            $stripped = self::stripQueriesInText($message);
            if ($stripped !== $message) {
                $event->setMessage($stripped);
            }
        }

        $event->setBreadcrumb(array_map(
            fn (Breadcrumb $breadcrumb): Breadcrumb => self::beforeBreadcrumb($breadcrumb),
            $event->getBreadcrumbs(),
        ));

        foreach ($event->getSpans() as $span) {
            self::stripSpan($span);
        }

        foreach ($event->getContexts() as $name => $context) {
            $stripped = self::omitQueryKeys(self::stripMap($context));
            if ($stripped !== [] && $stripped !== $context) {
                $event->setContext($name, $stripped);
            }
        }
    }

    /**
     * Span::setData() merges, so a missing key would leave the old query in place.
     * Blank the query keys instead of dropping them.
     */
    private static function stripSpan(Span $span): void
    {
        $description = $span->getDescription();
        if (is_string($description)) {
            $span->setDescription(self::stripQueriesInText($description));
        }

        $updates = [];
        foreach ($span->getData() as $key => $value) {
            if (str_starts_with($key, 'flag.evaluation.')) {
                continue;
            }

            if ($key === 'http.query' || $key === 'query_string') {
                if ($value !== '') {
                    $updates[$key] = '';
                }

                continue;
            }

            if (is_string($value)) {
                $stripped = self::stripQueriesInText($value);
                if ($stripped !== $value) {
                    $updates[$key] = $stripped;
                }

                continue;
            }

            if (is_array($value)) {
                $stripped = self::omitQueryKeys(self::stripMap($value));
                if ($stripped !== $value) {
                    $updates[$key] = $stripped;
                }
            }
        }

        if ($updates !== []) {
            $span->setData($updates);
        }
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function stripMap(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::stripMap($value);

                continue;
            }

            if (is_string($value)) {
                $data[$key] = self::stripQueriesInText($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function omitQueryKeys(array $data): array
    {
        unset($data['http.query'], $data['query_string']);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::omitQueryKeys($value);
            }
        }

        return $data;
    }

    /**
     * Drop the query and fragment from absolute and path-only URLs embedded in text.
     * SQL placeholders are not URLs, so a lone "?" is left as it is.
     */
    public static function stripQueriesInText(string $value): string
    {
        if (! str_contains($value, '?') && ! str_contains($value, '#')) {
            return $value;
        }

        // The query group stops before a fragment so "?a=1#b" loses both.
        // A bare URL with neither is left unchanged.
        $stripped = preg_replace(
            '~(\b[a-z][a-z0-9+.-]*://[^\s\'"<>?#]+|/[^\s\'"<>?#]*)(?:\?[^\s\'"<>#]*(?:#[^\s\'"<>]*)?|#[^\s\'"<>]*)~i',
            '$1',
            $value,
        );

        return is_string($stripped) ? $stripped : $value;
    }
}
