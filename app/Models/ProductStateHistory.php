<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStateHistory extends Model
{
    use HasFactory;

    protected $table = 'product_state_history';

    protected $fillable = [
        'product_id',
        'user_id',
        'action_type',
        'description',
        'image_path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }
}
