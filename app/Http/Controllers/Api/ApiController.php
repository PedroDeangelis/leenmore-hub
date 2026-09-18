<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiCatalog;
use App\Support\ApiData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The read-only REST API. Thin over {@see ApiData}: validate the query params
 * declared in {@see ApiCatalog}, run the query, return JSON. Every response is
 * already masked by ApiData, so there is no un-redacted path through here.
 *
 * Data comes from Supabase, not a local database, so {project} is a plain id
 * rather than a bound model: a project that is not there returns 404 here
 * instead of being caught by route binding.
 */
class ApiController extends Controller
{
    public function projects(Request $request): JsonResponse
    {
        $params = $request->validate(ApiCatalog::queryRules('projects'));

        return response()->json(ApiData::projects($params));
    }

    public function project(int $project): JsonResponse
    {
        return response()->json(self::orNotFound(ApiData::project($project)));
    }

    public function shareholders(Request $request, int $project): JsonResponse
    {
        $params = $request->validate(ApiCatalog::queryRules('shareholders'));

        return response()->json(self::orNotFound(ApiData::shareholders($project, $params)));
    }

    public function submissions(Request $request): JsonResponse
    {
        $params = $request->validate(ApiCatalog::queryRules('submissions'));

        return response()->json(ApiData::submissions($params));
    }

    public function activityData(Request $request, int $project): JsonResponse
    {
        $params = $request->validate(ApiCatalog::queryRules('activity-data'));

        return response()->json(self::orNotFound(ApiData::activityData($project, $params)));
    }

    public function receipts(Request $request): JsonResponse
    {
        $params = $request->validate(ApiCatalog::queryRules('receipts'));

        return response()->json(ApiData::receipts($params));
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    private static function orNotFound(?array $data): array
    {
        abort_if($data === null, 404, 'Project not found.');

        return $data;
    }
}
