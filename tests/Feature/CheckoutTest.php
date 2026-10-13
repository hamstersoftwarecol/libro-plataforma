<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_page_lists_only_active_plans(): void
    {
        Plan::factory()->create(['name' => 'Plan Mensual']);
        Plan::factory()->yearly()->create(['name' => 'Plan Anual']);
        Plan::factory()->inactive()->create(['name' => 'Plan Viejo']);

        $this->get(route('plans.index'))
            ->assertOk()
            ->assertSee('Plan Mensual')
            ->assertSee('Plan Anual')
            ->assertDontSee('Plan Viejo')
            ->assertSee('9,99');
    }

    public function test_checkout_creates_a_pending_payment_and_redirects_to_gateway(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 1500]);

        $response = $this->actingAs($user)->post(route('checkout.store'), $this->form($plan));

        $payment = Payment::sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(1500, $payment->amount);
        $this->assertSame('sandbox', $payment->gateway);
        $this->assertStringContainsString('/sandbox/pago/'.$payment->reference, $response->headers->get('Location'));
        $this->assertStringContainsString('signature=', $response->headers->get('Location'));
    }

    public function test_inactive_plans_cannot_be_purchased(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('checkout.store'), $this->form(Plan::factory()->inactive()->create()))
            ->assertSessionHasErrors('plan_id');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_guests_can_open_the_payment_form_and_must_give_an_email(): void
    {
        $plan = Plan::factory()->create();

        $this->get(route('checkout.create'))->assertOk()->assertSee('Confirma tu correo');
        $this->post(route('checkout.store'), $this->form($plan))->assertSessionHasErrors('email');
        $this->post(route('checkout.store'), $this->form($plan, ['email' => 'ana@example.com', 'email_confirmation' => 'otro@example.com']))->assertSessionHasErrors('email');

        $this->post(route('checkout.store'), $this->form($plan, ['email' => 'Ana@Example.com', 'email_confirmation' => 'Ana@Example.com']))->assertRedirect();

        $payment = Payment::sole();
        $this->assertNull($payment->user_id);
        $this->assertSame('ana@example.com', $payment->email);
        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
    }

    public function test_signed_in_customers_cannot_override_their_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('checkout.store'), $this->form(Plan::factory()->create(), ['email' => 'otro@example.com', 'email_confirmation' => 'otro@example.com']))
            ->assertSessionHasErrors('email');
    }

    public function test_sandbox_approval_activates_the_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $location = $this->actingAs($user)->post(route('checkout.store'), $this->form($plan))->headers->get('Location');
        $payment = Payment::sole();

        $this->actingAs($user)->get($location)->assertOk()->assertSee('Simular pago aprobado');

        $this->actingAs($user)
            ->post($this->signedCompleteUrl($payment), ['outcome' => 'approve'])
            ->assertRedirect(route('checkout.show', $payment));

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertTrue($user->fresh()->hasActiveSubscription());
        $this->actingAs($user)->get(route('checkout.show', [$payment, 'detalle' => 1]))->assertSee('¡Pago confirmado!');
    }

    public function test_sandbox_decline_does_not_grant_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('checkout.store'), $this->form(Plan::factory()->create()));
        $payment = Payment::sole();

        $this->actingAs($user)->post($this->signedCompleteUrl($payment), ['outcome' => 'decline']);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertFalse($user->fresh()->hasActiveSubscription());
        $this->actingAs($user)->get(route('checkout.show', $payment))->assertSee('No pudimos procesar el pago');
    }

    public function test_sandbox_requires_a_valid_signature(): void
    {
        $payment = Payment::factory()->create();

        $this->actingAs($payment->user)->get(route('sandbox.checkout', $payment))->assertForbidden();
        $this->actingAs($payment->user)->post(route('sandbox.complete', $payment), ['outcome' => 'approve'])->assertForbidden();
    }

    public function test_sandbox_is_disabled_when_configured_off(): void
    {
        config(['payments.gateways.sandbox.enabled' => false]);
        $payment = Payment::factory()->create();

        $this->actingAs($payment->user)->get($this->signedCheckoutUrl($payment))->assertNotFound();
    }

    public function test_users_cannot_see_other_users_payments(): void
    {
        $payment = Payment::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('checkout.show', $payment))->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function form(Plan $plan, array $overrides = []): array
    {
        return array_merge([
            'plan_id' => $plan->id,
            'billing_name' => 'Ana Pérez',
            'billing_document' => '1020304050',
            'billing_country' => 'CO',
            'billing_phone' => '+57 300 000 0000',
            'terms' => '1',
        ], $overrides);
    }

    private function signedCompleteUrl(Payment $payment): string
    {
        return \URL::temporarySignedRoute('sandbox.complete', now()->addMinutes(5), $payment);
    }

    private function signedCheckoutUrl(Payment $payment): string
    {
        return \URL::temporarySignedRoute('sandbox.checkout', now()->addMinutes(5), $payment);
    }
}
