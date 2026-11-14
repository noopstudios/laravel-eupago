<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Filament\Resources\PaymentAttempts\Pages\ListPaymentAttempts;
use Noop\Eupago\Filament\Resources\PaymentAttempts\PaymentAttemptResource;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\Pages\ListPaymentWebhookEvents;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\PaymentWebhookEventResource;
use Noop\Eupago\Models\PaymentWebhookEvent;
use Noop\Eupago\Tests\Fixtures\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    actingAs(User::create(['name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'secret']));
    handler();
});

/**
 * Grants the abilities named in eupago.permissions under these keys.
 */
function grantEupago(string ...$keys): void
{
    foreach ($keys as $key) {
        Gate::define((string) config("eupago.permissions.{$key}"), fn () => true);
    }
}

it('forbids both resources and their view pages without the view permissions', function () {
    $attempt = PaymentAttemptResource::getUrl('view', ['record' => attempt()]);
    $event = PaymentWebhookEventResource::getUrl('view', ['record' => PaymentWebhookEvent::factory()->create()]);

    get(PaymentAttemptResource::getUrl('index'))->assertForbidden();
    get(PaymentWebhookEventResource::getUrl('index'))->assertForbidden();
    get($attempt)->assertForbidden();
    get($event)->assertForbidden();

    grantEupago('view_attempts', 'view_events');
    get(PaymentAttemptResource::getUrl('index'))->assertOk();
    get(PaymentWebhookEventResource::getUrl('index'))->assertOk();
    get($attempt)->assertOk();
    get($event)->assertOk();

    // An unset permission grants nothing, whatever the Gate says about the default name.
    config(['eupago.permissions.view_attempts' => null]);
    get(PaymentAttemptResource::getUrl('index'))->assertForbidden();
});

it('reprocesses an orphaned event once its attempt exists', function () {
    grantEupago('view_events', 'reprocess_events');
    $attempt = attempt();
    $event = PaymentWebhookEvent::factory()->status(EventStatus::Orphaned)->create([
        'identifier' => $attempt->identifier,
        'payload' => ['transaction' => [
            'identifier' => $attempt->identifier,
            'status' => 'Paid',
            'amount' => ['value' => '12.50', 'currency' => 'EUR'],
            'reference' => $attempt->reference,
            'entity' => $attempt->entity,
            'trid' => '7654321',
        ]],
        'processed_at' => now(),
    ]);
    $processed = PaymentWebhookEvent::factory()->status(EventStatus::Processed)->create();

    Livewire::test(ListPaymentWebhookEvents::class)
        ->assertActionHidden(TestAction::make('reprocess')->table($processed))
        ->callAction(TestAction::make('reprocess')->table($event));

    expect($event->fresh()?->status)->toBe(EventStatus::Processed)
        ->and($attempt->fresh()?->status)->toBe(AttemptStatus::Paid)
        ->and(handler()->received)->toHaveCount(1);
});

it('does not reprocess without the permission', function () {
    grantEupago('view_events');
    $attempt = attempt();
    $event = PaymentWebhookEvent::factory()->status(EventStatus::Orphaned)->create([
        'identifier' => $attempt->identifier,
        'payload' => ['transaction' => ['identifier' => $attempt->identifier, 'status' => 'Expired']],
        'processed_at' => now(),
    ]);

    // Mounted and called directly, past the visibility check of callAction().
    $run = fn () => Livewire::test(ListPaymentWebhookEvents::class)
        ->mountAction(TestAction::make('reprocess')->table($event))
        ->callMountedAction();

    Livewire::test(ListPaymentWebhookEvents::class)->assertActionHidden(TestAction::make('reprocess')->table($event));
    $run();
    expect($event->fresh()?->status)->toBe(EventStatus::Orphaned)
        ->and($attempt->fresh()?->status)->toBe(AttemptStatus::Pending);

    // The same call goes through once the permission is granted.
    grantEupago('reprocess_events');
    $run();
    expect($event->fresh()?->status)->toBe(EventStatus::Processed)
        ->and($attempt->fresh()?->status)->toBe(AttemptStatus::Expired);
});

it('marks only an unknown attempt as failed', function () {
    grantEupago('view_attempts', 'abandon_attempts');
    $unknown = attempt(['status' => AttemptStatus::Unknown]);
    $pending = attempt();
    $paid = attempt(['status' => AttemptStatus::Paid]);

    Livewire::test(ListPaymentAttempts::class)
        ->assertActionHidden(TestAction::make('abandon')->table($pending))
        ->assertActionHidden(TestAction::make('abandon')->table($paid))
        ->callAction(TestAction::make('abandon')->table($unknown));

    expect($unknown->fresh()?->status)->toBe(AttemptStatus::Failed)
        ->and(handler()->expired)->toBe([$unknown->id])
        ->and($pending->fresh()?->status)->toBe(AttemptStatus::Pending)
        ->and($paid->fresh()?->status)->toBe(AttemptStatus::Paid);
});

it('does not mark an attempt as failed without the permission', function () {
    grantEupago('view_attempts');
    $unknown = attempt(['status' => AttemptStatus::Unknown]);

    // Mounted and called directly, past the visibility check of callAction().
    $run = fn () => Livewire::test(ListPaymentAttempts::class)
        ->mountAction(TestAction::make('abandon')->table($unknown))
        ->callMountedAction();

    Livewire::test(ListPaymentAttempts::class)->assertActionHidden(TestAction::make('abandon')->table($unknown));
    $run();
    expect($unknown->fresh()?->status)->toBe(AttemptStatus::Unknown);

    // The same call goes through once the permission is granted.
    grantEupago('abandon_attempts');
    $run();
    expect($unknown->fresh()?->status)->toBe(AttemptStatus::Failed);
});
