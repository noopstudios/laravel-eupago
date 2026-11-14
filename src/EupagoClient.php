<?php

namespace Noop\Eupago;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Noop\Eupago\Enums\FailureType;
use Noop\Eupago\Exceptions\EupagoException;
use RuntimeException;
use SensitiveParameter;

/**
 * The two EuPago endpoints the package calls. EuPago is not idempotent by identifier, so a request
 * is never repeated: anything other than a clear success or an explicit refusal is Unknown.
 */
class EupagoClient
{
    /**
     * @return array{entity: string, reference: string, transaction_id: null}
     */
    public function multibanco(string $identifier, int $amountCents, CarbonInterface $until): array
    {
        $key = $this->apiKey();

        $response = $this->send('Multibanco', fn (PendingRequest $http) => $http->post($this->host().'/clientes/rest_api/multibanco/create', [
            'chave' => $key,
            'valor' => $this->amount($amountCents),
            'id' => $identifier,
            'per_dup' => 0,
            'data_fim' => $until->format('Y-m-d'),
        ]));

        $body = $response->json();

        if ($response->successful() && is_array($body)) {
            if (($body['sucesso'] ?? null) === true && $this->filled($body, 'referencia') && $this->filled($body, 'entidade')) {
                return ['entity' => (string) $body['entidade'], 'reference' => (string) $body['referencia'], 'transaction_id' => null];
            }

            if (($body['sucesso'] ?? null) === false) {
                $reason = is_string($body['resposta'] ?? null) ? html_entity_decode($body['resposta']) : '';

                throw new EupagoException($this->redact('EuPago refused the Multibanco reference: '.$reason, $key), FailureType::Rejected);
            }
        }

        throw $this->unknown('Multibanco');
    }

    /**
     * @param  string  $phone  Nine digits, already normalised.
     * @return array{entity: null, reference: string, transaction_id: string|null}
     */
    public function mbway(string $identifier, int $amountCents, #[SensitiveParameter] string $phone): array
    {
        $key = $this->apiKey();

        $response = $this->send('MB WAY', fn (PendingRequest $http) => $http
            ->withHeader('Authorization', 'ApiKey '.$key)
            ->post($this->host().'/api/v1.02/mbway/create', [
                'payment' => [
                    'identifier' => $identifier,
                    'amount' => ['value' => $this->amount($amountCents), 'currency' => 'EUR'],
                    'customerPhone' => $phone,
                    'countryCode' => '+351',
                ],
            ]));

        $body = $response->json();

        if ($response->successful() && is_array($body) && ($body['transactionStatus'] ?? null) === 'Success' && $this->filled($body, 'reference')) {
            return [
                'entity' => null,
                'reference' => (string) $body['reference'],
                'transaction_id' => $this->filled($body, 'transactionID') ? (string) $body['transactionID'] : null,
            ];
        }

        if ($response->clientError() && is_array($body) && ($body['transactionStatus'] ?? null) === 'Rejected') {
            $code = is_scalar($body['code'] ?? null) ? (string) $body['code'] : '';
            $text = is_scalar($body['text'] ?? null) ? (string) $body['text'] : '';

            throw new EupagoException($this->redact("EuPago refused the MB WAY request: {$code}: {$text}", $key, $phone), FailureType::Rejected);
        }

        throw $this->unknown('MB WAY');
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(string $method, callable $request): Response
    {
        try {
            return $request(Http::acceptJson()->asJson()->withoutRedirecting()->connectTimeout(5)->timeout(15));
        } catch (ConnectionException) {
            // Covers timeouts too: the request may have reached EuPago and created a reference.
            throw $this->unknown($method);
        }
    }

    /** Fails when no API key is configured, so the caller can check before creating anything. */
    public function assertConfigured(): void
    {
        $this->apiKey();
    }

    private function unknown(string $method): EupagoException
    {
        return new EupagoException("EuPago did not confirm the {$method} request; a payable reference may exist.", FailureType::Unknown);
    }

    private function apiKey(): string
    {
        $key = config('eupago.api_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('EuPago is not configured: set EUPAGO_API_KEY.');
        }

        return $key;
    }

    private function host(): string
    {
        return config('eupago.sandbox') ? 'https://sandbox.eupago.pt' : 'https://clientes.eupago.pt';
    }

    /** Integer cents as a decimal string with two places, without float math. */
    private function amount(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private function filled(array $body, string $field): bool
    {
        return is_scalar($body[$field] ?? null) && (string) $body[$field] !== '';
    }

    /** EuPago's own text is echoed into the message: strip anything secret it might repeat. */
    private function redact(string $message, #[SensitiveParameter] string ...$secrets): string
    {
        return str_replace($secrets, '[redacted]', $message);
    }
}
