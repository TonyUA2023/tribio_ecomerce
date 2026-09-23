<?php

namespace App\Services\Attachments;

use App\Models\Attachment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Private files for custom orders. The type is decided from the file's content (finfo),
 * never from its name or the browser's claim; SVG is refused outright (it can carry
 * script). Files land on the private `local` disk and are only reachable through
 * Attachment::signedUrl().
 */
class AttachmentService
{
    /** Content type → stored extension. */
    public const ALLOWED = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public const MAX_KILOBYTES = 10240;

    public const DISK = 'local';

    public function store(UploadedFile $file, Store $store, ?User $uploader = null): Attachment
    {
        $mime = $file->getMimeType();
        if (!$file->isValid() || !isset(self::ALLOWED[$mime])) {
            throw ValidationException::withMessages(['file' => 'Sube una imagen PNG, JPG o WEBP, o un PDF.']);
        }
        if ($file->getSize() > self::MAX_KILOBYTES * 1024) {
            throw ValidationException::withMessages(['file' => 'El archivo pesa más de 10 MB.']);
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages(['file' => 'La imagen está dañada o no se puede leer.']);
        }

        $directory = "attachments/{$store->id}/" . now()->format('Y/m');
        $path = Storage::disk(self::DISK)->putFileAs($directory, $file, Str::uuid() . '.' . self::ALLOWED[$mime]);

        return Attachment::create([
            'store_id' => $store->id,
            'token' => Str::random(48),
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
            'mime' => $mime,
            'size' => $file->getSize(),
            'uploaded_by' => $uploader?->id,
        ]);
    }

    /** Links an upload to what it belongs to. Only uploads of the same store can be claimed. */
    public function attach(Attachment $attachment, Model $owner, Store $store): Attachment
    {
        if ((int) $attachment->store_id !== (int) $store->id) {
            throw new \DomainException('El archivo no pertenece a esta tienda.');
        }
        $attachment->attachable()->associate($owner)->save();

        return $attachment;
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    /** Removes uploads that were never linked to anything (abandoned carts). Returns how many. */
    public function pruneUnlinked(Carbon $olderThan): int
    {
        $count = 0;
        Attachment::whereNull('attachable_type')->where('created_at', '<', $olderThan)
            ->chunkById(200, function ($attachments) use (&$count) {
                foreach ($attachments as $attachment) {
                    $this->delete($attachment);
                    $count++;
                }
            });

        return $count;
    }
}
