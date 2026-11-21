<?php

namespace Tests\Unit\Sentry;

use App\Sentry\StripQueryStrings;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

class StripQueryStringsTest extends TestCase
{
    public function test_error_events_drop_incoming_and_outgoing_query_strings(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://publicuniverse.test/settings?s=share-token&theme=dark',
            'query_string' => 'p[lat]=51.5&p[lon]=-0.12',
            'method' => 'GET',
        ]);
        $event->setTransaction('GET https://publicuniverse.test/sky?lat=51.5&lon=-0.12');
        $event->setBreadcrumb([
            new Breadcrumb(
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_HTTP,
                'http',
                'GET https://api.what3words.com/v3/convert-to-coordinates?words=filled.count.soap',
                [
                    'url' => 'https://api.what3words.com/v3/convert-to-coordinates',
                    'http.query' => 'words=filled.count.soap',
                    'http.request.method' => 'GET',
                ],
            ),
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'log', 'user signed in'),
        ]);
        $event->setSpans([
            new Span(SpanContext::make()
                ->setOp('http.client')
                ->setDescription('GET https://api.open-meteo.com/v1/forecast?latitude=51.5&longitude=-0.12')
                ->setData([
                    'url' => 'https://api.open-meteo.com/v1/forecast',
                    'http.query' => 'latitude=51.5&longitude=-0.12',
                    'http.request.method' => 'GET',
                    'url.full' => 'https://api.open-meteo.com/v1/forecast?latitude=51.5&longitude=-0.12#map',
                ])),
        ]);

        $sent = StripQueryStrings::beforeSend($event);

        $this->assertNotNull($sent);
        $payload = $this->payload($sent);

        $this->assertStringNotContainsString('share-token', $payload);
        $this->assertStringNotContainsString('filled.count.soap', $payload);
        $this->assertStringNotContainsString('latitude=51.5', $payload);
        $this->assertStringNotContainsString('p[lat]', $payload);
        $this->assertStringNotContainsString('51.5', $payload);
        $this->assertStringContainsString('user signed in', $payload);
        $this->assertSame('https://publicuniverse.test/settings', $sent->getRequest()['url']);
        $this->assertSame('GET', $sent->getRequest()['method']);
        $this->assertArrayNotHasKey('query_string', $sent->getRequest());
        $this->assertSame('GET https://publicuniverse.test/sky', $sent->getTransaction());
        $this->assertSame(
            'GET https://api.what3words.com/v3/convert-to-coordinates',
            $sent->getBreadcrumbs()[0]->getMessage(),
        );
        $this->assertArrayNotHasKey('http.query', $sent->getBreadcrumbs()[0]->getMetadata());
        $this->assertSame('GET https://api.open-meteo.com/v1/forecast', $sent->getSpans()[0]->getDescription());
        $this->assertSame('https://api.open-meteo.com/v1/forecast', $sent->getSpans()[0]->getData('url.full'));
        $this->assertSame('', $sent->getSpans()[0]->getData('http.query'));
        $this->assertSame('GET', $sent->getSpans()[0]->getData('http.request.method'));
    }

    public function test_transactions_are_scrubbed_the_same_way(): void
    {
        $event = Event::createTransaction();
        $event->setRequest([
            'url' => 'https://publicuniverse.test/api?p[lat]=51.5&p[lon]=-0.12',
            'query_string' => 'p[lat]=51.5&p[lon]=-0.12',
            'method' => 'POST',
        ]);

        $sent = StripQueryStrings::beforeSendTransaction($event);

        $this->assertSame('https://publicuniverse.test/api', $sent?->getRequest()['url']);
        $this->assertArrayNotHasKey('query_string', $sent?->getRequest() ?? []);
        $this->assertSame('POST', $sent?->getRequest()['method']);
    }

    public function test_breadcrumb_hook_strips_a_logged_url_and_leaves_other_text(): void
    {
        $url = StripQueryStrings::beforeBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'log',
            'fetched https://api.open-meteo.com/v1/forecast?latitude=51.5&longitude=-0.12',
            ['http.query' => 'coordinates=51.5,-0.12'],
        ));
        $path = StripQueryStrings::beforeBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_NAVIGATION,
            'navigation',
            null,
            ['url' => '/settings?s=share-token#night'],
        ));
        $plain = StripQueryStrings::beforeBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_ERROR,
            Breadcrumb::TYPE_DEFAULT,
            'log',
            'insert into users (email) values (?, ?)',
        ));

        $this->assertSame(
            'fetched https://api.open-meteo.com/v1/forecast',
            $url?->getMessage(),
        );
        $this->assertArrayNotHasKey('http.query', $url?->getMetadata() ?? []);
        $this->assertSame('/settings', $path?->getMetadata()['url'] ?? null);
        $this->assertSame('insert into users (email) values (?, ?)', $plain?->getMessage());
    }

    public function test_database_bindings_are_still_removed_on_the_way_out(): void
    {
        $pdo = new PDOException("SQLSTATE[23000]: Duplicate entry 'leak@example.com' for key 'users_email_unique'");
        $query = new QueryException('mysql', 'insert into users (email, password) values (?, ?)', ['leak@example.com', 'hunter2'], $pdo);

        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag($query)]);
        $event->setRequest([
            'url' => 'https://publicuniverse.test/register?lat=51.5',
            'method' => 'POST',
        ]);

        $sent = StripQueryStrings::beforeSend($event, EventHint::fromArray(['exception' => $query]));
        $payload = $this->payload($sent);

        $this->assertStringNotContainsString('leak@example.com', $payload);
        $this->assertStringNotContainsString('hunter2', $payload);
        $this->assertStringNotContainsString('lat=51.5', $payload);
        $this->assertStringContainsString('insert into users (email, password) values (?, ?)', $payload);
    }

    private function payload(?Event $event): string
    {
        $this->assertNotNull($event);

        $spanText = [];
        foreach ($event->getSpans() as $span) {
            $spanText[] = (string) $span->getDescription();
            $spanText[] = json_encode($span->getData());
        }

        return implode(' | ', [
            json_encode($event->getRequest()),
            (string) $event->getTransaction(),
            ...array_map(fn (ExceptionDataBag $exception) => $exception->getValue(), $event->getExceptions()),
            ...array_map(
                fn (Breadcrumb $crumb) => (string) $crumb->getMessage().' '.json_encode($crumb->getMetadata()),
                $event->getBreadcrumbs(),
            ),
            ...$spanText,
        ]);
    }
}
