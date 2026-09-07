@extends('layouts.dashboard')
@section('title', 'Nueva Marca')
@section('page_title', '+ Marca')
@section('content')
<div class="glass-card p-8 max-w-md">
    <form method="POST" action="{{ route('dashboard.marcas.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="input-label">Nombre de la Marca *</label>
            <input type="text" name="name" class="input-field" value="{{ old('name') }}" required placeholder="Ej: Nike, Apple">
        </div>
        
        @if($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
            @foreach($errors->all() as $e)
            <p>• {{ $e }}</p>
            @endforeach
        </div>
        @endif

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Guardar</button>
            <a href="{{ route('dashboard.marcas.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
@endsection
