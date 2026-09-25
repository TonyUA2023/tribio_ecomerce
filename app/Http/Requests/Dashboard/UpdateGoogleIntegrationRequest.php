<?php

namespace App\Http\Requests\Dashboard;

use App\Models\StoreMarketingIntegration;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dashboard → Marketing → Google. Forgiving on input like the Meta form: owners paste
 * whatever Google shows them — the whole gtag.js snippet, an event snippet with
 * `send_to: 'AW-123/abc'`, the whole <meta name="google-site-verification"> tag — and
 * the ids are pulled out of that before validating.
 */
class UpdateGoogleIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->currentStore();
    }

    protected function prepareForValidation(): void
    {
        [$adsId, $label] = $this->adsConversion((string) $this->input('ads_conversion_id'), (string) $this->input('ads_conversion_label'));

        $this->merge([
            'measurement_id'       => $this->firstMatch('/\b(G-[A-Z0-9]{4,20})\b/i', (string) $this->input('measurement_id')),
            'ads_conversion_id'    => $adsId,
            'ads_conversion_label' => $label,
            'domain_verification'  => $this->verificationCode($this->input('domain_verification')),
        ]);
    }

    public function rules(): array
    {
        return [
            'is_active'            => ['nullable', 'boolean'],
            'measurement_id'       => ['nullable', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'ads_conversion_id'    => ['nullable', 'regex:/^AW-\d{6,15}$/', 'required_with:ads_conversion_label'],
            'ads_conversion_label' => ['nullable', 'regex:/^[A-Za-z0-9_\-]{4,64}$/'],
            'domain_verification'  => ['nullable', 'regex:/^[A-Za-z0-9_\-]{20,100}$/'],
            'default_condition'    => ['required', 'in:' . implode(',', array_keys(StoreMarketingIntegration::CONDITIONS))],
        ];
    }

    public function messages(): array
    {
        return [
            'measurement_id.regex'             => 'El ID de Google Analytics empieza con G- (por ejemplo, G-AB12CD34EF). Lo encuentras en Analytics → Administrar → Flujos de datos.',
            'ads_conversion_id.regex'          => 'El ID de Google Ads empieza con AW- seguido de números (por ejemplo, AW-123456789).',
            'ads_conversion_id.required_with'  => 'Falta el ID de Google Ads (AW-…) de esa conversión.',
            'ads_conversion_label.regex'       => 'No reconocimos la etiqueta de conversión. Pega el fragmento completo del evento que te da Google Ads.',
            'domain_verification.regex'        => 'No reconocimos el código. Pega la etiqueta completa <meta name="google-site-verification" …> que te da Google.',
        ];
    }

    /**
     * "AW-123/abcDEF" (or a whole snippet containing send_to) fills both fields; the
     * separate label field wins when it was typed on its own.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function adsConversion(string $idInput, string $labelInput): array
    {
        $id = null;
        $label = trim($labelInput) !== '' ? trim($labelInput) : null;

        if (preg_match('#\b(AW-\d{6,15})\s*/\s*([A-Za-z0-9_\-]{4,64})#', $idInput, $m)) {
            $id = strtoupper($m[1]);
            $label ??= $m[2];
        } elseif (preg_match('/\b(AW-\d{6,15})\b/i', $idInput, $m)) {
            $id = strtoupper($m[1]);
        } elseif (trim($idInput) !== '') {
            $id = trim($idInput); // let validation explain what's wrong
        }
        if ($label !== null && preg_match('#\bAW-\d{6,15}\s*/\s*([A-Za-z0-9_\-]{4,64})#i', $label, $m)) {
            $label = $m[1]; // the whole send_to pasted into the label field
        }

        return [$id, $label];
    }

    private function firstMatch(string $pattern, string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return preg_match($pattern, $value, $m) ? strtoupper($m[1]) : $value;
    }

    /** <meta name="google-site-verification" content="abc123"> → "abc123". */
    private function verificationCode(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/content\s*=\s*[\'"]([^\'"]+)[\'"]/i', $value, $m)) {
            $value = trim($m[1]);
        }

        return $value !== '' ? $value : null;
    }
}
