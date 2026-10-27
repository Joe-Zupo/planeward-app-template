<?php

namespace App\Http\Controllers;

use App\Http\ApiError;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Planeward\Viewer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/** The Board example. Scoped by verified claims only. */
class PostController extends Controller
{
    /** Latest posts in the viewer's channel. */
    public function index(Request $request): Response
    {
        $viewer = Viewer::from($request);
        if ($viewer->channelId === null) {
            return ApiError::make(400, 'The board is only available in a channel.');
        }

        $posts = Post::query()
            ->where('installation_id', $viewer->installationId)
            ->where('channel_id', $viewer->channelId)
            ->latest()
            ->limit(50)
            ->get();

        return PostResource::collection($posts)->response();
    }

    public function store(Request $request): Response
    {
        $viewer = Viewer::from($request);
        if ($viewer->channelId === null) {
            return ApiError::make(400, 'The board is only available in a channel.');
        }

        $validator = Validator::make($request->all(), [
            'body' => ['required', 'string', 'max:500'],
        ], [
            'body.required' => 'Write something first.',
        ]);
        if ($validator->fails()) {
            return ApiError::make(400, $validator->errors()->first());
        }

        // Installation, channel and author come from the verified token, never the body.
        $post = Post::query()->create([
            'installation_id' => $viewer->installationId,
            'channel_id' => $viewer->channelId,
            'author_id' => $viewer->sub,
            'author_name' => $viewer->name,
            'body' => $validator->validated()['body'],
        ]);

        return (new PostResource($post->refresh()))->response()->setStatusCode(201);
    }
}
