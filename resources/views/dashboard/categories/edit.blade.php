@extends('layouts.dashboard')
@section('title','Editar Categoría') @section('page_title','✏️ Categoría')
@section('content')
<div class="glass-card p-8 max-w-md">
    <form method="POST" action="{{ route('dashboard.categorias.update', $category) }}" class="space-y-4">@csrf @method('PUT')
        <div><label class="input-label">Nombre *</label><input type="text" name="name" class="input-field" value="{{ old('name', $category->name) }}" required></div>
        <div><label class="input-label">Ícono</label><input type="text" name="icon" class="input-field" value="{{ old('icon', $category->icon) }}" maxlength="5"></div>
        <div><label class="input-label">Color</label><input type="color" name="color" value="{{ old('color', $category->color ?? '#8B5CF6') }}" class="h-10 w-full rounded-xl border-0 bg-transparent cursor-pointer"></div>
        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" {{ $category->is_active ? 'checked' : '' }} class="accent-tribio-purple"><span class="text-white/70 text-sm">Activa</span></label>
        <div class="flex gap-3"><button type="submit" class="btn-primary">Guardar</button><a href="{{ route('dashboard.categorias.index') }}" class="btn-ghost">Cancelar</a></div>
    </form>
</div>
@endsection
