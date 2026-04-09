<?php

/**
 * Copyright © 2021-2026 The Galette Team
 *
 * This file is part of Galette OAuth2 plugin (https://galette-community.github.io/plugin-oauth2/).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette OAuth2 plugin. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace GaletteOAuth2\ResponseTypes;

use GaletteOAuth2\OIDC\IdTokenBuilder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

/**
 * OIDC-aware Bearer Token Response
 *
 * Extends the standard BearerTokenResponse to include an id_token
 * when the 'openid' scope is present in the access token.
 *
 * The id_token is a signed JWT containing user identity claims
 * according to OpenID Connect Core 1.0 specification.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#TokenResponse
 */
final class OidcBearerTokenResponse extends BearerTokenResponse
{
    private IdTokenBuilder $idTokenBuilder;
    private ?string $nonce = null;

    /**
     * Set the ID Token Builder
     *
     * @param IdTokenBuilder $idTokenBuilder The builder instance
     */
    public function setIdTokenBuilder(IdTokenBuilder $idTokenBuilder): void
    {
        $this->idTokenBuilder = $idTokenBuilder;
    }

    /**
     * Set the nonce from the authorization request
     *
     * The nonce is used to mitigate replay attacks and must be included
     * in the id_token if it was present in the original authorization request.
     *
     * @param string|null $nonce The nonce value
     */
    public function setNonce(?string $nonce): void
    {
        $this->nonce = $nonce;
    }

    /**
     * Add extra parameters to the token response
     *
     * When the 'openid' scope is present, generates and includes an id_token
     * JWT in the response. The id_token contains identity claims about the
     * authenticated user.
     *
     * @param AccessTokenEntityInterface $accessToken The access token entity
     *
     * @return array<string, mixed> Extra parameters to include in the response
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        $extraParams = parent::getExtraParams($accessToken);

        // Check if 'openid' scope is requested
        if (!$this->hasOpenIdScope($accessToken)) {
            return $extraParams;
        }

        // Generate and include the id_token
        if (isset($this->idTokenBuilder)) {
            $idToken = $this->idTokenBuilder->build($accessToken, $this->nonce);
            $extraParams['id_token'] = $idToken;
        }

        return $extraParams;
    }

    /**
     * Check if the access token includes the 'openid' scope
     *
     * @param AccessTokenEntityInterface $accessToken The access token entity
     *
     * @return bool True if 'openid' scope is present
     */
    private function hasOpenIdScope(AccessTokenEntityInterface $accessToken): bool
    {
        $scopes = $accessToken->getScopes();

        foreach ($scopes as $scope) {
            if ($scope instanceof ScopeEntityInterface && $scope->getIdentifier() === 'openid') {
                return true;
            }
        }

        return false;
    }
}
