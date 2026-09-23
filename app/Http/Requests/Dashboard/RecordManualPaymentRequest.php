<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class RecordManualPaymentRequest extends FormRequest
{
    public const METHODS = [
        'yape' => 'Yape',
        'plin' => 'Plin',
        'transferencia' => 'Transferencia',
        'efectivo' => 'Efectivo',
        'pos' => 'Tarjeta (POS)',
        'otro' => 'Otro',
    ];

    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $order->store_id === $this->user()?->currentStore()?->id;
    }

    public function rules(): array
    {
        /** @var Order $order */
        $order = $this->route('order');

        return [
            // Never more than what is still owed: a typo can't mark an order overpaid.
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . max(0.01, (float) $order->balance_due)],
            'method' => ['required', 'in:' . implode(',', array_keys(self::METHODS))],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.max' => 'El monto no puede ser mayor al saldo pendiente.',
            'amount.min' => 'Ingresa el monto recibido.',
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'monto', 'method' => 'medio de pago', 'note' => 'nota'];
    }
}
