<?php

declare(strict_types=1);

namespace App\Services\Mailchimp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin client over the Mailchimp Marketing API for newsletter signups.
 *
 * Uses the idempotent "add or update" member endpoint with double opt-in, so
 * Mailchimp owns the confirmation email and no address is stored locally.
 */
final readonly class MailchimpClient
{
    public function __construct(
        private ?string $apiKey,
        private ?string $audienceId,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array{api_key?: string|null, audience_id?: string|null} $cfg */
        $cfg = (array) config('services.mailchimp', []);

        return new self($cfg['api_key'] ?? null, $cfg['audience_id'] ?? null);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== ''
            && $this->audienceId !== null && $this->audienceId !== ''
            && $this->dataCentre() !== null;
    }

    /** @throws MailchimpException when Mailchimp rejects the address or is unreachable */
    public function subscribe(string $email): SubscribeResult
    {
        if (! $this->isConfigured()) {
            throw new MailchimpException('Mailchimp is not configured.');
        }

        $hash = md5(strtolower(trim($email)));
        $url = sprintf('https://%s.api.mailchimp.com/3.0/lists/%s/members/%s', $this->dataCentre(), $this->audienceId, $hash);

        try {
            $response = Http::withBasicAuth('anystring', (string) $this->apiKey)
                ->acceptJson()
                ->timeout(8)
                ->put($url, [
                    'email_address' => $email,
                    'status_if_new' => 'pending',
                    'tags' => ['sol.wickedsick.com'],
                ]);
        } catch (ConnectionException $e) {
            throw new MailchimpException('Mailchimp unreachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new MailchimpException((string) ($response->json('detail') ?? 'Mailchimp rejected the request.'));
        }

        return $response->json('status') === 'subscribed'
            ? SubscribeResult::AlreadySubscribed
            : SubscribeResult::Pending;
    }

    /** The "usNN" suffix of the API key selects the data centre host. */
    private function dataCentre(): ?string
    {
        if ($this->apiKey === null || ! str_contains($this->apiKey, '-')) {
            return null;
        }

        $dc = substr($this->apiKey, strrpos($this->apiKey, '-') + 1);

        return preg_match('/^[a-z]{2}\d{1,2}$/', $dc) === 1 ? $dc : null;
    }
}
