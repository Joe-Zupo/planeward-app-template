<?php

namespace App\Planeward\Exceptions;

use RuntimeException;

/**
 * The token is valid but was issued for another app. Usually the backend's
 * `PLANEWARD_APP_ID` does not match the app that the iframe belongs to.
 */
class TokenAudienceException extends RuntimeException
{
    public function __construct(
        /** The `aud` claim from the token. */
        public readonly string $tokenAudience,
        /** The app id this backend verified against. */
        public readonly string $expectedAppId,
    ) {
        parent::__construct(
            "Token is for app \"{$tokenAudience}\", but this backend expects \"{$expectedAppId}\". Check PLANEWARD_APP_ID."
        );
    }
}
