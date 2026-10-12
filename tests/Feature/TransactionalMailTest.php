<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Mail\LoginCodeMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\SubscriptionCancelledMail;
use App\Mail\SubscriptionExpiringMail;
use App\Mail\SubscriptionPastDueMail;
use App\Mail\WelcomeMail;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TransactionalMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_new_users_get_a_welcome_email_once(): void
    {
        $this->post(route('login.send'), ['email' => 'nuevo@example.com']);
        $this->post(route('login.verify.store'), ['code' => $this->lastCode()]);

        Mail::assertQueued(WelcomeMail::class, fn (WelcomeMail $mail) => $mail->hasTo('nuevo@example.com'));

        $this->post(route('logout'));
        $this->travel(2)->minutes();
        $this->post(route('login.send'), ['email' => 'nuevo@example.com']);
        $this->post(route('login.verify.store'), ['code' => $this->lastCode()]);

        Mail::assertQueuedCount(1);
    }

    public function test_paid_webhook_sends_a_receipt(): void
    {
        $payment = Payment::factory()->create();

        $this->sendWebhook($payment, GatewayEvent::PAYMENT_SUCCEEDED);

        Mail::assertQueued(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) use ($payment): bool {
            $mail->assertSeeInHtml($payment->reference);
            $mail->assertSeeInHtml($payment->formattedAmount());

            return $mail->hasTo($payment->user->email);
        });
    }

    public function test_duplicate_webhook_does_not_send_a_second_receipt(): void
    {
        $payment = Payment::factory()->create();

        $this->sendWebhook($payment, GatewayEvent::PAYMENT_SUCCEEDED, 'evt_same');
        $this->sendWebhook($payment, GatewayEvent::PAYMENT_SUCCEEDED, 'evt_same');

        Mail::assertQueuedCount(1);
    }

    public function test_failed_webhook_notifies_the_customer(): void
    {
        $payment = Payment::factory()->create();

        $this->sendWebhook($payment, GatewayEvent::PAYMENT_FAILED);

        Mail::assertQueued(PaymentFailedMail::class, fn (PaymentFailedMail $mail) => $mail->hasTo($payment->user->email));
        Mail::assertNotQueued(PaymentReceiptMail::class);
    }

    public function test_cancellation_is_confirmed_by_email(): void
    {
        $subscription = Subscription::factory()->create();

        $this->actingAs($subscription->user)->post(route('account.subscription.cancel'));

        Mail::assertQueued(SubscriptionCancelledMail::class, fn (SubscriptionCancelledMail $mail) => $mail->hasTo($subscription->user->email));
    }

    public function test_expiry_reminders_are_sent_once_per_period(): void
    {
        $soon = Subscription::factory()->create(['ends_at' => now()->addDays(2)]);
        Subscription::factory()->create(['ends_at' => now()->addDays(20)]);
        Subscription::factory()->expired()->create();
        Subscription::factory()->for(User::factory()->inactive())->create(['ends_at' => now()->addDay()]);

        $this->artisan('subscriptions:remind')->expectsOutputToContain('Recordatorios enviados: 1')->assertSuccessful();

        Mail::assertQueued(SubscriptionExpiringMail::class, fn (SubscriptionExpiringMail $mail) => $mail->hasTo($soon->user->email));
        Mail::assertQueuedCount(1);

        $this->artisan('subscriptions:remind')->expectsOutputToContain('Recordatorios enviados: 0');
    }

    public function test_extending_a_subscription_resets_the_reminder(): void
    {
        $subscription = Subscription::factory()->create(['ends_at' => now()->addDays(2)]);
        $this->artisan('subscriptions:remind');

        $subscription->refresh()->update(['ends_at' => now()->addDays(2)->addMonth()]);

        $this->assertNull($subscription->fresh()->expiry_reminder_sent_at);
    }

    public function test_past_due_customers_are_notified(): void
    {
        $renewing = Subscription::factory()->create(['ends_at' => now()->subHour()]);
        Subscription::factory()->create(['status' => SubscriptionStatus::Cancelled, 'ends_at' => now()->subHour()]);

        $this->artisan('subscriptions:expire');

        Mail::assertQueued(SubscriptionPastDueMail::class, fn (SubscriptionPastDueMail $mail) => $mail->hasTo($renewing->user->email));
        Mail::assertQueuedCount(1);
    }

    public function test_mails_render(): void
    {
        $payment = Payment::factory()->paid()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $payment->plan_id]);
        $payment->update(['subscription_id' => $subscription->id]);

        foreach ([
            new WelcomeMail($payment->user),
            new PaymentReceiptMail($payment->fresh(['plan', 'subscription'])),
            new PaymentFailedMail($payment),
            new SubscriptionCancelledMail($subscription),
            new SubscriptionExpiringMail($subscription),
            new SubscriptionPastDueMail($subscription),
        ] as $mail) {
            $this->assertNotEmpty($mail->render());
        }
    }

    private function lastCode(): string
    {
        $code = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function sendWebhook(Payment $payment, string $type, ?string $eventId = null): void
    {
        $webhook = app(PaymentGatewayManager::class)->driver('sandbox')->signedWebhook([
            'id' => $eventId ?? 'evt_'.uniqid(),
            'type' => $type,
            'reference' => $payment->reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'failure_reason' => 'Fondos insuficientes',
        ]);

        $this->call('POST', route('webhooks.payments', 'sandbox'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SANDBOX_TIMESTAMP' => $webhook['headers']['X-Sandbox-Timestamp'],
            'HTTP_X_SANDBOX_SIGNATURE' => $webhook['headers']['X-Sandbox-Signature'],
        ], content: $webhook['body'])->assertOk();
    }
}
