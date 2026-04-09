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

namespace GaletteOAuth2\Grant;

use DateInterval;
use GaletteOAuth2\ResponseTypes\OidcBearerTokenResponse;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * OIDC-aware Authorization Code Grant
 *
 * Extends the standard AuthCodeGrant to support OpenID Connect:
 * - Passes the nonce to the token response for id_token generation
 *
 * The nonce is an optional parameter for OIDC - when present in the
 * authorization request, it must be included in the id_token to
 * prevent replay attacks.
 *
 * Note: The nonce should ideally be stored in the authorization code
 * payload. Since we can't easily modify the parent's encrypt/decrypt
 * flow, clients can pass the nonce again in the token request.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#AuthRequest
 */
class OidcAuthCodeGrant extends AuthCodeGrant
{
    /**
     * @param AuthCodeRepositoryInterface     $authCodeRepository
     * @param RefreshTokenRepositoryInterface $refreshTokenRepository
     * @param DateInterval                    $authCodeTTL
     */
    public function __construct(
        AuthCodeRepositoryInterface $authCodeRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        DateInterval $authCodeTTL
    ) {
        parent::__construct($authCodeRepository, $refreshTokenRepository, $authCodeTTL);
    }

    /**
     * {@inheritdoc}
     *
     * Extends token response to pass nonce to OidcBearerTokenResponse
     * for id_token generation.
     */
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        // Call parent to handle standard token generation
        $responseType = parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);

        // If this is an OIDC response type, we need to set the nonce
        if ($responseType instanceof OidcBearerTokenResponse) {
            // Get nonce from the token request body
            // Note: Ideally this would come from the auth code payload,
            // but we pass it through the token request for simplicity
            $params = (array)$request->getParsedBody();
            $nonce = $params['nonce'] ?? null;
            if ($nonce !== null) {
                $responseType->setNonce($nonce);
            }
        }

        return $responseType;
    }
}
