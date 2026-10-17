<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', Rule::exists('books', 'id')->where('status', 'published')->whereNotNull('price')],
            'email' => [
                $this->user() ? 'prohibited' : 'required',
                'nullable',
                'string',
                'email:rfc',
                'max:255',
                Rule::when($this->filled('email_confirmation'), ['confirmed']),
            ],
            'billing_name' => [Rule::when($this->has('billing_name'), ['required', 'string', 'min:3', 'max:255'])],
            'billing_document' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9.\-\s]+$/'],
            'billing_country' => [Rule::when($this->has('billing_country'), ['required', 'string', 'size:2', 'alpha'])],
            'billing_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'book_id' => 'libro',
            'email' => 'correo electrónico',
            'billing_name' => 'nombre completo',
            'billing_document' => 'documento de identidad',
            'billing_country' => 'país',
            'billing_phone' => 'teléfono',
            'terms' => 'términos y condiciones',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms.accepted' => 'Debes aceptar los términos y condiciones para continuar.',
            'email.confirmed' => 'Los dos correos no coinciden.',
        ];
    }

    /**
     * Where the receipt and access go: the account's e-mail, or the one the guest typed.
     */
    public function buyerEmail(): string
    {
        return mb_strtolower(trim($this->user()?->email ?? (string) $this->validated('email')));
    }

    /**
     * @return array{billing_name: ?string, billing_document: ?string, billing_country: string, billing_phone: ?string}
     */
    public function billing(): array
    {
        $email = $this->buyerEmail();
        $fallbackName = $this->user()?->name ?: ($email !== '' ? Str::headline(Str::before($email, '@')) : null);

        return [
            'billing_name' => $this->validated('billing_name') ? trim((string) $this->validated('billing_name')) : $fallbackName,
            'billing_document' => $this->validated('billing_document') ? trim((string) $this->validated('billing_document')) : null,
            'billing_country' => strtoupper($this->validated('billing_country') ?: 'CO'),
            'billing_phone' => $this->validated('billing_phone') ? trim((string) $this->validated('billing_phone')) : null,
        ];
    }
}
