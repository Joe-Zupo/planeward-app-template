<?php

namespace App\Http\Controllers;

use App\Http\Resources\InstallationResource;
use App\Http\Resources\WebhookDeliveryResource;
use App\Models\Installation;
use App\Models\WebhookDelivery;
use App\Planeward\Viewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Staff only: this installation's record and its recent webhook deliveries. */
    public function __invoke(Request $request): JsonResponse
    {
        $installationId = Viewer::from($request)->installationId;

        $installation = Installation::query()->find($installationId);
        $deliveries = WebhookDelivery::query()
            ->where('installation_id', $installationId)
            ->orderByDesc('received_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'installation' => $installation ? new InstallationResource($installation) : null,
                'deliveries' => WebhookDeliveryResource::collection($deliveries),
            ],
        ]);
    }
}
