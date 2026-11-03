<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmSchema;
use App\Services\Crm\ReorderReminders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Inventario → producto → Recompra: how long one unit lasts a customer (ReorderReminders). */
class ProductReorderController extends Controller
{
    public function update(Request $request, CrmSchema $schema, ReorderReminders $reminders, int $product): RedirectResponse
    {
        abort_unless($schema->reorderReady(), 404);
        $product = $request->user()->currentStore()->products()->findOrFail($product);

        $data = $request->validate(
            ['consumption_days' => ['nullable', 'integer', 'min:1', 'max:365']],
            ['consumption_days.min' => 'Una unidad dura al menos 1 día.', 'consumption_days.max' => 'Máximo 365 días.'],
        );
        $product->update(['consumption_days' => $data['consumption_days'] ?? null]);
        $customers = $reminders->refreshForProduct($product);

        if (!$product->consumption_days) {
            return back()->with('success', "Listo: ya no agendamos recompras de «{$product->name}».");
        }

        return back()->with('success', "Listo: una unidad dura {$product->consumption_days} días. Revisamos las recompras de "
            . $customers . ($customers === 1 ? ' cliente' : ' clientes') . ' que lo compraron en los últimos 6 meses.');
    }
}
