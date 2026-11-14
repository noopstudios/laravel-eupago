<?php

namespace Noop\Eupago\Testing;

use Illuminate\Support\Str;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\EupagoClient;
use Noop\Eupago\Jobs\ProcessWebhookEvent;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Models\PaymentWebhookEvent;

/**
 * Installed by Eupago::fake() for an application's own tests. Requests never leave the process
 * (see FakeEupagoClient) but every rule of the real manager still runs. pay() and expire() play
 * EuPago's webhook for an attempt through the real ProcessWebhookEvent, so the application's
 * handler and the package events fire as in production.
 */
class EupagoFake
{
    public function __construct()
    {
        app()->singleton(EupagoClient::class, FakeEupagoClient::class);
    }

    public function pay(PaymentAttempt $attempt): void
    {
        $this->deliver($attempt, 'Paid', [
            'amount' => ['value' => number_format($attempt->amount_cents / 100, 2, '.', ''), 'currency' => 'EUR'],
            'reference' => $attempt->reference,
            'entity' => $attempt->entity,
            'trid' => (string) random_int(10_000_000, 99_999_999),
            'date' => now('UTC')->format('Y-m-d H:i:s'),
        ]);
    }

    public function expire(PaymentAttempt $attempt): void
    {
        $this->deliver($attempt, 'Expired');
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function deliver(PaymentAttempt $attempt, string $status, array $fields = []): void
    {
        $event = PaymentWebhookEvent::create([
            'event_key' => hash('sha256', 'fake-'.Str::ulid()),
            'identifier' => $attempt->identifier,
            'payload' => ['transaction' => ['identifier' => $attempt->identifier, 'status' => $status] + $fields],
            'status' => EventStatus::Pending,
        ]);

        ProcessWebhookEvent::dispatchSync($event);
    }
}
