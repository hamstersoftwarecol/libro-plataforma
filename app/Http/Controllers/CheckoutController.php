<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Requests\CheckoutRequest;
use App\Models\Book;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentStatusSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

class CheckoutController extends Controller
{
    /**
     * Payment form: plan selection and billing details (HU-018).
     */
    public function create(Request $request): View
    {
        $plans = Plan::available()->get();
        $user = $request->user();
        $lastPayment = $user?->payments()->whereNotNull('billing_name')->latest()->first();

        return view('checkout.form', [
            'plans' => $plans,
            'selectedPlanId' => (int) ($request->old('plan_id') ?? $plans->firstWhere('slug', $request->query('plan'))?->id ?? $plans->first()?->id),
            'book' => Book::featured()?->load('authors'),
            'currentSubscription' => $user?->currentSubscription(),
            'billing' => [
                'billing_name' => $lastPayment?->billing_name ?? $user?->name,
                'billing_document' => $lastPayment?->billing_document,
                'billing_country' => $lastPayment?->billing_country ?? 'CO',
                'billing_phone' => $lastPayment?->billing_phone,
            ],
        ]);
    }

    /**
     * Create the pending payment and send the customer to the gateway.
     */
    public function store(CheckoutRequest $request, CheckoutService $checkout): RedirectResponse
    {
        $plan = Plan::findOrFail($request->validated('plan_id'));

        try {
            $session = $checkout->start($request->user(), $request->buyerEmail(), $plan, $request->billing());
            $session->payment->rememberInSession($request);

            return redirect()->away($session->url);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['plan_id' => $exception->getMessage()]);
        }
    }

    /**
     * Page the gateway returns to. Only the server-side webhook confirms the payment;
     * once it has, the customer goes straight into the book.
     */
    public function show(Request $request, Payment $payment, PaymentStatusSync $sync, AuditLogger $auditLogger): View|RedirectResponse
    {
        abort_unless($payment->belongsToVisitor($request), 404);

        $sync->sync($payment);

        if ($payment->status === PaymentStatus::Paid && ! $request->user() && $this->mayAutoLogin($request, $payment)) {
            Auth::login($payment->user, remember: true);
            $request->session()->regenerate();
            $payment->rememberInSession($request);
            $auditLogger->log('auth.login_after_payment', $payment, user: $payment->user);
        }

        if ($payment->status === PaymentStatus::Paid && $request->user() && ! $request->boolean('detalle')) {
            $book = Book::featured();

            if ($book && Gate::allows('read', $book)) {
                return redirect()->route('reader.show', $book)->with('status', '¡Pago confirmado! Bienvenido a tu libro.');
            }
        }

        return view('checkout.result', ['payment' => $payment->load('plan', 'subscription')]);
    }

    /**
     * Only the browser that paid, only for an account created by this very payment, and only shortly after.
     * Existing accounts (including staff) must sign in with the e-mailed code instead.
     */
    private function mayAutoLogin(Request $request, Payment $payment): bool
    {
        return $payment->created_account
            && $payment->user?->is_active
            && ! $payment->user->can('access-admin')
            && $payment->paid_at?->gt(now()->subDay())
            && in_array($payment->reference, (array) $request->session()->get(Payment::SESSION_KEY, []), true);
    }
}
