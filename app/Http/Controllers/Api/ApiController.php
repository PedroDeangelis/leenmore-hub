<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\ApiCatalog;
use App\Support\ApiData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The read-only REST API. Thin over {@see ApiData}: validate the query params
 * declared in {@see ApiCatalog}, run the query, return JSON. Every response is
 * already masked by ApiData, so there is no un-redacted path through here.
 *
 * The project-scoped routes bind {project} to the model, which applies the
 * Project global scope — deleted projects 404 rather than leaking.
 */
class ApiController extends Controller
{
    public function projects(Request $request): JsonResponse
    {
        $params = $request->validate(ApiCatalog::queryRules('projects'));

        return response()->json(ApiData::projects($params));
    }

    public function project(Project $project): JsonResponse
    {
        return response()->json(ApiData::project($project->id));
    }

    public function shareholders(Request $request, Project $project): JsonResponse
    {
        $params = $request->validate(ApiCatalog::queryRules('shareholders'));

        return response()->json(ApiData::shareholders($project->id, $params));
    }

    public function submissions(Request $request): JsonResponse
    {
        $params = $request->validate(ApiCatalog::queryRules('submissions'));

        return response()->json(ApiData::submissions($params));
    }

    public function activityData(Request $request, Project $project): JsonResponse
    {
        $params = $request->validate(ApiCatalog::queryRules('activity-data'));

        return response()->json(ApiData::activityData($project->id, $params));
    }

    public function receipts(Request $request): JsonResponse
    {
        $params = $request->validate(ApiCatalog::queryRules('receipts'));

        return response()->json(ApiData::receipts($params));
    }
}
