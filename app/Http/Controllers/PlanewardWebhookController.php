<?php

namespace App\Http\Controllers;

use App\Planeward\Webhooks\WebhookProcessor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed webhooks from Planeward. The processor verifies the signature,
 * dedupes on `x-planeward-delivery`, and stores `app.installed` /
 * `app.uninstalled`.
 *
 * React to other events by listening for WebhookReceived, e.g. membership
 * changes (sent only to installations granted COMMUNITY_MEMBERS_READ):
 *
 *     Event::listen(function (WebhookReceived $received) {
 *         if (str_starts_with($received->envelope->event, 'community.membership.')) {
 *             // $received->envelope->installationId, $received->envelope->data …
 *         }
 *     });
 */
class PlanewardWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookProcessor $processor): Response
    {
        return $processor->handle($request);
    }
}
