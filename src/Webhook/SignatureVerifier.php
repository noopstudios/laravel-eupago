<?php

namespace Noop\Eupago\Webhook;

use SensitiveParameter;

/**
 * Webhooks 2.0 signature: the X-Signature header is the base64 of the HMAC-SHA256 of the raw body,
 * keyed with the channel's cryptographic key as is. Confirmed against a real sandbox delivery.
 */
class SignatureVerifier
{
    public function verify(string $body, ?string $signature, #[SensitiveParameter] string $secret): bool
    {
        $expected = $signature === null ? false : base64_decode($signature, true);

        return $expected !== false && hash_equals(hash_hmac('sha256', $body, $secret, true), $expected);
    }
}
