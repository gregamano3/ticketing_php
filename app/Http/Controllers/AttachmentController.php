<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function show(Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        $inline = str_starts_with((string) $attachment->mime, 'image/') || $attachment->mime === 'application/pdf';
        $disk = Storage::disk($attachment->disk);

        return $inline
            ? $disk->response($attachment->path, $attachment->original_name)
            : $disk->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $attachment);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }
}
