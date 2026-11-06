<?php

namespace Tests\Support;

use App\Models\Store;
use App\Models\SunatSetting;
use App\Services\Sunat\SunatGateway;
use App\Services\Sunat\SunatResult;

/** A SUNAT that answers what the test says, and remembers what it was sent. */
class FakeSunatGateway implements SunatGateway
{
    /** @var list<SunatResult> answers in order; the last one repeats */
    public array $answers = [];
    /** @var list<string> */
    public array $sent = [];

    public function __construct(?SunatResult $answer = null)
    {
        $this->answers = [$answer ?? new SunatResult('accepted', '0', 'La Boleta numero B001-1, ha sido aceptada', base64_encode('PK-fake-cdr'))];
    }

    public function send(SunatSetting $setting, Store $store, string $xml): SunatResult
    {
        $this->sent[] = $xml;

        return count($this->answers) > 1 ? array_shift($this->answers) : $this->answers[0];
    }

    public function unavailable(): ?string
    {
        return null;
    }

    public function ping(SunatSetting $setting): array
    {
        return ['ok' => true, 'message' => 'El servicio de SUNAT responde.'];
    }
}
