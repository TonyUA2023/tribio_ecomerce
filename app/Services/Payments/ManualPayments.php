<?php

namespace App\Services\Payments;

use App\Models\Store;
use Illuminate\Support\Facades\Storage;

/**
 * "Pago por confirmación": the buyer pays outside the site (Yape, Plin, bank transfer) and
 * the seller confirms receipt from the dashboard. Settings live in stores.manual_payments.
 */
class ManualPayments
{
    /** checkout payment_method key => [settings key, label, needs proof from the buyer] */
    public const METHODS = [
        'manual_yape' => ['key' => 'yape', 'label' => 'Yape', 'confirmable' => true],
        'manual_plin' => ['key' => 'plin', 'label' => 'Plin', 'confirmable' => true],
        'manual_bank' => ['key' => 'bank', 'label' => 'Transferencia / depósito', 'confirmable' => true],
        'manual_cod'  => ['key' => 'cod',  'label' => 'Pago contra entrega', 'confirmable' => false],
    ];

    public static function isManualKey(?string $paymentMethod): bool
    {
        return $paymentMethod !== null && isset(self::METHODS[$paymentMethod]);
    }

    public static function label(?string $paymentMethod): ?string
    {
        return self::METHODS[$paymentMethod]['label'] ?? null;
    }

    /** Enabled methods ready for the checkout drawer, keyed by payment_method. */
    public function enabledFor(Store $store): array
    {
        $out = [];
        foreach (self::METHODS as $paymentMethod => $meta) {
            $cfg = $this->settings($store)[$meta['key']] ?? [];
            if (empty($cfg['enabled'])) {
                continue;
            }
            $out[$paymentMethod] = [
                'method'      => $paymentMethod,
                'label'       => $meta['label'],
                'confirmable' => $meta['confirmable'],
                'holder'      => $cfg['holder'] ?? null,
                'phone'       => $cfg['phone'] ?? null,
                'bank'        => $cfg['bank'] ?? null,
                'account'     => $cfg['account'] ?? null,
                'cci'         => $cfg['cci'] ?? null,
                'instructions'=> $cfg['instructions'] ?? null,
                'qr_url'      => !empty($cfg['qr_path']) ? Storage::disk('public')->url($cfg['qr_path']) : null,
            ];
        }
        return $out;
    }

    public function isEnabled(Store $store, string $paymentMethod): bool
    {
        return isset($this->enabledFor($store)[$paymentMethod]);
    }

    public function settings(Store $store): array
    {
        return is_array($store->manual_payments) ? $store->manual_payments : [];
    }

    /** Footer / badge helper: which payment brands to advertise for this store. */
    public function badgesFor(Store $store): array
    {
        $badges = [];
        foreach ($this->enabledFor($store) as $m) {
            $badges[] = $m['label'];
        }
        return $badges;
    }
}
