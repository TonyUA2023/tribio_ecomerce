<?php

namespace App\Services\Sunat;

use App\Models\SunatSetting;
use Greenter\Factory\FeFactory;
use Greenter\Factory\XmlBuilderResolver;
use Greenter\Model\Sale\Invoice;
use Greenter\XMLSecLibs\Sunat\SignedXml;

/** Builds the UBL XML of an invoice and signs it with the business's certificate. Needs no connection. */
class XmlSigner
{
    /** @return array{xml: string, hash: ?string} */
    public function sign(SunatSetting $setting, Invoice $invoice): array
    {
        // The builder and the signer only: building and signing need no SOAP, so a comprobante can be
        // prepared (and kept) even on a server that cannot reach SUNAT yet.
        $signer = new SignedXml();
        $signer->setCertificate((string) $setting->certificate_pem);
        $factory = new FeFactory();
        $factory->setSigner($signer);
        $xml = $factory->setBuilder((new XmlBuilderResolver(['autoescape' => false]))->find(Invoice::class))->getXmlSigned($invoice);
        if (!$xml) {
            throw new \RuntimeException('No pudimos generar el XML del comprobante.');
        }

        return ['xml' => $xml, 'hash' => $this->digest($xml)];
    }

    /** The value SUNAT prints in the QR and the ticket: the DigestValue of the signature. */
    private function digest(string $xml): ?string
    {
        return preg_match('~<(?:\w+:)?DigestValue>([^<]+)</~', $xml, $match) ? trim($match[1]) : null;
    }
}
