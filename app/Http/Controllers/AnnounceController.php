<?php

namespace App\Http\Controllers;

use App\Http\ApiError;
use App\Planeward\Exceptions\PlanewardApiException;
use App\Planeward\Planeward;
use App\Planeward\Viewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnnounceController extends Controller
{
    /**
     * Staff only: post a message as the app (COMMUNITY_MESSAGES_WRITE). The
     * target channel is the one deliberate client-chosen id: the installation
     * still comes from the claims, and Planeward enforces which channels the
     * app may post to.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'channelId' => ['required', 'string'],
            'body' => ['required', 'string', 'max:4000'],
        ], [
            'channelId.required' => 'Enter a channel id.',
            'body.required' => 'Write a message.',
        ]);
        if ($validator->fails()) {
            return ApiError::make(400, $validator->errors()->first());
        }
        $input = $validator->validated();

        try {
            $message = Planeward::forViewer(Viewer::from($request))
                ->createMessage($input['channelId'], ['body' => $input['body']]);

            return response()->json($message, 201);
        } catch (PlanewardApiException $error) {
            return ApiError::make($error->status, $error->getMessage());
        }
    }
}
