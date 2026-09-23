<?php

namespace App\Http\Requests\Dashboard;

use App\Models\StoreMarketingIntegration;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dashboard → Marketing → Meta. Forgiving on input on purpose: store owners paste
 * whatever Meta shows them (the whole Pixel code, the whole <meta> tag), so the ids
 * are pulled out of that before validating.
 */
class UpdateMetaIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->currentStore();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pixel_id'            => $this->pixelId($this->input('pixel_id')),
            'capi_token'          => trim((string) $this->input('capi_token')) ?: null,
            'test_event_code'     => strtoupper(trim((string) $this->input('test_event_code'))) ?: null,
            'domain_verification' => $this->domainCode($this->input('domain_verification')),
        ]);
    }

    public function rules(): array
    {
        return [
            'is_active'           => ['nullable', 'boolean'],
            'pixel_id'            => ['nullable', 'regex:/^\d{8,20}$/'],
            'capi_token'          => ['nullable', 'string', 'min:30', 'max:1000', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'remove_capi_token'   => ['nullable', 'boolean'],
            'test_event_code'     => ['nullable', 'regex:/^TEST[A-Z0-9]{1,30}$/'],
            'domain_verification' => ['nullable', 'regex:/^[A-Za-z0-9]{10,64}$/'],
            'default_condition'   => ['required', 'in:' . implode(',', array_keys(StoreMarketingIntegration::CONDITIONS))],
        ];
    }

    public function messages(): array
    {
        return [
            'pixel_id.regex'            => 'El ID del Píxel son solo números (entre 8 y 20). Lo encuentras en el Administrador de eventos de Meta.',
            'capi_token.min'            => 'Ese token parece incompleto. Cópialo entero desde el Administrador de eventos → Configuración → API de conversiones.',
            'capi_token.regex'          => 'El token solo tiene letras y números, sin espacios. Cópialo de nuevo desde Meta.',
            'test_event_code.regex'     => 'El código de prueba empieza con TEST (por ejemplo, TEST12345).',
            'domain_verification.regex' => 'No reconocimos el código. Pega la etiqueta completa que te da Meta o solo el valor de content.',
        ];
    }

    /** "1234567890", "fbq('init', '1234567890')" or the whole snippet → "1234567890". */
    private function pixelId(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/fbq\(\s*[\'"]init[\'"]\s*,\s*[\'"](\d+)[\'"]/', $value, $m)
            || preg_match('/[?&]id=(\d+)/', $value, $m)) {
            return $m[1];
        }

        return preg_match('/^[\d\s]+$/', $value) ? preg_replace('/\s+/', '', $value) : $value;
    }

    /** <meta name="facebook-domain-verification" content="abc123"> → "abc123". */
    private function domainCode(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/content\s*=\s*[\'"]([^\'"]+)[\'"]/i', $value, $m)) {
            $value = trim($m[1]);
        }

        return $value !== '' ? $value : null;
    }
}
