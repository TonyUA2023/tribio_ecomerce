<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Geo\GeoCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** Owner settings for "pago por confirmación" and for the delivery coverage. */
class ManualPaymentsController extends Controller
{
    public function update(Request $request)
    {
        $store = Auth::user()->currentStore();
        abort_unless($store, 404);

        $request->validate([
            'yape.enabled' => 'nullable|boolean', 'yape.holder' => 'nullable|string|max:120', 'yape.phone' => 'nullable|string|max:30',
            'yape.instructions' => 'nullable|string|max:500', 'yape.qr' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3072',
            'plin.enabled' => 'nullable|boolean', 'plin.holder' => 'nullable|string|max:120', 'plin.phone' => 'nullable|string|max:30',
            'plin.instructions' => 'nullable|string|max:500', 'plin.qr' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3072',
            'bank.enabled' => 'nullable|boolean', 'bank.holder' => 'nullable|string|max:120', 'bank.bank' => 'nullable|string|max:80',
            'bank.account' => 'nullable|string|max:40', 'bank.cci' => 'nullable|string|max:40', 'bank.instructions' => 'nullable|string|max:500',
            'cod.enabled' => 'nullable|boolean', 'cod.instructions' => 'nullable|string|max:500',
        ]);

        $current = is_array($store->manual_payments) ? $store->manual_payments : [];
        $fields = [
            'yape' => ['holder', 'phone', 'instructions'],
            'plin' => ['holder', 'phone', 'instructions'],
            'bank' => ['holder', 'bank', 'account', 'cci', 'instructions'],
            'cod'  => ['instructions'],
        ];

        $next = [];
        foreach ($fields as $key => $names) {
            $cfg = ['enabled' => $request->boolean("$key.enabled")];
            foreach ($names as $name) {
                $value = trim((string) $request->input("$key.$name"));
                $cfg[$name] = $value !== '' ? $value : null;
            }
            $qrPath = $current[$key]['qr_path'] ?? null;
            if (in_array($key, ['yape', 'plin'], true)) {
                if ($request->hasFile("$key.qr")) {
                    if ($qrPath) {
                        Storage::disk('public')->delete($qrPath);
                    }
                    $qrPath = $request->file("$key.qr")->store("manual-payments/{$store->id}", 'public');
                } elseif ($request->boolean("$key.remove_qr") && $qrPath) {
                    Storage::disk('public')->delete($qrPath);
                    $qrPath = null;
                }
                $cfg['qr_path'] = $qrPath;
            }
            // A method needs something to pay to (except cash on delivery).
            if ($cfg['enabled'] && $key !== 'cod' && empty($cfg['phone'] ?? null) && empty($cfg['account'] ?? null) && empty($cfg['qr_path'] ?? null)) {
                return back()->withErrors(["$key.enabled" => 'Para activar este método agrega un número, una cuenta o un QR.'])->withInput();
            }
            $next[$key] = $cfg;
        }

        $store->update(['manual_payments' => $next]);

        return back()->with('success', 'Pagos por confirmación guardados.');
    }

    public function coverage(Request $request, GeoCatalog $geo)
    {
        $store = Auth::user()->currentStore();
        abort_unless($store, 404);

        $request->validate(['departments' => 'nullable|array', 'departments.*' => 'string|max:100', 'exchange_hours' => 'nullable|integer|in:24,48,72,120,168']);
        $valid = $geo->states('PE');
        $selected = array_values(array_intersect($valid, (array) $request->input('departments', [])));

        $store->update([
            'delivery_coverage' => $selected ?: null,
            'exchange_hours' => $request->filled('exchange_hours') ? (int) $request->input('exchange_hours') : null,
        ]);

        return back()->with('success', $selected
            ? 'Cobertura guardada: solo envías a ' . implode(', ', $selected) . '.'
            : 'Cobertura guardada: envías a todo el país.');
    }
}
