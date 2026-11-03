<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Controllers\Dashboard\ContactController;
use App\Models\Contact;
use App\Services\Whatsapp\WhatsappBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** A WhatsApp broadcast (Fase 3c). The live estimate posts the same fields without a name. */
class SaveWhatsappBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        $estimating = $this->routeIs('dashboard.whatsapp.broadcasts.estimate');

        return [
            'name'                => [$estimating ? 'nullable' : 'required', 'string', 'max:120'],
            'template_id'         => ['required', 'integer'],
            'stage'               => ['nullable', Rule::in(array_keys(Contact::STAGES))],
            'tag'                 => ['nullable', 'string', 'max:40'],
            'segment'             => ['nullable', Rule::in(array_keys(ContactController::SEGMENTS))],
            'skip_recent'         => ['nullable', 'boolean'],
            'variables'           => ['nullable', 'array', 'max:20'],
            'variables.*.source'  => ['nullable', 'string', 'max:30'],
            'variables.*.text'    => ['nullable', 'string', 'max:1000'],
            'send'                => ['nullable', Rule::in(['now', 'later'])],
            'scheduled_at'        => [$estimating ? 'nullable' : 'required_if:send,later', 'nullable', 'date_format:Y-m-d\TH:i', function ($attribute, $value, $fail) {
                if ($value && Carbon::createFromFormat('Y-m-d\TH:i', $value, WhatsappBudget::TIMEZONE)->lte(now()->addMinutes(5))) {
                    $fail('Programa la difusión para dentro de 5 minutos o más.');
                }
            }],
        ];
    }

    /** Filters, variables and the time; "now" drops any time left in the form. */
    public function broadcast(): array
    {
        $data = $this->validated();
        if (($data['send'] ?? 'now') !== 'later') {
            $data['scheduled_at'] = null;
        }
        $data['skip_recent'] = $this->boolean('skip_recent');

        return $data;
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'Ponle un nombre a la difusión (solo lo ves tú).',
            'template_id.required'     => 'Elige una plantilla aprobada.',
            'scheduled_at.required_if' => 'Elige el día y la hora del envío.',
            'scheduled_at.date_format' => 'Elige el día y la hora del envío.',
        ];
    }
}
