<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una llamada a un proveedor de IA: tokens, costo real en USD y créditos descontados. */
class AiUsageLog extends Model
{
    protected $fillable = [
        'store_id', 'user_id', 'feature', 'provider', 'model',
        'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens',
        'cost_usd', 'credits', 'status', 'error',
    ];

    protected $casts = [
        'cost_usd' => 'decimal:6',
    ];
}
