<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanInterval;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Services\Payments\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::withCount(['subscriptions' => fn ($query) => $query->grantingAccess()])->orderBy('sort_order')->orderBy('price')->get(),
            'intervals' => PlanInterval::cases(),
        ]);
    }

    /**
     * Create a monthly or yearly plan (HU-017).
     */
    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::create($this->validated($request));
        $this->auditLogger->log('plan.created', $plan);

        return back()->with('status', 'Plan creado.');
    }

    /**
     * Price changes only affect future payments; existing subscriptions keep what they paid.
     */
    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request));
        $this->auditLogger->log('plan.updated', $plan, ['changes' => array_keys($plan->getChanges())]);

        return back()->with('status', 'Plan actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'interval' => ['required', Rule::enum(PlanInterval::class)],
            'price' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $validated['currency'] = strtoupper($validated['currency']);
        $validated['price'] = Money::toMinor($validated['price'], $validated['currency']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] ??= 0;

        return $validated;
    }
}
