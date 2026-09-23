<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\Attachments\AttachmentService;
use App\Services\Storefront\StorefrontStoreResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Buyer upload from a storefront (e.g. the logo to embroider). Only stores that sell
     * made-to-order accept files; the returned token is what the cart line carries.
     */
    public function store(Request $request, string $slug, AttachmentService $attachments): JsonResponse
    {
        $store = app(StorefrontStoreResolver::class)->resolve($slug);
        abort_unless($store->made_to_order_enabled, 404);

        $request->validate([
            'file' => ['required', 'file', 'max:' . AttachmentService::MAX_KILOBYTES, 'mimes:png,jpg,jpeg,webp,pdf'],
        ]);

        $attachment = $attachments->store($request->file('file'), $store, Auth::user());

        return response()->json([
            'token' => $attachment->token,
            'name' => $attachment->original_name,
            'mime' => $attachment->mime,
            'size' => $attachment->size,
            'preview_url' => $attachment->signedUrl(120),
        ], 201);
    }

    /** Serves a private file. Reached only through a valid signed URL (route middleware). */
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        $disposition = $request->boolean('descargar') || !$attachment->isImage() ? 'attachment' : 'inline';

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $disposition);
    }
}
