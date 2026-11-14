<?php

namespace Noop\Eupago\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\PaymentMethod;
use Noop\Eupago\Models\PaymentAttempt;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    /**
     * A pending Multibanco attempt; pass `for($payable, 'payable')` to attach it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => 'TEST-'.Str::ulid(),
            'method' => PaymentMethod::Multibanco,
            'amount_cents' => 1250,
            'entity' => '12345',
            'reference' => (string) fake()->numerify('#########'),
            'status' => AttemptStatus::Pending,
            'expires_at' => now()->addDays(3),
        ];
    }

    public function mbway(): static
    {
        return $this->state([
            'method' => PaymentMethod::Mbway,
            'entity' => null,
            'transaction_id' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function status(AttemptStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function paid(): static
    {
        return $this->state(['status' => AttemptStatus::Paid, 'trid' => (string) fake()->numerify('########'), 'paid_at' => now()]);
    }
}
