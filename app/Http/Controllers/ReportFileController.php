<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves one of a report's uploaded attachments from the private `local` disk,
 * inline (?preview=1) or as a download. Gated by `can:view-submissions`; the
 * stored path comes from the database (never the request), so there is no
 * path-traversal surface — the only request input is the integer file index.
 */
class ReportFileController extends Controller
{
    public function __invoke(Request $request, Submission $submission, int $index): StreamedResponse
    {
        $path = $submission->files[$index] ?? abort(404);

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            basename($path),
            disposition: $request->boolean('preview') ? 'inline' : 'attachment',
        );
    }
}
