<?php

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\FailureType;
use Noop\Eupago\Exceptions\EupagoException;
use Noop\Eupago\Facades\Eupago;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Tests\Fixtures\Order;

use function Pest\Laravel\travelTo;

beforeEach(fn () => Http::preventStrayRequests());

function multibancoOk(string $reference = '800000001'): PromiseInterface
{
    return Http::response(['sucesso' => true, 'estado' => 0, 'resposta' => 'OK', 'referencia' => $reference, 'entidade' => '12345', 'valor' => '12.34000']);
}

function mbwayOk(string $reference = '400001'): PromiseInterface
{
    return Http::response(['transactionStatus' => 'Success', 'transactionID' => 'tx-'.$reference, 'reference' => $reference], 201);
}

function requestPayment(string $method, Order $order, int $amountCents = 1234): PaymentAttempt
{
    return $method === 'multibanco'
        ? Eupago::multibanco($order, $amountCents)
        : Eupago::mbway($order, $amountCents, '912345678');
}

function failure(Closure $call): EupagoException
{
    try {
        $call();
    } catch (EupagoException $e) {
        return $e;
    }

    throw new RuntimeException('Expected an EupagoException.');
}

dataset('methods', ['multibanco', 'mbway']);

it('reuses a pending Multibanco reference for the same amount and creates a new one for another amount', function () {
    Http::fake(['*' => Http::sequence()->pushResponse(multibancoOk('800000001'))->whenEmpty(multibancoOk('800000002'))]);
    $order = Order::create();

    $first = Eupago::multibanco($order, 1234);
    $again = Eupago::multibanco($order, 1234);
    $other = Eupago::multibanco($order, 1500);

    expect($again->id)->toBe($first->id)
        ->and($first->status)->toBe(AttemptStatus::Pending)
        ->and($first->reference)->toBe('800000001')
        ->and($other->id)->not->toBe($first->id)
        ->and($other->reference)->toBe('800000002');
    Http::assertSentCount(2);
});

it('sends a new MB WAY request every time', function () {
    Http::fake(['*' => mbwayOk()]);
    $order = Order::create();

    $first = Eupago::mbway($order, 1234, '912345678');
    $second = Eupago::mbway($order, 1234, '912345678');

    expect($second->id)->not->toBe($first->id)
        ->and($second->status)->toBe(AttemptStatus::Pending)
        ->and($second->transaction_id)->toBe('tx-400001');
    Http::assertSentCount(2);
});

it('leaves the attempt unknown when the response is lost and blocks the next request', function (string $method, mixed $response) {
    Http::fake(['*' => $response]);
    $order = Order::create();

    $lost = failure(fn () => requestPayment($method, $order));
    $next = failure(fn () => requestPayment($method, $order));

    expect($lost->type)->toBe(FailureType::Unknown)
        ->and($lost->attempt?->fresh()?->status)->toBe(AttemptStatus::Unknown)
        ->and($next->type)->toBe(FailureType::Blocked)
        ->and($next->attempt?->id)->toBe($lost->attempt?->id);
    Http::assertSentCount(1);
})->with('methods')->with([
    'connection error or timeout' => fn () => Http::failedConnection(),
    'server error' => fn () => Http::response(['sucesso' => true], 500),
    'unreadable body' => fn () => Http::response('<html>Bad gateway</html>', 200),
    'incomplete body' => fn () => Http::response(['sucesso' => true, 'transactionStatus' => 'Success'], 200),
    'redirect, never followed' => fn () => Http::response('', 307, ['Location' => 'https://elsewhere.test/create']),
]);

it('leaves the attempt unknown, without leaking, when the send fails with an unexpected error', function (string $method) {
    $calls = 0;
    Http::fake(['*' => function () use (&$calls) {
        $calls++;

        throw new RuntimeException('boom fake-api-key 912345678');
    }]);

    $failure = failure(fn () => requestPayment($method, Order::create()));

    expect($failure->type)->toBe(FailureType::Unknown)
        ->and($failure->getPrevious())->toBeNull()
        ->and($failure->getMessage())->not->toContain('boom')
        ->and($failure->attempt?->fresh()?->status)->toBe(AttemptStatus::Unknown)
        ->and($failure->attempt?->fresh()?->error_message)->toBe($failure->getMessage())
        ->and($calls)->toBe(1);
})->with('methods');

it('has the attempt stored as creating before EuPago is called', function (string $method) {
    $seen = [];
    Http::fake(['*' => function () use (&$seen, $method) {
        $seen[] = PaymentAttempt::query()->value('status');

        return $method === 'multibanco' ? multibancoOk() : mbwayOk();
    }]);

    requestPayment($method, Order::create());

    expect($seen)->toBe([AttemptStatus::Creating]);
})->with('methods');

it('does not reuse an expired pending Multibanco reference', function () {
    Http::fake(['*' => multibancoOk('800000009')]);
    $order = Order::create();
    $expired = PaymentAttempt::factory()->for($order, 'payable')->create(['amount_cents' => 1234, 'expires_at' => now()->subMinute()]);

    $attempt = Eupago::multibanco($order, 1234);

    expect($attempt->id)->not->toBe($expired->id)
        ->and($attempt->reference)->toBe('800000009');
    Http::assertSentCount(1);
});

it('marks an explicit refusal as failed without blocking the next request or leaking secrets', function (string $method, PromiseInterface $refusal, PromiseInterface $success) {
    Http::fake(['*' => Http::sequence()->pushResponse($refusal)->whenEmpty($success)]);
    $order = Order::create();

    $refused = failure(fn () => requestPayment($method, $order));
    $next = requestPayment($method, $order);

    expect($refused->type)->toBe(FailureType::Rejected)
        ->and($refused->attempt?->fresh()?->status)->toBe(AttemptStatus::Failed)
        ->and($refused->attempt?->fresh()?->error_message)->toBe($refused->getMessage())
        ->and($refused->getMessage())->toContain('inválida')
        ->and($refused->getMessage())->not->toContain('fake-api-key')
        ->and($refused->getMessage())->not->toContain('912345678')
        ->and($next->status)->toBe(AttemptStatus::Pending);
    Http::assertSentCount(2);
})->with([
    'multibanco refusal on HTTP 200' => ['multibanco', fn () => Http::response(['sucesso' => false, 'estado' => -10, 'resposta' => 'Chave fake-api-key inv&aacute;lida.']), fn () => multibancoOk()],
    'mbway refusal on HTTP 400' => ['mbway', fn () => Http::response(['transactionStatus' => 'Rejected', 'code' => 'PHONE_INVALID', 'text' => 'Telefone 912345678 inválida (fake-api-key)'], 400), fn () => mbwayOk()],
]);

it('blocks once the payable reached max_attempts', function () {
    config(['eupago.max_attempts' => 2]);
    Http::fake(['*' => multibancoOk()]);
    $order = Order::create();
    $refused = fn (int $count, int $minutesAgo) => PaymentAttempt::factory()->for($order, 'payable')->status(AttemptStatus::Failed)
        ->count($count)->create(['reference' => null, 'created_at' => now()->subMinutes($minutesAgo)]);

    // Refusals without a reference stop counting after 15 minutes...
    $refused(3, 16);
    PaymentAttempt::factory()->for($order, 'payable')->status(AttemptStatus::Expired)->create();
    expect(Eupago::multibanco($order, 1234)->status)->toBe(AttemptStatus::Pending);

    // ...but recent ones count.
    $other = Order::create();
    PaymentAttempt::factory()->for($other, 'payable')->status(AttemptStatus::Failed)->count(2)->create(['reference' => null]);
    expect(failure(fn () => Eupago::multibanco($other, 1234))->type)->toBe(FailureType::Blocked);
    Http::assertSentCount(1);
});

it('blocks without creating anything while another request holds the payable lock', function () {
    Sleep::fake(syncWithCarbon: true);
    Http::fake();
    $order = Order::create();
    Cache::lock('eupago:payable:'.$order->getMorphClass().':'.$order->id, 30)->get();

    expect(failure(fn () => Eupago::multibanco($order, 1234))->type)->toBe(FailureType::Blocked)
        ->and(PaymentAttempt::count())->toBe(0);
    Http::assertNothingSent();
});

it('sends the Multibanco request with the key in the body and a Lisbon end date', function (bool $sandbox, string $url) {
    config(['eupago.sandbox' => $sandbox]);
    travelTo(now()->parse('2026-07-10 23:30:00', 'UTC')); // already 11 July in Lisbon (UTC+1)
    Http::fake(['*' => multibancoOk()]);

    $attempt = Eupago::multibanco(Order::create(), 1205);

    Http::assertSent(fn (Request $request) => $request->url() === $url
        && $request->data() === [
            'chave' => 'fake-api-key',
            'valor' => '12.05',
            'id' => $attempt->identifier,
            'per_dup' => 0,
            'data_fim' => '2026-07-14',
        ]);
    expect($attempt->identifier)->toMatch('/^TEST-[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($attempt->entity)->toBe('12345')
        ->and(DB::table('payment_attempts')->value('expires_at'))->toBe('2026-07-14 22:59:59');
})->with([
    'sandbox' => [true, 'https://sandbox.eupago.pt/clientes/rest_api/multibanco/create'],
    'production' => [false, 'https://clientes.eupago.pt/clientes/rest_api/multibanco/create'],
]);

it('sends the MB WAY request with the key in the header and the phone as nine digits', function (string $phone) {
    Http::fake(['*' => mbwayOk()]);

    $attempt = Eupago::mbway(Order::create(), 5, $phone);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://sandbox.eupago.pt/api/v1.02/mbway/create'
        && $request->header('Authorization') === ['ApiKey fake-api-key']
        && $request->data() === ['payment' => [
            'identifier' => $attempt->identifier,
            'amount' => ['value' => '0.05', 'currency' => 'EUR'],
            'customerPhone' => '912345678',
            'countryCode' => '+351',
        ]]);
})->with(['+351 912 345 678', '00351912345678', '351912345678']);

it('refuses an invalid phone without creating an attempt or calling EuPago', function (string $phone) {
    Http::fake();

    expect(failure(fn () => Eupago::mbway(Order::create(), 1234, $phone))->type)->toBe(FailureType::Rejected)
        ->and(PaymentAttempt::count())->toBe(0);
    Http::assertNothingSent();
})->with(['91234567', '+44 912345678', '9123456789']);
