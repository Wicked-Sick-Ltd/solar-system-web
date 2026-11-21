<?php

namespace Tests\Unit\Sentry;

use App\Sentry\RedactQueryBindings;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;

class RedactQueryBindingsTest extends TestCase
{
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

        $sent = RedactQueryBindings::beforeSend($event, EventHint::fromArray(['exception' => $query]));

        $this->assertNotNull($sent);
        $payload = implode(' | ', [
            ...array_map(fn (ExceptionDataBag $exception) => $exception->getValue(), $sent->getExceptions()),
            ...array_map(fn (Breadcrumb $crumb) => (string) $crumb->getMessage(), $sent->getBreadcrumbs()),
        ]);

        $this->assertStringNotContainsString('leak@example.com', $payload);
        $this->assertStringNotContainsString('hunter2', $payload);
        $this->assertStringContainsString('insert into users (email, password) values (?, ?)', $payload);
        $this->assertStringContainsString('user signed in', $payload);
    }

    public function test_other_exceptions_are_left_alone(): void
    {
        $exception = new \RuntimeException('disk full');
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag($exception)]);

        $sent = RedactQueryBindings::beforeSend($event, EventHint::fromArray(['exception' => $exception]));

        $this->assertSame('disk full', $sent?->getExceptions()[0]->getValue());
    }
}
