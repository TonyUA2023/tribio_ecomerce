<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Voz y colores de una tienda para todo lo que se genera en Marketing → Contenido. */
class BrandKit extends Model
{
    public const TONES = [
        'cercano'     => 'Cercano y amable',
        'profesional' => 'Profesional y claro',
        'divertido'   => 'Divertido y con energía',
        'premium'     => 'Elegante / premium',
    ];

    protected $fillable = [
        'store_id', 'tone', 'slogan', 'audience', 'banned_words',
        'primary_color', 'secondary_color', 'show_whatsapp',
    ];

    protected $casts = [
        'banned_words'  => 'array',
        'show_whatsapp' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Kit guardado, o uno por defecto (sin guardar) con los colores de la tienda. */
    public static function forStore(Store $store): self
    {
        return static::firstWhere('store_id', $store->id) ?? new static([
            'store_id'        => $store->id,
            'tone'            => 'cercano',
            'primary_color'   => self::hex($store->accent_color) ?? '#0f766e',
            'secondary_color' => self::hex($store->secondary_color) ?? '#111827',
            'show_whatsapp'   => true,
        ]);
    }

    private static function hex(?string $color): ?string
    {
        return $color && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : null;
    }
}
