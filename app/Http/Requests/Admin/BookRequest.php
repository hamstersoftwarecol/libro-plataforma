<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class BookRequest extends FormRequest
{
    /**
     * Maximum book file size in kilobytes (100 MB).
     */
    public const MAX_FILE_KB = 102400;

    public function authorize(): bool
    {
        return $this->user()->can('manage-books');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'language' => ['required', 'string', 'size:2'],
            'published_year' => ['nullable', 'integer', 'min:1000', 'max:'.(now()->year + 1)],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'authors' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'file' => ['nullable', 'file', 'extensions:pdf,epub', 'mimetypes:application/pdf,application/epub+zip,application/zip', 'max:'.self::MAX_FILE_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'category_id' => 'categoría',
            'published_year' => 'año de publicación',
            'page_count' => 'número de páginas',
            'cover' => 'portada',
            'price' => 'precio',
            'currency' => 'moneda',
            'file' => 'archivo del libro',
        ];
    }

    /**
     * Comma separated names, trimmed and de-duplicated.
     *
     * @return list<string>
     */
    public function names(string $field): array
    {
        return collect(explode(',', (string) $this->input($field)))
            ->map(fn (string $name): string => Str::squish($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values()
            ->all();
    }
}
