<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Book;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.featured_book' => 'libro-a-la-venta']);
        $this->book = Book::factory()->published()->create(['title' => 'Libro a la venta', 'price' => 50000, 'currency' => 'COP']);
    }

    public function test_payment_form_shows_the_book_and_its_single_price(): void
    {
        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('Libro a la venta')
            ->assertSee('Acceso de por vida')
            ->assertSee('50.000')
            ->assertSee('pago único')
            ->assertDontSee('Elige tu plan');
    }

    public function test_a_specific_book_can_be_bought_with_the_libro_parameter(): void
    {
        $other = Book::factory()->published()->create(['title' => 'Otro libro', 'price' => 30000]);

        $this->get(route('checkout.create', ['libro' => $other->slug]))->assertSee('Otro libro')->assertSee('30.000');
    }

    public function test_old_plans_page_redirects_to_the_payment_form(): void
    {
        $this->get('/planes')->assertRedirect(route('checkout.create'));
    }

    public function test_checkout_creates_a_pending_payment_for_the_book_price(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('checkout.store'), $this->form());

        $payment = Payment::sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame($this->book->id, $payment->book_id);
        $this->assertNull($payment->plan_id);
        $this->assertSame(50000, $payment->amount);
        $this->assertSame('COP', $payment->currency);
        $this->assertStringContainsString('/sandbox/pago/'.$payment->reference, $response->headers->get('Location'));
    }

    public function test_books_without_a_price_cannot_be_bought(): void
    {
        $free = Book::factory()->published()->create(['price' => null]);

        $this->post(route('checkout.store'), $this->form(['book_id' => $free->id, 'email' => 'a@example.com', 'email_confirmation' => 'a@example.com']))
            ->assertSessionHasErrors('book_id');
        $this->assertDatabaseCount('payments', 0);

        $this->get(route('checkout.create', ['libro' => $free->slug]))->assertSee('todavía no está a la venta');
    }

    public function test_owners_are_sent_to_read_instead_of_paying_again(): void
    {
        $user = User::factory()->create();
        $user->books()->attach($this->book->id, ['granted_at' => now()]);

        $this->actingAs($user)->post(route('checkout.store'), $this->form())->assertRedirect(route('reader.show', $this->book));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_guests_must_give_and_confirm_an_email(): void
    {
        $this->post(route('checkout.store'), $this->form())->assertSessionHasErrors('email');
        $this->post(route('checkout.store'), $this->form(['email' => 'ana@example.com', 'email_confirmation' => 'otro@example.com']))->assertSessionHasErrors('email');

        $this->post(route('checkout.store'), $this->form(['email' => 'Ana@Example.com', 'email_confirmation' => 'Ana@Example.com']))->assertRedirect();

        $payment = Payment::sole();
        $this->assertNull($payment->user_id);
        $this->assertSame('ana@example.com', $payment->email);
    }

    public function test_guests_can_checkout_with_only_email_and_terms(): void
    {
        $this->post(route('checkout.store'), [
            'book_id' => $this->book->id,
            'email' => 'simple@example.com',
            'terms' => '1',
        ])->assertRedirect();

        $payment = Payment::sole();
        $this->assertSame('simple@example.com', $payment->email);
        $this->assertSame('Simple', $payment->billing_name);
        $this->assertSame('CO', $payment->billing_country);
    }

    public function test_guests_with_email_of_existing_owner_cannot_buy_again(): void
    {
        $user = User::factory()->create(['email' => 'dueno@example.com']);
        $user->books()->attach($this->book->id, ['granted_at' => now()]);

        $this->post(route('checkout.store'), [
            'book_id' => $this->book->id,
            'email' => 'dueno@example.com',
            'terms' => '1',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_signed_in_customers_cannot_override_their_email(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('checkout.store'), $this->form(['email' => 'otro@example.com', 'email_confirmation' => 'otro@example.com']))
            ->assertSessionHasErrors('email');
    }

    public function test_unconfigured_gateway_shows_a_friendly_error(): void
    {
        config(['payments.gateway' => 'sandbox', 'payments.gateways.sandbox.enabled' => false]);

        $this->actingAs(User::factory()->create())
            ->post(route('checkout.store'), $this->form())
            ->assertSessionHasErrors(['book_id' => 'Los pagos no están disponibles en este momento. Inténtalo más tarde.']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_sandbox_approval_grants_lifetime_access_to_the_book(): void
    {
        $user = User::factory()->create();
        $location = $this->actingAs($user)->post(route('checkout.store'), $this->form())->headers->get('Location');
        $payment = Payment::sole();

        $this->actingAs($user)->get($location)->assertOk()->assertSee('Simular pago aprobado');
        $this->actingAs($user)->post($this->signedCompleteUrl($payment), ['outcome' => 'approve']);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertTrue($user->ownsBook($this->book));
        $this->assertSame($payment->id, $user->books()->first()->pivot->payment_id);
        $this->assertSame(0, $user->subscriptions()->count());

        $this->travel(20)->years();
        $this->assertTrue($user->fresh()->ownsBook($this->book));
    }

    public function test_sandbox_decline_does_not_grant_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('checkout.store'), $this->form());
        $payment = Payment::sole();

        $this->actingAs($user)->post($this->signedCompleteUrl($payment), ['outcome' => 'decline']);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertFalse($user->ownsBook($this->book));
        $this->actingAs($user)->get(route('checkout.show', $payment))->assertSee('No pudimos procesar el pago');
    }

    public function test_sandbox_requires_a_valid_signature(): void
    {
        $payment = Payment::factory()->create(['book_id' => $this->book->id, 'plan_id' => null]);

        $this->actingAs($payment->user)->get(route('sandbox.checkout', $payment))->assertForbidden();
        $this->actingAs($payment->user)->post(route('sandbox.complete', $payment), ['outcome' => 'approve'])->assertForbidden();
    }

    public function test_users_cannot_see_other_users_payments(): void
    {
        $payment = Payment::factory()->create(['book_id' => $this->book->id, 'plan_id' => null]);

        $this->actingAs(User::factory()->create())->get(route('checkout.show', $payment))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'book_id' => $this->book->id,
            'billing_name' => 'Ana Pérez',
            'billing_document' => '1020304050',
            'billing_country' => 'CO',
            'billing_phone' => '+57 300 000 0000',
            'terms' => '1',
        ], $overrides);
    }

    private function signedCompleteUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('sandbox.complete', now()->addMinutes(5), $payment);
    }
}
