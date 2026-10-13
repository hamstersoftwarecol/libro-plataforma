<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * All payments, for administrators only.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return view('admin.payments.index', [
            'payments' => Payment::query()
                ->with(['user', 'plan', 'book'])
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where(fn ($query) => $query
                    ->where('reference', $term)
                    ->orWhere('email', 'like', '%'.$term.'%')))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => PaymentStatus::cases(),
        ]);
    }
}
