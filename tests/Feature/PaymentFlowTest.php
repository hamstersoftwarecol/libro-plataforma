<?php

namespace Tests\Feature;

use App\Enums\BookStatus;
use App\Enums\PaymentStatus;
use App\Mail\LoginCodeMail;
use App\Models\Book;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BookFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Support\BookFixtures;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private Book $book;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('books');
        Mail::fake();
        config(['app.featured_book' => 'el-fondo-no-era-el-final']);

        $this->book = Book::factory()->create(['title' => 'El fondo no era el final.', 'status' => BookStatus::Published, 'published_at' => now()]);
        $this->book->forceFill(['slug' => 'el-fondo-no-era-el-final'])->save();
        app(BookFileStorage::class)->storeBookFile($this->book, BookFixtures::pdfUpload());
        $this->book->refresh();

        $this->plan = Plan::factory()->create(['name' => 'Mensual', 'price' => 999]);
    }

    public function test_customer_without_payment_is_sent_to_the_payment_form_after_login(): void
    {
        $this->post(route('login.send'), ['email' => 'cliente@example.com']);
        $this->post(route('login.verify.store'), ['code' => $this->lastCode()])->assertRedirect(route('book.start'));

        $this->get(route('book.start'))->assertRedirect(route('checkout.create'));
        $this->get(route('checkout.create'))->assertOk()->assertSee('Completa tu pago')->assertSee('El fondo no era el final.')->assertSee('Mensual');
    }

    public function test_paying_customer_goes_straight_into_the_book(): void
    {
        $customer = User::factory()->create();
        Subscription::factory()->for($customer)->create();

        $this->actingAs($customer)->get(route('book.start'))->assertRedirect(route('reader.show', $this->book));
    }

    public function test_admin_goes_to_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'jefe@example.com']);

        $this->post(route('login.send'), ['email' => 'jefe@example.com']);
        $this->post(route('login.verify.store'), ['code' => $this->lastCode()])->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('book.start'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_unpaid_customer_cannot_open_the_book(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('reader.show', $this->book))->assertRedirect(route('checkout.create'));
        $this->actingAs($customer)->get(route('reader.page', [$this->book, 1]))->assertForbidden();
    }

    public function test_payment_form_validates_billing_details_and_terms(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('checkout.store'), ['plan_id' => $this->plan->id, 'billing_name' => '', 'billing_country' => 'XYZ', 'billing_phone' => 'abc'])
            ->assertSessionHasErrors(['billing_name', 'billing_country', 'billing_phone', 'terms']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_full_flow_form_then_payment_then_immediate_access(): void
    {
        $customer = User::factory()->create(['name' => null]);

        $location = $this->actingAs($customer)->post(route('checkout.store'), [
            'plan_id' => $this->plan->id,
            'billing_name' => 'Ana Pérez',
            'billing_document' => 'CC 1020304050',
            'billing_country' => 'co',
            'billing_phone' => '+57 300 000 0000',
            'terms' => '1',
        ])->headers->get('Location');

        $payment = Payment::sole();
        $this->assertSame('Ana Pérez', $payment->billing_name);
        $this->assertSame('CO', $payment->billing_country);
        $this->assertNotNull($payment->terms_accepted_at);
        $this->assertSame('Ana Pérez', $customer->fresh()->name);
        $this->assertStringContainsString('/sandbox/pago/', $location);

        // Before the gateway confirms: still no access.
        $this->actingAs($customer)->get(route('reader.show', $this->book))->assertRedirect(route('checkout.create'));
        $this->actingAs($customer)->get(route('checkout.show', $payment))->assertOk()->assertSee('Estamos confirmando tu pago');

        // The gateway approves and notifies the server.
        $this->actingAs($customer)->post(URL::temporarySignedRoute('sandbox.complete', now()->addMinutes(5), $payment), ['outcome' => 'approve']);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->actingAs($customer)->get(route('checkout.show', $payment))->assertRedirect(route('reader.show', $this->book));
        $this->actingAs($customer)->get(route('reader.show', $this->book))->assertOk();
    }

    public function test_declined_payment_keeps_the_book_locked(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('checkout.store'), [
            'plan_id' => $this->plan->id, 'billing_name' => 'Ana Pérez', 'billing_country' => 'CO', 'terms' => '1',
        ]);
        $payment = Payment::sole();

        $this->actingAs($customer)->post(URL::temporarySignedRoute('sandbox.complete', now()->addMinutes(5), $payment), ['outcome' => 'decline']);

        $this->actingAs($customer)->get(route('checkout.show', $payment))->assertOk()->assertSee('No pudimos procesar el pago');
        $this->actingAs($customer)->get(route('reader.show', $this->book))->assertRedirect(route('checkout.create'));
    }

    public function test_form_is_prefilled_with_previous_billing_details(): void
    {
        $customer = User::factory()->create();
        Payment::factory()->create(['user_id' => $customer->id, 'billing_name' => 'Ana Pérez', 'billing_country' => 'MX', 'billing_document' => 'XYZ123']);

        $this->actingAs($customer)->get(route('checkout.create'))->assertSee('Ana Pérez')->assertSee('XYZ123');
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
}
