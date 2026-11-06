<?php

namespace App\Services\Sunat;

use App\Models\ElectronicDocument;
use App\Models\Store;
use App\Models\SunatSetting;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use Illuminate\Support\Facades\Http;

/** SUNAT's billService (sendBill) through Greenter's SOAP client. */
class GreenterGateway implements SunatGateway
{
    /** SUNAT's beta environment only accepts these credentials, with any RUC. */
    private const BETA_USER = 'MODDATOS';
    private const BETA_PASSWORD = 'moddatos';

    public function __construct(private SunatSchema $schema)
    {
    }

    public function unavailable(): ?string
    {
        if (!$this->schema->libraryReady()) {
            return 'Falta instalar la librería de facturación (composer install).';
        }

        return $this->schema->soapReady() ? null : 'Tu servidor no tiene activada la extensión SOAP de PHP: pídele a tu hosting que la active.';
    }

    public static function endpoint(SunatSetting $setting): string
    {
        return filled($setting->endpoint) ? trim($setting->endpoint) : ($setting->isProduction() ? SunatEndpoints::FE_PRODUCCION : SunatEndpoints::FE_BETA);
    }

    public function send(SunatSetting $setting, Store $store, string $xml): SunatResult
    {
        if ($why = $this->unavailable()) {
            return SunatResult::error($why);
        }

        try {
            $see = new See();
            $see->setCertificate((string) $setting->certificate_pem);
            $see->setService(self::endpoint($setting));
            if (!filled($setting->endpoint) && !$setting->isProduction()) {
                $see->setClaveSOL((string) $store->ruc, self::BETA_USER, self::BETA_PASSWORD);
            } else {
                $see->setClaveSOL((string) $store->ruc, (string) $setting->sol_user, (string) $setting->sol_password);
            }

            return $this->read($see->sendXmlFile($xml));
        } catch (\Throwable $e) {
            report($e);

            return SunatResult::error('No pudimos comunicarnos con SUNAT: ' . mb_substr($e->getMessage(), 0, 300));
        }
    }

    public function ping(SunatSetting $setting): array
    {
        try {
            $response = Http::timeout(8)->get(self::endpoint($setting) . '?wsdl');

            return $response->successful()
                ? ['ok' => true, 'message' => 'El servicio de SUNAT responde.']
                : ['ok' => false, 'message' => 'El servicio respondió con el código ' . $response->status() . '.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No pudimos conectar con el servicio de SUNAT desde tu servidor.'];
        }
    }

    private function read(?\Greenter\Model\Response\BaseResult $result): SunatResult
    {
        if ($result === null) {
            return SunatResult::error('SUNAT no devolvió respuesta.');
        }

        if (!$result->isSuccess()) {
            $error = $result->getError();
            $code = (string) $error?->getCode();
            $message = (string) $error?->getMessage() ?: 'SUNAT no aceptó el envío.';

            // 1033: SUNAT already has this exact comprobante (a retry after a lost answer).
            if ($code === '1033') {
                return new SunatResult(ElectronicDocument::ACCEPTED, $code, 'SUNAT ya tenía registrado este comprobante.');
            }
            // 2000–3999: the document itself is wrong. Anything else (0xxx access/format, 1xxx service, network) is retried.
            $rejected = ctype_digit($code) && (int) $code >= 2000 && (int) $code < 4000;

            return new SunatResult($rejected ? ElectronicDocument::REJECTED : ElectronicDocument::ERROR, $code ?: null, $message);
        }

        $cdr = $result->getCdrResponse();
        $zip = $result->getCdrZip();
        $code = (string) $cdr?->getCode();
        $notes = array_filter((array) $cdr?->getNotes());
        $message = trim((string) $cdr?->getDescription() . ($notes ? ' · ' . implode(' · ', $notes) : ''));

        if ($cdr && !$cdr->isAccepted()) {
            return new SunatResult(ElectronicDocument::REJECTED, $code, $message ?: 'SUNAT rechazó el comprobante.', $zip ? base64_encode($zip) : null);
        }

        return new SunatResult($notes || (int) $code >= 4000 ? ElectronicDocument::OBSERVED : ElectronicDocument::ACCEPTED, $code ?: '0', $message, $zip ? base64_encode($zip) : null);
    }
}
