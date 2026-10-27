<?php

namespace App\Planeward;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * Verified viewer token claims (RS256, 5-minute expiry). Every property has
 * been checked against Planeward's JWKS, so it is safe to scope data by
 * `installationId` / `channelId` / `sub`.
 */
final readonly class Viewer
{
    public function __construct(
        /** Planeward app URL. */
        public string $iss,
        /** App id. */
        public string $aud,
        /** Community membership id (member) or workspace membership id (staff). */
        public string $sub,
        public int $iat,
        public int $exp,
        public string $workspaceId,
        public string $installationId,
        /** Set for `view = "experience"`. */
        public ?string $channelId,
        /** "experience" or "dashboard". */
        public string $view,
        /** "member" or "admin". */
        public string $role,
        public string $name,
        public ?string $avatar,
    ) {}

    /** Request attribute the `viewer` middleware stores the verified viewer under. */
    public const ATTRIBUTE = 'planeward.viewer';

    /** The viewer the `viewer` middleware verified for this request. */
    public static function from(Request $request): self
    {
        $viewer = $request->attributes->get(self::ATTRIBUTE);
        if (! $viewer instanceof self) {
            throw new RuntimeException('No verified viewer on this request. Add the `viewer` middleware to the route.');
        }

        return $viewer;
    }

    /** Null when a required claim is missing or has the wrong type. */
    public static function fromClaims(array $claims): ?self
    {
        $string = fn (string $key) => isset($claims[$key]) && is_string($claims[$key]);
        $nullableString = fn (string $key) => array_key_exists($key, $claims)
            && ($claims[$key] === null || is_string($claims[$key]));
        $number = fn (string $key) => isset($claims[$key]) && is_numeric($claims[$key]) && ! is_string($claims[$key]);

        $valid = $string('iss') && $string('aud') && $string('sub')
            && $number('iat') && $number('exp')
            && $string('workspaceId') && $string('installationId')
            && $nullableString('channelId')
            && in_array($claims['view'] ?? null, ['experience', 'dashboard'], true)
            && in_array($claims['role'] ?? null, ['member', 'admin'], true)
            && $string('name') && $nullableString('avatar');

        if (! $valid) {
            return null;
        }

        return new self(
            iss: $claims['iss'],
            aud: $claims['aud'],
            sub: $claims['sub'],
            iat: (int) $claims['iat'],
            exp: (int) $claims['exp'],
            workspaceId: $claims['workspaceId'],
            installationId: $claims['installationId'],
            channelId: $claims['channelId'],
            view: $claims['view'],
            role: $claims['role'],
            name: $claims['name'],
            avatar: $claims['avatar'],
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
