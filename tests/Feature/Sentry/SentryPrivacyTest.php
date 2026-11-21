<?php

namespace Tests\Feature\Sentry;

use App\Sentry\ScrubEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase;
use Nyholm\Psr7\ServerRequest;
use PDOException;
use RuntimeException;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\Laravel\Http\LaravelRequestFetcher;
use Sentry\SentrySdk;
use Sentry\Serializer\PayloadSerializer;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * Extends the framework TestCase rather than Tests\TestCase: these tests need a
 * booted application and its real Sentry config, but no database.
 */
class SentryPrivacyTest extends TestCase
{
    private const SECRETS = [
        'path-token-1f9a',
        'query-secret-77c2',
        'cookie-secret-b410',
        'header-secret-5e3d',
        'referer-secret-8a6f',
        'body-secret-c0de',
        '203.0.113.9',
    ];

    protected function tearDown(): void
    {
        SentrySdk::init();
        unset($_SERVER['PATH_INFO']);

        parent::tearDown();
    }

    public function test_nothing_from_the_request_reaches_a_reported_event(): void
    {
        $transport = new class implements TransportInterface
        {
            /** @var list<Event> */
            public array $events = [];

            public function send(Event $event): Result
            {
                $this->events[] = $event;

                return new Result(ResultStatus::success(), $event);
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResultStatus::success());
            }
        };

        config(['sentry.dsn' => 'https://key@o1.ingest.de.sentry.io/1', 'sentry.transport' => $transport]);
        $this->app->forgetInstance(HubInterface::class);
        $this->app->instance(LaravelRequestFetcher::CONTAINER_PSR7_INSTANCE_KEY, $this->requestCarryingSecrets());
        // Some server setups pass the raw path here, and the SDK falls back to it for the transaction name.
        $_SERVER['PATH_INFO'] = '/reset-password/'.self::SECRETS[0];

        $hub = $this->app->make(HubInterface::class);
        $hub->captureException($this->failure());

        $this->assertCount(1, $transport->events);
        $payload = (new PayloadSerializer($hub->getClient()->getOptions()))->serialize($transport->events[0]);

        $this->assertStringContainsString('boom', $payload);

        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $payload);
        }
    }

    private function failure(): RuntimeException
    {
        return new RuntimeException('boom');
    }

    public function test_breadcrumbs_that_copy_application_data_are_off_by_default(): void
    {
        foreach (['logs', 'cache', 'livewire', 'command_info', 'http_client_requests'] as $breadcrumb) {
            $this->assertFalse(config("sentry.breadcrumbs.{$breadcrumb}"), $breadcrumb);
        }
    }

    public function test_bound_values_are_removed_from_database_exceptions(): void
    {
        $pdo = new PDOException("SQLSTATE[23000]: Duplicate entry 'leak@example.com' for key 'users_email_unique'");
        $query = new QueryException('mysql', 'insert into users (email, password) values (?, ?)', ['leak@example.com', 'hunter2'], $pdo);

        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag($query), new ExceptionDataBag($pdo)]);
        $event->setBreadcrumb([
            new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_DEFAULT, 'log', $query->getMessage()),
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'log', 'user signed in'),
        ]);

        $sent = ScrubEvent::beforeSend($event, EventHint::fromArray(['exception' => $query]));

        $this->assertNotNull($sent);
        $text = implode(' | ', [
            ...array_map(fn (ExceptionDataBag $exception) => $exception->getValue(), $sent->getExceptions()),
            ...array_map(fn (Breadcrumb $crumb) => (string) $crumb->getMessage(), $sent->getBreadcrumbs()),
        ]);

        $this->assertStringNotContainsString('leak@example.com', $text);
        $this->assertStringNotContainsString('hunter2', $text);
        $this->assertStringContainsString('insert into users (email, password) values (?, ?)', $text);
        $this->assertStringContainsString('user signed in', $text);
    }

    public function test_outbound_urls_in_exception_text_lose_their_query(): void
    {
        $exception = new RuntimeException(
            'cURL error 28 for https://api.what3words.com/v3/convert-to-coordinates?words=filled.count.soap',
        );
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag($exception)]);

        $sent = ScrubEvent::beforeSend($event, EventHint::fromArray(['exception' => $exception]));

        $this->assertSame(
            'cURL error 28 for https://api.what3words.com/v3/convert-to-coordinates',
            $sent?->getExceptions()[0]->getValue(),
        );
    }

    public function test_sampled_transactions_drop_urls_and_database_bindings(): void
    {
        $pdo = new PDOException("SQLSTATE[23000]: Duplicate entry 'leak@example.com' for key 'users_email_unique'");
        $query = new QueryException('mysql', 'insert into users (email, password) values (?, ?)', ['leak@example.com', 'hunter2'], $pdo);

        $event = Event::createTransaction();
        $event->setTransaction('reset-password/{token}');
        $event->setRequest([
            'url' => 'https://app.test/reset-password/path-token-1f9a?email=query-secret-77c2',
            'query_string' => 'email=query-secret-77c2',
            'method' => 'GET',
        ]);
        $event->setContext('trace', [
            'span_id' => 'span',
            'trace_id' => 'trace',
            'op' => 'http.server',
            'data' => [
                'url' => '/reset-password/path-token-1f9a',
                'http.request.method' => 'GET',
            ],
        ]);
        $event->setBreadcrumb([
            new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_DEFAULT, 'log', $query->getMessage()),
        ]);

        $sent = ScrubEvent::beforeSend($event, EventHint::fromArray(['exception' => $query]));

        $this->assertNotNull($sent);
        $this->assertSame('reset-password/{token}', $sent->getTransaction());
        $this->assertArrayNotHasKey('url', $sent->getRequest());
        $this->assertArrayNotHasKey('query_string', $sent->getRequest());
        $this->assertSame('GET', $sent->getRequest()['method']);
        $this->assertArrayNotHasKey('url', $sent->getContexts()['trace']['data']);
        $this->assertSame('GET', $sent->getContexts()['trace']['data']['http.request.method']);
        $this->assertStringNotContainsString('path-token-1f9a', json_encode($sent->getContexts()));
        $this->assertStringNotContainsString('leak@example.com', (string) $sent->getBreadcrumbs()[0]->getMessage());
        $this->assertStringNotContainsString('hunter2', (string) $sent->getBreadcrumbs()[0]->getMessage());
    }

    public function test_other_exception_messages_are_left_alone(): void
    {
        $exception = new RuntimeException('disk full');
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag($exception)]);

        $sent = ScrubEvent::beforeSend($event, EventHint::fromArray(['exception' => $exception]));

        $this->assertSame('disk full', $sent?->getExceptions()[0]->getValue());
    }

    private function requestCarryingSecrets(): ServerRequest
    {
        [$path, $query, $cookie, $header, $referer, $body, $ip] = self::SECRETS;

        return (new ServerRequest('POST', "https://app.test/reset-password/{$path}?email={$query}", [
            'Cookie' => "laravel_session={$cookie}",
            'Authorization' => "Bearer {$header}",
            'X-Api-Token' => $header,
            'Referer' => "https://app.test/share?token={$referer}",
            'Content-Type' => 'application/json',
        ], (string) json_encode(['password' => $body]), '1.1', ['REMOTE_ADDR' => $ip]))
            ->withCookieParams(['laravel_session' => $cookie])
            ->withQueryParams(['email' => $query])
            ->withParsedBody(['password' => $body]);
    }
}
