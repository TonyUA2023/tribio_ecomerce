<?php

namespace App\Services\Sunat;

use App\Models\Store;
use App\Models\SunatSetting;

/** The wire to SUNAT (or to an OSE/PSE with the same billService). Swapped for a fake in tests. */
interface SunatGateway
{
    /** Sends an already signed XML and reads the CDR. Never throws: a failure comes back as an `error` result. */
    public function send(SunatSetting $setting, Store $store, string $xml): SunatResult;

    /** Why this gateway cannot send from this server (null = it can). */
    public function unavailable(): ?string;

    /** Is the service reachable at all? (used by "Probar configuración"). @return array{ok: bool, message: string} */
    public function ping(SunatSetting $setting): array;
}
