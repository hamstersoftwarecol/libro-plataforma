<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Book;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BoldGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const IDENTITY = 'IDENTIDAD_PRUEBA';

    private const SECRET = 'SECRETA_PRUEBA';

    private User $customer;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'payments.gateway' => 'bold',
            'payments.gateways.bold.identity_key' => self::IDENTITY,
            'payments.gateways.bold.secret_key' => self::SECRET,
            'payments.gateways.bold.environment' => 'production',
        ]);

        $this->customer = User::factory()->create();
        $this->book = Book::factory()->published()->create(['title' => 'El fondo no era el final.', 'price' => 29900, 'currency' => 'COP']);
    }

    public function test_checkout_renders_bold_button_signed_on_the_server(): void
    {
        $location = $this->startCheckout()->headers->get('Location');
        $payment = Payment::sole();

        $this->assertSame('bold', $payment->gateway);
        $this->assertStringContainsString('/pagar/bold/'.$payment->reference, $location);

        $expectedSignature = hash('sha256', $payment->reference.'29900COP'.self::SECRET);

        $html = $this->actingAs($this->customer)->get($location)->assertOk()->assertDontSee(self::SECRET)->getContent();

        preg_match('/const config = (\{.*?\});/s', $html, $matches);
        $config = json_decode($matches[1] ?? '{}', true);

        $this->assertSame(self::IDENTITY, $config['apiKey']);
        $this->assertSame($payment->reference, $config['orderId']);
        $this->assertSame('29900', $config['amount']);
        $this->assertSame('COP', $config['currency']);
        $this->assertSame($expectedSignature, $config['integritySignature']);
        $this->assertSame(route('checkout.bold.return'), $config['redirectionUrl']);
        $this->assertStringContainsString('boldPaymentButton.js', $html);
        $this->assertStringContainsString('checkout.open()', $html);
    }

    public function test_bold_page_requires_signature_and_ownership(): void
    {
        $this->startCheckout();
        $payment = Payment::sole();

        $this->actingAs($this->customer)->get(route('checkout.bold', $payment))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(\URL::temporarySignedRoute('checkout.bold', now()->addHour(), $payment))->assertNotFound();
    }

    public function test_prices_bold_cannot_charge_show_an_error(): void
    {
        $usdWithCents = Book::factory()->published()->create(['price' => 999, 'currency' => 'USD']);

        $this->actingAs($this->customer)->post(route('checkout.store'), $this->form($usdWithCents))->assertSessionHasErrors('book_id');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_return_from_bold_verifies_with_the_api_and_grants_access_immediately(): void
    {
        $this->startCheckout();
        $payment = Payment::sole();

        Http::fake(['payments.api.bold.co/v2/payment-voucher/*' => Http::response([
            'transaction_id' => 'CNPVI70CQC0EY', 'total' => 29900, 'reference_id' => $payment->reference, 'payment_status' => 'APPROVED',
        ])]);

        // The status in the URL is ignored; the server asks Bold.
        $this->actingAs($this->customer)
            ->get(route('checkout.bold.return', ['bold-order-id' => $payment->reference, 'bold-tx-status' => 'approved']))
            ->assertRedirect(route('checkout.show', $payment));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/payment-voucher/'.$payment->reference)
            && $request->hasHeader('Authorization', 'x-api-key '.self::IDENTITY));
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame('CNPVI70CQC0EY', $payment->fresh()->gateway_payment_id);
        $this->assertTrue($this->customer->fresh()->ownsBook($this->book));
    }

    public function test_tampered_return_status_is_not_trusted(): void
    {
        $this->startCheckout();
        $payment = Payment::sole();

        Http::fake(['payments.api.bold.co/*' => Http::response(['payment_status' => 'REJECTED', 'total' => 29900, 'transaction_id' => 'X1'])]);

        $this->actingAs($this->customer)->get(route('checkout.bold.return', ['bold-order-id' => $payment->reference, 'bold-tx-status' => 'approved']));

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertFalse($this->customer->fresh()->ownsBook($this->book));
    }

    public function test_pending_or_unknown_status_keeps_waiting(): void
    {
        $this->startCheckout();
        $payment = Payment::sole();

        Http::fake(['payments.api.bold.co/*' => Http::response(['payment_status' => 'NO_TRANSACTION_FOUND', 'total' => 29900])]);

        $this->actingAs($this->customer)->get(route('checkout.bold.return', ['bold-order-id' => $payment->reference]));
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->actingAs($this->customer)->get(route('checkout.show', $payment))->assertSee('Estamos confirmando tu pago');
    }

    public function test_amount_mismatch_from_api_is_ignored(): void
    {
        $this->startCheckout();
        $payment = Payment::sole();

        Http::fake(['payments.api.bold.co/*' => Http::response(['payment_status' => 'APPROVED', 'total' => 1000, 'transaction_id' => 'X2'])]);

        $this->actingAs($this->customer)->get(route('checkout.bold.return', ['bold-order-id' => $payment->reference]));
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_signed_webhook_approves_the_payment(): void
    {
        $this->startCheckout();
        $payment = Payment::sole();

        $this->sendWebhook($this->event($payment))->assertOk()->assertJson(['status' => 'processed']);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->user->ownsBook($this->book));
        $this->assertSame(0, Subscription::count());

        $this->sendWebhook($this->event($payment))->assertJson(['status' => 'duplicate']);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $this->startCheckout();
        $payment = Payment::sole();

        $this->sendWebhook($this->event($payment), signature: str_repeat('a', 64))->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_test_environment_webhooks_are_signed_with_an_empty_key(): void
    {
        config(['payments.gateways.bold.environment' => 'test']);
        $this->startCheckout();
        $payment = Payment::sole();
        $body = json_encode($this->event($payment));

        $this->sendWebhook($this->event($payment), signature: hash_hmac('sha256', base64_encode($body), ''))->assertOk();
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_rejected_sale_webhook_marks_the_payment_failed(): void
    {
        $this->startCheckout();
        $payment = Payment::sole();

        $this->sendWebhook($this->event($payment, 'SALE_REJECTED'))->assertOk();
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    private function startCheckout(): TestResponse
    {
        return $this->actingAs($this->customer)->post(route('checkout.store'), $this->form($this->book));
    }

    /**
     * @return array<string, mixed>
     */
    private function form(Book $book): array
    {
        return ['book_id' => $book->id, 'billing_name' => 'Ana Pérez', 'billing_country' => 'CO', 'billing_phone' => '3001234567', 'terms' => '1'];
    }

    /**
     * @return array<string, mixed>
     */
    private function event(Payment $payment, string $type = 'SALE_APPROVED'): array
    {
        return [
            'id' => 'e4f8c1b9-'.$payment->id.'-'.$type,
            'type' => $type,
            'subject' => 'F8A5D6B7G2H1',
            'source' => '/payments',
            'spec_version' => '1.0',
            'time' => 1761060600000000000,
            'data' => [
                'payment_id' => 'F8A5D6B7G2H1',
                'merchant_id' => 'PQR6Y4T8Z3',
                'amount' => ['currency' => 'COP', 'total' => 29900, 'taxes' => [], 'tip' => 0],
                'metadata' => ['reference' => $payment->reference],
                'payment_method' => 'CARD',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function sendWebhook(array $event, ?string $signature = null): TestResponse
    {
        $body = json_encode($event);
        $signature ??= hash_hmac('sha256', base64_encode($body), self::SECRET);

        return $this->call('POST', route('webhooks.payments', 'bold'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BOLD_SIGNATURE' => $signature,
        ], content: $body);
    }
}
