<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\ReadingSession;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Library\BookAccess;
use App\Services\Payments\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Customers with their subscription state (HU-023).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:buyers,without,inactive'],
        ]);

        $customers = User::query()
            ->where('role', Role::Reader)
            ->withCount(['payments as paid_payments_count' => fn (Builder $query) => $query->where('status', 'paid'), 'books'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('email', 'like', '%'.$term.'%')
                ->orWhere('name', 'like', '%'.$term.'%')))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match ($status) {
                'buyers' => $query->has('books'),
                'without' => $query->doesntHave('books'),
                'inactive' => $query->where('is_active', false),
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers.index', ['customers' => $customers, 'filters' => $filters]);
    }

    public function show(User $customer): View
    {
        abort_unless($customer->role === Role::Reader, 404);

        return view('admin.customers.show', [
            'customer' => $customer,
            'books' => $customer->books()->orderByPivot('granted_at', 'desc')->get(),
            'availableBooks' => Book::published()->whereNotIn('id', $customer->books()->pluck('books.id'))->orderBy('title')->get(['id', 'title']),
            'payments' => $customer->payments()->with('plan', 'book')->latest()->limit(50)->get(),
            'reading' => $customer->readingProgress()->with('book')->latest('updated_at')->limit(20)->get(),
        ]);
    }

    /**
     * Block or unblock access. Blocking also ends web and reading sessions.
     */
    public function toggleActive(User $customer): RedirectResponse
    {
        abort_unless($customer->role === Role::Reader, 404);

        $customer->update(['is_active' => ! $customer->is_active]);

        if (! $customer->is_active) {
            DB::table('sessions')->where('user_id', $customer->id)->delete();
            ReadingSession::where('user_id', $customer->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        }

        $this->auditLogger->log($customer->is_active ? 'customer.activated' : 'customer.deactivated', $customer);

        return back()->with('status', $customer->is_active ? 'Cliente reactivado.' : 'Cliente bloqueado y desconectado.');
    }

    /**
     * Give lifetime access to a book without payment (gift, compensation, review copy).
     */
    public function grantBook(Request $request, User $customer, BookAccess $bookAccess): RedirectResponse
    {
        abort_unless($customer->role === Role::Reader, 404);

        $book = Book::published()->findOrFail($request->validate(['book_id' => ['required', 'integer']])['book_id']);
        $bookAccess->grant($customer, $book, grantedBy: $request->user());

        return back()->with('status', 'Acceso de por vida concedido a «'.rtrim($book->title, '.').'».');
    }

    /**
     * Remove access to a book, e.g. after a refund.
     */
    public function revokeBook(Request $request, User $customer, Book $book, BookAccess $bookAccess): RedirectResponse
    {
        abort_unless($customer->role === Role::Reader, 404);

        $bookAccess->revoke($customer, $book, $request->user());

        return back()->with('status', 'Acceso a «'.rtrim($book->title, '.').'» retirado.');
    }

    /**
     * Grant courtesy days, extending the current subscription or creating a new one.
     */
    public function extend(Request $request, User $customer): RedirectResponse
    {
        abort_unless($customer->role === Role::Reader, 404);

        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $subscription = $customer->currentSubscription();

        if ($subscription) {
            $subscription->update(['ends_at' => $subscription->ends_at->addDays($validated['days'])]);
        } else {
            $subscription = Subscription::create([
                'user_id' => $customer->id,
                'status' => SubscriptionStatus::Active,
                'auto_renew' => false,
                'starts_at' => now(),
                'ends_at' => now()->addDays($validated['days']),
            ]);
        }

        $this->auditLogger->log('subscription.courtesy_extended', $subscription, $validated);

        return back()->with('status', "Acceso extendido {$validated['days']} días.");
    }

    public function cancelSubscription(User $customer, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = $customer->currentSubscription();

        abort_unless($subscription && $subscription->status === SubscriptionStatus::Active, 404);

        $subscriptions->cancel($subscription);

        return back()->with('status', 'Renovación cancelada. El cliente conserva el acceso hasta el fin del periodo.');
    }

    /**
     * End access immediately, e.g. after a refund or fraud.
     */
    public function revokeSubscription(User $customer): RedirectResponse
    {
        $subscription = $customer->currentSubscription();

        abort_unless($subscription, 404);

        $subscription->update(['status' => SubscriptionStatus::Expired, 'auto_renew' => false, 'ends_at' => now()]);
        ReadingSession::where('user_id', $customer->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->auditLogger->log('subscription.revoked', $subscription);

        return back()->with('status', 'Acceso revocado de inmediato.');
    }
}
