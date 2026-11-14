<?php

namespace Noop\Eupago\Testing;

use Carbon\CarbonInterface;
use Noop\Eupago\EupagoClient;
use SensitiveParameter;

/**
 * Stands in for the EuPago client under Eupago::fake(): no HTTP, no API key, fictitious values.
 */
class FakeEupagoClient extends EupagoClient
{
    private int $last = 0;

    public function assertConfigured(): void {}

    public function multibanco(string $identifier, int $amountCents, CarbonInterface $until): array
    {
        return ['entity' => '99999', 'reference' => $this->next(), 'transaction_id' => null];
    }

    public function mbway(string $identifier, int $amountCents, #[SensitiveParameter] string $phone): array
    {
        $reference = $this->next();

        return ['entity' => null, 'reference' => $reference, 'transaction_id' => "fake-mbway-{$reference}"];
    }

    private function next(): string
    {
        return str_pad((string) ++$this->last, 9, '0', STR_PAD_LEFT);
    }
}
