<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Services\Payments\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Subscription status and payment history (HU-020).
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'subscription' => $user->currentSubscription() ?? $user->subscriptions()->with('plan')->latest('ends_at')->first(),
            'payments' => $user->payments()->with('plan')->latest()->paginate(15),
        ]);
    }

    public function cancel(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = $request->user()->currentSubscription();

        abort_unless($subscription && $subscription->status === SubscriptionStatus::Active, 404);

        $subscriptions->cancel($subscription);

        return back()->with('status', 'Cancelaste la renovación. Seguirás teniendo acceso hasta el '.$subscription->ends_at->translatedFormat('j \d\e F \d\e Y').'.');
    }

    public function resume(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = $request->user()->currentSubscription();

        abort_unless($subscription && $subscription->status === SubscriptionStatus::Cancelled, 404);

        $subscriptions->resume($subscription);

        return back()->with('status', 'Tu suscripción se renovará automáticamente.');
    }
}
