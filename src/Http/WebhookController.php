<?php

namespace Noop\Eupago\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Jobs\ProcessWebhookEvent;
use Noop\Eupago\Models\PaymentWebhookEvent;
use Noop\Eupago\Webhook\SignatureVerifier;

/**
 * Receives EuPago's Webhooks 2.0: verifies the signature over the raw body, stores the event once
 * (keyed by the hash of the body) and leaves the processing to ProcessWebhookEvent.
 */
class WebhookController
{
    public function __invoke(Request $request, SignatureVerifier $verifier): Response
    {
        $secret = config('eupago.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            return response('', 503);
        }

        $body = $request->getContent();

        if (! $verifier->verify($body, $request->header('X-Signature'), $secret)) {
            // No context: the body, the header and the secret stay out of the logs.
            Log::warning('EuPago webhook refused: invalid or missing signature.');

            return response('', 401);
        }

        $payload = json_decode($body, true);
        // An encrypted body ("data" at the root) is not supported: the channel must not encrypt.
        $transaction = is_array($payload) && ! array_key_exists('data', $payload) ? ProcessWebhookEvent::transaction($payload) : null;

        if ($transaction === null) {
            return response('', 422);
        }

        $event = PaymentWebhookEvent::createOrFirst(['event_key' => hash('sha256', $body)], [
            'identifier' => $transaction['identifier'],
            'payload' => $payload,
            'status' => EventStatus::Pending,
        ]);

        if ($event->wasRecentlyCreated) {
            ProcessWebhookEvent::dispatch($event)->afterCommit();
        }

        return response('', 200);
    }
}
