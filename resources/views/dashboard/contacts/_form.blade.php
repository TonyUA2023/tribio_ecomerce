@php use App\Models\Contact; @endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="input-label" for="ct-name">Nombre</label>
        <input id="ct-name" name="name" class="input-field" maxlength="120" value="{{ old('name', $contact->name) }}" required>
    </div>
    <div>
        <label class="input-label" for="ct-phone">Celular / WhatsApp</label>
        <input id="ct-phone" name="phone" class="input-field" maxlength="30" inputmode="tel" value="{{ old('phone', $contact->phone_display) }}" placeholder="987 654 321">
    </div>
    <div>
        <label class="input-label" for="ct-email">Correo <span class="text-white/40 font-normal">(opcional)</span></label>
        <input id="ct-email" type="email" name="email" class="input-field" maxlength="150" value="{{ old('email', $contact->email) }}">
    </div>
    <div class="grid grid-cols-3 gap-2">
        <div>
            <label class="input-label" for="ct-doctype">Doc.</label>
            <select id="ct-doctype" name="document_type" class="input-field">
                @foreach(['dni' => 'DNI', 'ruc' => 'RUC', 'ce' => 'CE', 'pasaporte' => 'Pasap.'] as $key => $label)
                    <option value="{{ $key }}" @selected(old('document_type', $contact->document_type ?? 'dni') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-span-2">
            <label class="input-label" for="ct-doc">Número <span class="text-white/40 font-normal">(opcional)</span></label>
            <input id="ct-doc" name="document_number" class="input-field" maxlength="20" value="{{ old('document_number', $contact->document_number) }}">
        </div>
    </div>
    <div>
        <label class="input-label" for="ct-city">Ciudad <span class="text-white/40 font-normal">(opcional)</span></label>
        <input id="ct-city" name="city" class="input-field" maxlength="100" value="{{ old('city', $contact->city) }}">
    </div>
    <div>
        <label class="input-label" for="ct-birthday">Cumpleaños <span class="text-white/40 font-normal">(opcional)</span></label>
        <input id="ct-birthday" type="date" name="birthday" class="input-field" value="{{ old('birthday', $contact->birthday?->toDateString()) }}">
    </div>
    <div>
        <label class="input-label" for="ct-rel">Tipo de cliente</label>
        <select id="ct-rel" name="relationship" class="input-field">
            <option value="">Sin indicar</option>
            @foreach(Contact::RELATIONSHIPS as $key => $label)
                <option value="{{ $key }}" @selected(old('relationship', $contact->relationship) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="input-label" for="ct-tags">Etiquetas <span class="text-white/40 font-normal">(separadas por coma)</span></label>
        <input id="ct-tags" name="tags_text" class="input-field" value="{{ old('tags_text', implode(', ', $contact->tags ?? [])) }}" placeholder="Ej.: gimnasio, referida por Rosa">
    </div>
</div>
<div class="mt-4">
    <label class="input-label" for="ct-notes">Nota fija <span class="text-white/40 font-normal">(lo que siempre conviene recordar)</span></label>
    <textarea id="ct-notes" name="notes" rows="2" maxlength="2000" class="input-field" placeholder="Ej.: prefiere que le escriban en la tarde">{{ old('notes', $contact->notes) }}</textarea>
</div>
<label class="flex items-start gap-2 text-sm text-white/70 mt-4">
    <input type="checkbox" name="accepts_marketing" value="1" class="mt-1" @checked(old('accepts_marketing', $contact->accepts_marketing))>
    <span>Aceptó recibir ofertas y novedades
        <span class="block text-[11px] text-white/40">Márcalo solo si el cliente te dio permiso (Ley 29733). Sin esto no le enviaremos campañas. @if($contact->marketing_consent_at) Aceptó el {{ $contact->marketing_consent_at->locale('es')->isoFormat('D MMM YYYY') }}. @endif</span>
    </span>
</label>
