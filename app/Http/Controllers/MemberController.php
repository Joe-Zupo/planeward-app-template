<?php

namespace App\Http\Controllers;

use App\Http\ApiError;
use App\Planeward\Exceptions\PlanewardApiException;
use App\Planeward\Planeward;
use App\Planeward\Viewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /** Staff only: community members via the Planeward API (COMMUNITY_MEMBERS_READ). */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            return response()->json(
                Planeward::forViewer(Viewer::from($request))->listMembers(['limit' => 50])
            );
        } catch (PlanewardApiException $error) {
            return ApiError::make($error->status, $error->getMessage());
        }
    }
}
