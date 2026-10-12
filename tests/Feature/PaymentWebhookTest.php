<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SandboxGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_webhook_marks_payment_paid_and_activates_access(): void
    {
        $payment = Payment::factory()->create();

        $this->sendWebhook($this->payload($payment))->assertOk()->assertJson(['status' => 'processed']);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->subscription_id);
        $this->assertTrue($payment->user->hasActiveSubscription());
    }

    public function test_duplicate_event_is_processed_only_once(): void
    {
        $payment = Payment::factory()->create();
        $payload = $this->payload($payment);

        $this->sendWebhook($payload)->assertJson(['status' => 'processed']);
        $endsAt = Subscription::sole()->ends_at;

        $this->sendWebhook($payload)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(1, Subscription::count());
        $this->assertTrue($endsAt->eq(Subscription::sole()->ends_at));
    }

    public function test_second_success_event_for_a_paid_payment_does_not_extend_access(): void
    {
        $payment = Payment::factory()->create();

        $this->sendWebhook($this->payload($payment, ['id' => 'evt_1']));
        $endsAt = Subscription::sole()->ends_at;

        $this->sendWebhook($this->payload($payment, ['id' => 'evt_2']))->assertJson(['status' => 'ignored']);

        $this->assertTrue($endsAt->eq(Subscription::sole()->ends_at));
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payment = Payment::factory()->create();
        $webhook = $this->gateway()->signedWebhook($this->payload($payment));
        $webhook['headers'][SandboxGateway::SIGNATURE_HEADER] = str_repeat('0', 64);

        $this->call('POST', route('webhooks.payments', 'sandbox'), server: $this->server($webhook['headers']), content: $webhook['body'])
            ->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_tampered_body_is_rejected(): void
    {
        $payment = Payment::factory()->create();
        $webhook = $this->gateway()->signedWebhook($this->payload($payment, ['amount' => 1]));
        $webhook['body'] = str_replace('"amount":1', '"amount":'.$payment->amount, $webhook['body']);

        $this->call('POST', route('webhooks.payments', 'sandbox'), server: $this->server($webhook['headers']), content: $webhook['body'])
            ->assertStatus(400);
    }

    public function test_old_webhooks_are_rejected(): void
    {
        $payment = Payment::factory()->create();
        $webhook = $this->gateway()->signedWebhook($this->payload($payment), now()->subHour()->timestamp);

        $this->call('POST', route('webhooks.payments', 'sandbox'), server: $this->server($webhook['headers']), content: $webhook['body'])
            ->assertStatus(400);
    }

    public function test_amount_mismatch_does_not_activate_access(): void
    {
        $payment = Payment::factory()->create(['amount' => 999]);

        $this->sendWebhook($this->payload($payment, ['amount' => 1]))->assertJson(['status' => 'ignored']);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.amount_mismatch']);
    }

    public function test_failed_payment_is_recorded(): void
    {
        $payment = Payment::factory()->create();

        $this->sendWebhook($this->payload($payment, ['type' => GatewayEvent::PAYMENT_FAILED, 'failure_reason' => 'Fondos insuficientes']));

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame('Fondos insuficientes', $payment->fresh()->failure_reason);
        $this->assertFalse($payment->user->hasActiveSubscription());
    }

    public function test_unknown_gateway_returns_not_found(): void
    {
        $this->postJson(route('webhooks.payments', 'desconocida'), [])->assertNotFound();
    }

    public function test_unknown_reference_is_acknowledged_and_ignored(): void
    {
        $this->sendWebhook([
            'id' => 'evt_x', 'type' => GatewayEvent::PAYMENT_SUCCEEDED, 'reference' => 'no-existe',
            'amount' => 100, 'currency' => 'USD',
        ])->assertOk()->assertJson(['status' => 'ignored']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Payment $payment, array $overrides = []): array
    {
        return array_merge([
            'id' => 'evt_'.$payment->id,
            'type' => GatewayEvent::PAYMENT_SUCCEEDED,
            'reference' => $payment->reference,
            'payment_id' => 'sbx_123',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWebhook(array $payload): TestResponse
    {
        $webhook = $this->gateway()->signedWebhook($payload);

        return $this->call('POST', route('webhooks.payments', 'sandbox'), server: $this->server($webhook['headers']), content: $webhook['body']);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function server(array $headers): array
    {
        return collect($headers)->mapWithKeys(fn (string $value, string $name) => [
            $name === 'Content-Type' ? 'CONTENT_TYPE' : 'HTTP_'.strtoupper(str_replace('-', '_', $name)) => $value,
        ])->all();
    }

    private function gateway(): SandboxGateway
    {
        return app(PaymentGatewayManager::class)->driver('sandbox');
    }
}
