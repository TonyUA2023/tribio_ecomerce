<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // PurchaseOrderService only accepts this store's products and suppliers
    }

    protected function prepareForValidation(): void
    {
        // The Alpine form posts its lines as JSON (one hidden field), like the variants editor.
        $items = json_decode((string) $this->input('items_json'), true);
        $this->merge([
            'items'        => is_array($items) ? array_values($items) : [],
            'mark_ordered' => $this->boolean('mark_ordered'),
        ]);
    }

    public function rules(): array
    {
        $storeId = $this->user()->currentStore()?->id;

        return [
            'supplier_id'          => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('store_id', $storeId)],
            'expected_at'          => ['nullable', 'date'],
            'extra_costs'          => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'mark_ordered'         => ['boolean'],
            'items'                => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id'   => ['required', 'integer', 'distinct'],
            'items.*.quantity'     => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost'    => ['required', 'numeric', 'min:0', 'max:9999999'],
            'items.*.unit_id'      => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'               => 'Agrega al menos un producto a la compra.',
            'items.min'                    => 'Agrega al menos un producto a la compra.',
            'items.*.product_id.distinct'  => 'Hay un producto repetido: junta sus cantidades en una sola línea.',
            'items.*.quantity.min'         => 'Cada producto necesita una cantidad de al menos 1.',
            'items.*.unit_cost.required'   => 'Indica el costo por unidad de cada producto.',
            'supplier_id.exists'           => 'Ese proveedor no es de tu tienda.',
        ];
    }

    /** @return array<string, mixed> shaped for PurchaseOrderService::save() */
    public function payload(): array
    {
        $data = $this->validated();
        $data['items'] = array_map(fn ($line) => [
            'product_id' => (int) $line['product_id'],
            'quantity'   => (int) $line['quantity'],
            'unit_cost'  => (float) $line['unit_cost'],
            'unit_id'    => !empty($line['unit_id']) ? (int) $line['unit_id'] : null,
        ], $data['items']);

        return $data;
    }
}
