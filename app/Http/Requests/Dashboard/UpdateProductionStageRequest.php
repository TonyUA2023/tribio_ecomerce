<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $order->store_id === $this->user()?->currentStore()?->id;
    }

    public function rules(): array
    {
        return [
            'production_stage' => ['required', Rule::in(array_keys(Order::PRODUCTION_STAGES))],
            'notify_customer' => ['nullable', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['production_stage' => 'etapa de producción', 'message' => 'mensaje para el cliente'];
    }
}
