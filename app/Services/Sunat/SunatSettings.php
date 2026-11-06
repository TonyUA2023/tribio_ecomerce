<?php

namespace App\Services\Sunat;

use App\Models\Branch;
use App\Models\DocumentSeries;
use App\Models\Store;
use App\Models\SunatSetting;
use App\Services\Pos\SalesDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The business's SUNAT configuration: environment, what gets sent, the SOL user and the digital
 * certificate. Secrets are write-only (stored encrypted; a screen only learns that they exist) and
 * the certificate is checked when it is uploaded — valid, not expired, key matches — so a wrong file
 * is refused then, not at the first sale.
 */
class SunatSettings
{
    public const MAX_CERT_BYTES = 65536;

    public function __construct(private SunatSchema $schema, private Branches $branches, private SunatGateway $gateway)
    {
    }

    public function for(Store $store): SunatSetting
    {
        return SunatSetting::firstOrCreate(['store_id' => $store->id], ['email_to' => $store->contact_email]);
    }

    public function save(Store $store, array $data): SunatSetting
    {
        $setting = $this->for($store);
        $fields = [
            'environment'    => $data['environment'] === SunatSetting::PRODUCTION ? SunatSetting::PRODUCTION : SunatSetting::BETA,
            'factura_mode'   => $data['factura_mode'] === 'manual' ? 'manual' : 'auto',
            'boleta_mode'    => in_array($data['boleta_mode'], ['manual', 'auto', 'never'], true) ? $data['boleta_mode'] : 'manual',
            'endpoint'       => filled($data['endpoint'] ?? null) ? trim($data['endpoint']) : null,
            'email_company'  => (bool) ($data['email_company'] ?? false),
            'email_to'       => filled($data['email_to'] ?? null) ? trim($data['email_to']) : null,
            'email_customer' => (bool) ($data['email_customer'] ?? false),
            'max_attempts'   => max(1, min(10, (int) ($data['max_attempts'] ?? 5))),
        ];
        // Write-only: blank keeps what is stored.
        if (filled($data['sol_user'] ?? null)) {
            $fields['sol_user'] = trim($data['sol_user']);
        }
        if (filled($data['sol_password'] ?? null)) {
            $fields['sol_password'] = $data['sol_password'];
        }
        $setting->fill($fields)->save();

        // It can only be on while nothing blocks it; switching it on with a gap says exactly which.
        $wantsOn = (bool) ($data['enabled'] ?? false);
        if ($wantsOn) {
            $problems = $this->problems($store, $setting->fresh(), enabling: true);
            if ($problems) {
                $setting->update(['enabled' => false]);
                throw ValidationException::withMessages(['enabled' => 'Falta para activar: ' . implode(' ', $problems)]);
            }
        }
        $setting->update(['enabled' => $wantsOn]);

        return $setting->fresh();
    }

    /**
     * Reads a .pfx/.p12 (with its password) or a .pem (certificate and key) and keeps it, encrypted.
     *
     * @throws ValidationException
     */
    public function storeCertificate(Store $store, UploadedFile $file, ?string $password): SunatSetting
    {
        if ($file->getSize() > self::MAX_CERT_BYTES) {
            $this->refuse('Ese archivo es demasiado grande para ser un certificado.');
        }
        $parsed = $this->parse((string) file_get_contents($file->getRealPath()), $password);

        $setting = $this->for($store);
        $setting->update(['certificate_pem' => $parsed['pem'], 'certificate_subject' => $parsed['subject'], 'certificate_valid_to' => $parsed['valid_to'], 'verified_at' => null]);

        return $setting->fresh();
    }

    public function removeCertificate(Store $store): void
    {
        $this->for($store)->update(['certificate_pem' => null, 'certificate_subject' => null, 'certificate_valid_to' => null, 'enabled' => false]);
    }

    /** @return array{pem: string, subject: string, valid_to: \Illuminate\Support\Carbon} */
    public function parse(string $content, ?string $password): array
    {
        $cert = $key = null;
        if (str_contains($content, '-----BEGIN')) {
            $cert = @openssl_x509_read($content) ?: null;
            $private = @openssl_pkey_get_private($content, (string) $password) ?: null;
            if (!$cert || !$private) {
                $this->refuse('El .pem debe traer el certificado y la llave privada (y su contraseña si está protegida).');
            }
            openssl_x509_export($cert, $certPem);
            openssl_pkey_export($private, $key);
        } else {
            $certs = [];
            if (!@openssl_pkcs12_read($content, $certs, (string) $password)) {
                $this->refuse('No pudimos abrir el certificado: revisa la contraseña. Si es correcta, el archivo puede usar un cifrado antiguo: expórtalo de nuevo como .pfx o conviértelo a .pem.');
            }
            $certPem = $certs['cert'] ?? null;
            $key = $certs['pkey'] ?? null;
            $cert = $certPem ? openssl_x509_read($certPem) : null;
        }
        if (!$cert || !$key || !isset($certPem)) {
            $this->refuse('El archivo no trae el certificado con su llave privada.');
        }
        if (!openssl_x509_check_private_key($cert, $key)) {
            $this->refuse('La llave privada no corresponde a este certificado.');
        }

        $info = openssl_x509_parse($cert);
        $from = \Illuminate\Support\Carbon::createFromTimestamp($info['validFrom_time_t']);
        $to = \Illuminate\Support\Carbon::createFromTimestamp($info['validTo_time_t']);
        if ($to->isPast()) {
            $this->refuse('El certificado venció el ' . $to->format('d/m/Y') . '. Renuévalo con tu proveedor de certificados.');
        }
        if ($from->isFuture()) {
            $this->refuse('El certificado todavía no está vigente (desde el ' . $from->format('d/m/Y') . ').');
        }

        return ['pem' => trim($certPem) . "\n" . trim($key) . "\n", 'subject' => mb_substr((string) ($info['name'] ?? ''), 0, 255), 'valid_to' => $to];
    }

    /** What stops a comprobante from being sent right now (empty = ready). @return list<string> */
    public function problems(Store $store, SunatSetting $setting, bool $enabling = false): array
    {
        $problems = [];
        if (!$this->schema->ready()) {
            return ['La facturación electrónica todavía no está activada en el servidor.'];
        }
        if ($why = $this->gateway->unavailable()) {
            $problems[] = $why;
        }
        if (!SalesDocuments::validRuc((string) $store->ruc)) {
            $problems[] = 'Registra un RUC válido en Comprobantes.';
        }
        if (!$setting->hasCertificate()) {
            $problems[] = 'Sube tu certificado digital.';
        } elseif ($setting->certificateExpired()) {
            $problems[] = 'Tu certificado digital venció: súbelo de nuevo.';
        }
        if ($setting->isProduction() || filled($setting->endpoint)) {
            if (!filled($setting->sol_user) || !$setting->hasSolPassword()) {
                $problems[] = 'Registra tu usuario y clave SOL secundarios.';
            }
        }
        $main = $this->branches->main($store);
        if ($missing = $main->fiscalProblems()) {
            $problems[] = 'La sede principal necesita ' . implode(' y ', $missing) . '.';
        }
        foreach ([SalesDocuments::BOLETA => 'B', SalesDocuments::FACTURA => 'F'] as $type => $prefix) {
            $series = DocumentSeries::where('store_id', $store->id)->where('type', $type)->whereNull('branch_id')->whereNull('register_id')->value('series');
            if ($series && !preg_match('/^' . $prefix . '[A-Z0-9]{3}$/', $series)) {
                $problems[] = 'La serie de ' . SalesDocuments::TYPES[$type]['label'] . ' debe empezar con ' . $prefix . ' (ej. ' . $prefix . '001).';
            }
        }

        return $problems;
    }

    /**
     * The screen's checklist: each requirement with whether it is met and what to do. `ok` null = a warning.
     *
     * @return list<array{label: string, ok: ?bool, hint: ?string}>
     */
    public function checklist(Store $store, SunatSetting $setting): array
    {
        $main = $this->branches->main($store);
        $list = [
            ['label' => 'Librería de facturación y extensión SOAP de PHP', 'ok' => $this->gateway->unavailable() === null, 'hint' => $this->gateway->unavailable() ?? ''],
            ['label' => 'RUC válido', 'ok' => SalesDocuments::validRuc((string) $store->ruc), 'hint' => 'Regístralo en Caja → Comprobantes.'],
            ['label' => 'Sede principal con dirección y ubigeo', 'ok' => !$main->fiscalProblems(), 'hint' => 'Complétala en Sedes y cajas.'],
        ];
        $cert = $setting->hasCertificate() && !$setting->certificateExpired();
        $list[] = ['label' => 'Certificado digital vigente' . ($setting->certificate_valid_to ? ' · vence el ' . $setting->certificate_valid_to->format('d/m/Y') : ''), 'ok' => $cert,
            'hint' => $setting->certificateExpired() ? 'Venció: renuévalo y súbelo.' : 'Súbelo abajo (.pfx o .pem).'];
        if ($cert && filled($store->ruc) && !str_contains((string) $setting->certificate_subject, (string) $store->ruc)) {
            $list[] = ['label' => 'El certificado menciona tu RUC', 'ok' => null, 'hint' => 'No vimos el RUC ' . $store->ruc . ' en el certificado. Si es de otra empresa, SUNAT lo rechazará.'];
        }
        $needsSol = $setting->isProduction() || filled($setting->endpoint);
        $list[] = ['label' => 'Usuario y clave SOL secundarios', 'ok' => $needsSol ? (filled($setting->sol_user) && $setting->hasSolPassword()) : null,
            'hint' => $needsSol ? 'Créalos en SUNAT Operaciones en Línea con el perfil «Emisión electrónica».' : 'En pruebas no hacen falta: SUNAT usa un usuario de prueba.'];

        return $list;
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['certificate' => $message]);
    }
}
