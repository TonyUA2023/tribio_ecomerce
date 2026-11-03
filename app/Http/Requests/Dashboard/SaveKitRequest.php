<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Inventory\ProductStructureService;
use Illuminate\Foundation\Http\FormRequest;

class SaveKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ProductStructureService checks every component belongs to the store
    }

    protected function prepareForValidation(): void
    {
        $rows = json_decode((string) $this->input('components_json'), true);
        $this->merge(['components' => is_array($rows)
            ? array_values(array_filter($rows, fn ($row) => is_array($row) && !empty($row['product_id'])))
            : []]);
    }

    public function rules(): array
    {
        return [
            'components'              => ['array', 'max:' . ProductStructureService::MAX_COMPONENTS],
            'components.*.product_id' => ['required', 'integer', 'distinct'],
            'components.*.quantity'   => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'components.*.product_id.distinct' => 'Hay un producto repetido en el combo.',
            'components.*.quantity.min'        => 'Cada producto del combo necesita al menos 1 unidad.',
        ];
    }

    /** @return list<array{product_id: int, quantity: int}> */
    public function components(): array
    {
        return array_map(fn ($row) => ['product_id' => (int) $row['product_id'], 'quantity' => (int) $row['quantity']], $this->validated()['components'] ?? []);
    }
}
