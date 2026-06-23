<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a receipt's attachment from the private `local` disk, inline (?preview=1)
 * or as a download. Readable by the worker who submitted it or by admin/office
 * (the `view-receipts` ability). The stored path comes from the database (never
 * the request), so there is no path-traversal surface.
 */
class ReceiptFileController extends Controller
{
    public function __invoke(Request $request, Receipt $receipt): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user->can('view-receipts') || $receipt->user_id === $user->id, 403);

        $path = $receipt->attachment ?? abort(404);

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            basename($path),
            disposition: $request->boolean('preview') ? 'inline' : 'attachment',
        );
    }
}
