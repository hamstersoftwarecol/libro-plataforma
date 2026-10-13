<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Services\Payments\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

#[Fillable(['user_id', 'email', 'created_account', 'plan_id', 'book_id', 'subscription_id', 'reference', 'gateway', 'gateway_payment_id', 'amount', 'currency', 'billing_name', 'billing_document', 'billing_country', 'billing_phone', 'terms_accepted_at', 'status', 'failure_reason', 'paid_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'created_account' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * What was bought, for receipts and listings.
     */
    public function description(): string
    {
        if ($this->book_id) {
            return '«'.rtrim((string) $this->book?->title, '.').'» · acceso de por vida';
        }

        return 'Plan '.($this->plan?->name ?? '');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public const SESSION_KEY = 'checkout.references';

    /**
     * Whether the current visitor started this payment: its account, or the browser session that created it.
     */
    public function belongsToVisitor(Request $request): bool
    {
        $startedInThisBrowser = in_array($this->reference, (array) $request->session()->get(self::SESSION_KEY, []), true);
        $signedInAs = $request->user()?->id;

        // A different signed-in account never sees someone else's payment, even in the same browser.
        if ($signedInAs !== null && $this->user_id !== null) {
            return $signedInAs === $this->user_id;
        }

        return $startedInThisBrowser || ($signedInAs !== null && $signedInAs === $this->user_id);
    }

    public function rememberInSession(Request $request): void
    {
        $references = array_slice([...(array) $request->session()->get(self::SESSION_KEY, []), $this->reference], -10);

        $request->session()->put(self::SESSION_KEY, $references);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function formattedAmount(): string
    {
        return Money::format($this->amount, $this->currency);
    }
}
