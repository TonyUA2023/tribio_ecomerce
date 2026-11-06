<?php

namespace App\Jobs;

use App\Models\ElectronicDocument;
use App\Services\Sunat\ElectronicDocuments;
use Illuminate\Foundation\Bus\Dispatchable;

/** Sends one comprobante to SUNAT. Dispatched after the response (no worker needed); also used by the retry command. */
class SendElectronicDocument
{
    use Dispatchable;

    public function __construct(public int $documentId)
    {
    }

    public function handle(ElectronicDocuments $documents): void
    {
        if ($doc = ElectronicDocument::find($this->documentId)) {
            $documents->send($doc);
        }
    }
}
