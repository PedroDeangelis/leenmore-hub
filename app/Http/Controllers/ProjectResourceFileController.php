<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\ProjectResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a project resource's uploaded file from the private `local` disk, inline
 * (?preview=1) or as a download. Readable by admin/office (`manage-resources`) or by
 * a worker assigned to the resource's (published) project. The stored path comes
 * from the database, so there is no path-traversal surface.
 */
class ProjectResourceFileController extends Controller
{
    public function __invoke(Request $request, ProjectResource $resource): StreamedResponse
    {
        $user = $request->user();

        $allowed = $user->can('manage-resources') || (
            $resource->project->status === ProjectStatus::Publish
            && $resource->project->shareholders()
                ->whereHas('workers', fn (Builder $q) => $q->whereKey($user->id))
                ->exists()
        );

        abort_unless($allowed, 403);

        $path = $resource->file_path ?? abort(404);

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            $resource->file_name ?? basename($path),
            disposition: $request->boolean('preview') ? 'inline' : 'attachment',
        );
    }
}
