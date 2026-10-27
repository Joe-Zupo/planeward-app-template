<?php

namespace App\Http\Middleware;

use App\Http\ApiError;
use App\Models\Installation;
use App\Planeward\Exceptions\TokenAudienceException;
use App\Planeward\Planeward;
use App\Planeward\Viewer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Verifies `Authorization: Bearer <viewer token>` against this deployment's
 * `PLANEWARD_APP_ID` and stores the verified {@see Viewer} on the request.
 *
 * - `viewer`: any member or staff viewer.
 * - `viewer:admin`: workspace staff only (`role === "admin"`), 403 otherwise.
 *
 * 401 for a missing or invalid token, or an uninstalled installation.
 */
class RequireViewer
{
    private const BEARER = '/^Bearer[ \t]+([^\s]+)[ \t]*$/i';

    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $token = self::bearerToken($request);
        if ($token === null) {
            return ApiError::unauthorized('Missing viewer token');
        }

        try {
            $viewer = Planeward::verifier()->verify($token);
        } catch (TokenAudienceException $error) {
            // App ids are not secret, and the message says exactly what to fix.
            return ApiError::unauthorized($error->getMessage());
        } catch (Throwable $error) {
            // Log the reason server-side only (expired, bad signature, JWKS
            // unreachable…); never echo verifier internals or the token.
            Log::warning('[planeward] viewer token rejected: '.class_basename($error).': '.$error->getMessage());

            return ApiError::unauthorized('Invalid viewer token');
        }

        if ($role === 'admin' && ! $viewer->isAdmin()) {
            return ApiError::make(403, 'Staff only');
        }

        // Viewer tokens live 5 minutes; a recorded `app.uninstalled` cuts them
        // off right away. Installs we never heard about (no webhook yet) stay allowed.
        $uninstalled = Installation::query()
            ->whereKey($viewer->installationId)
            ->whereNotNull('uninstalled_at')
            ->exists();
        if ($uninstalled) {
            return ApiError::unauthorized('This app was uninstalled from the community.');
        }

        $request->attributes->set(Viewer::ATTRIBUTE, $viewer);

        return $next($request);
    }

    /** The token from `Authorization: Bearer <token>`, or null. */
    public static function bearerToken(Request $request): ?string
    {
        $header = trim((string) $request->headers->get('Authorization'));

        return preg_match(self::BEARER, $header, $matches) ? $matches[1] : null;
    }
}
