<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // PurchaseOrderService only accepts this store's tracked products
    }

    protected function prepareForValidation(): void
    {
        // Only the ticked rows: lines[product_id][selected]=1 & [quantity]=n
        $lines = collect($this->input('lines', []))
            ->filter(fn ($line) => is_array($line) && !empty($line['selected']))
            ->map(fn ($line, $productId) => ['product_id' => (int) $productId, 'quantity' => (int) ($line['quantity'] ?? 0)])
            ->values()->all();
        $this->merge(['items' => $lines]);
    }

    public function rules(): array
    {
        return [
            'supplier_id'        => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('store_id', $this->user()->currentStore()?->id)],
            'items'              => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'         => 'Marca al menos un producto para comprar.',
            'items.min'              => 'Marca al menos un producto para comprar.',
            'items.*.quantity.min'   => 'Cada producto marcado necesita una cantidad de al menos 1.',
            'supplier_id.exists'     => 'Ese proveedor no es de tu tienda.',
        ];
    }
}
