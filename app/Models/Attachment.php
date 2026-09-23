<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/** A private file tied to a store (and, once linked, to an order item, quote…). See AttachmentService. */
class Attachment extends Model
{
    protected $fillable = [
        'store_id', 'token', 'disk', 'path', 'original_name', 'mime', 'size', 'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    protected $hidden = ['path', 'disk'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function attachable()
    {
        return $this->morphTo();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** The only way a file leaves the private disk: a link that expires. */
    public function signedUrl(int $minutes = 60 * 24 * 7, bool $download = false): string
    {
        return URL::temporarySignedRoute('attachments.show', now()->addMinutes($minutes), array_filter([
            'attachment' => $this->token,
            'descargar' => $download ? 1 : null,
        ]));
    }
}
