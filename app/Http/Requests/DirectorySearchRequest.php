<?php

namespace App\Http\Requests;

use App\Services\Directory\DirectoryCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query of the public business directory (/negocios) and its live search endpoint. */
class DirectorySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The page itself never errors on a shared or hand-edited link: unknown filters are
     * dropped and long queries trimmed. The JSON endpoint keeps strict 422s.
     */
    protected function prepareForValidation(): void
    {
        if ($this->expectsJson()) {
            return;
        }

        $this->merge([
            'q' => is_string($this->input('q')) ? mb_substr($this->input('q'), 0, 80) : null,
            'categoria' => array_key_exists((string) $this->input('categoria'), config('tribio.business_categories')) ? $this->input('categoria') : null,
            'orden' => in_array($this->input('orden'), DirectoryCatalog::SORTS, true) ? $this->input('orden') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'],
            'categoria' => ['nullable', 'string', Rule::in(array_keys(config('tribio.business_categories')))],
            'orden' => ['nullable', 'string', Rule::in(DirectoryCatalog::SORTS)],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda puede tener hasta 80 caracteres.',
            'categoria.in' => 'Esa categoría no existe.',
            'orden.in' => 'Ese orden no está disponible.',
        ];
    }

    public function searchQuery(): string
    {
        return trim((string) $this->validated('q', ''));
    }

    public function category(): ?string
    {
        return $this->validated('categoria') ?: null;
    }

    public function sort(): string
    {
        return $this->validated('orden') ?: 'relevancia';
    }
}
