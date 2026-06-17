@extends('layouts.dashboard')
@section('title','Nueva Categoría') @section('page_title','+ Categoría')
@section('content')
<div class="glass-card p-8 max-w-md">
    <form method="POST" action="{{ route('dashboard.categorias.store') }}" class="space-y-4">@csrf
        <div><label class="input-label">Nombre *</label><input type="text" name="name" class="input-field" value="{{ old('name') }}" required placeholder="Ej: Tortas"></div>
        <div><label class="input-label">Ícono (emoji)</label><input type="text" name="icon" class="input-field" value="{{ old('icon') }}" placeholder="🎂" maxlength="5"></div>
        <div><label class="input-label">Color</label><input type="color" name="color" value="{{ old('color', '#8B5CF6') }}" class="h-10 w-full rounded-xl border-0 bg-transparent cursor-pointer"></div>
        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked class="accent-tribio-purple"><span class="text-white/70 text-sm">Activa</span></label>
        @if($errors->any())<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">@foreach($errors->all() as $e)<p>• {{ $e }}</p>@endforeach</div>@endif
        <div class="flex gap-3"><button type="submit" class="btn-primary">Guardar</button><a href="{{ route('dashboard.categorias.index') }}" class="btn-ghost">Cancelar</a></div>
    </form>
</div>
@endsection
