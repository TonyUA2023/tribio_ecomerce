<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="input-label" for="sup-name">Nombre o razón social</label>
        <input id="sup-name" name="name" class="input-field" maxlength="120" value="{{ old('name', $supplier->name) }}" required>
        @error('name')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="input-label" for="sup-doc">RUC o DNI <span class="text-white/40 font-normal">(opcional)</span></label>
        <input id="sup-doc" name="document_number" class="input-field" maxlength="20" value="{{ old('document_number', $supplier->document_number) }}">
    </div>
    <div>
        <label class="input-label" for="sup-contact">Persona de contacto <span class="text-white/40 font-normal">(opcional)</span></label>
        <input id="sup-contact" name="contact_name" class="input-field" maxlength="120" value="{{ old('contact_name', $supplier->contact_name) }}">
    </div>
    <div>
        <label class="input-label" for="sup-phone">Celular <span class="text-white/40 font-normal">(opcional)</span></label>
        <input id="sup-phone" name="phone" class="input-field" maxlength="30" value="{{ old('phone', $supplier->phone) }}">
    </div>
    <div>
        <label class="input-label" for="sup-email">Correo <span class="text-white/40 font-normal">(opcional)</span></label>
        <input id="sup-email" type="email" name="email" class="input-field" maxlength="150" value="{{ old('email', $supplier->email) }}">
        @error('email')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="input-label" for="sup-notes">Notas <span class="text-white/40 font-normal">(opcional)</span></label>
        <input id="sup-notes" name="notes" class="input-field" maxlength="1000" value="{{ old('notes', $supplier->notes) }}" placeholder="Días de entrega, forma de pago…">
    </div>
</div>
