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

namespace GaletteOAuth2\OIDC;

use DateTimeImmutable;
use DI\Container;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * ID Token Builder for OpenID Connect
 *
 * Generates JWT id_tokens according to OIDC Core 1.0 specification.
 * Uses lcobucci/jwt for JWT creation and RS256 for signing.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#IDToken
 */
final class IdTokenBuilder
{
    private Configuration $jwtConfig;
    private ClaimExtractor $claimExtractor;
    private string $issuer;

    /**
     * Constructor
     *
     * @param Container $container      DI Container instance
     * @param string    $privateKeyPath Path to the RSA private key file
     * @param string    $issuer         The issuer identifier (base URL of the OAuth2 server)
     */
    public function __construct(
        Container $container,
        string $privateKeyPath,
        string $issuer
    ) {
        $this->claimExtractor = new ClaimExtractor($container);
        $this->issuer = $issuer;

        // Configure JWT with RS256 signer and the private key
        $this->jwtConfig = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::file($privateKeyPath),
            InMemory::empty() // Public key not needed for signing
        );
    }

    /**
     * Build an ID Token for the given access token
     *
     * The ID Token contains standard OIDC claims plus user profile claims
     * based on the granted scopes.
     *
     * @param AccessTokenEntityInterface $accessToken The access token entity
     * @param string|null                $nonce       The nonce from the authorization request (replay protection)
     *
     * @return string The signed JWT id_token
     */
    public function build(
        AccessTokenEntityInterface $accessToken,
        ?string $nonce = null
    ): string {
        $userIdentifier = $accessToken->getUserIdentifier();
        $clientId = $accessToken->getClient()->getIdentifier();
        $scopes = $this->getScopeIdentifiers($accessToken->getScopes());

        $now = new DateTimeImmutable();
        $expiresAt = $accessToken->getExpiryDateTime();

        // Start building the JWT with required OIDC claims
        $builder = $this->jwtConfig->builder()
            ->issuedBy($this->issuer)                    // iss: Issuer Identifier
            ->permittedFor($clientId)                     // aud: Audience (client_id)
            ->relatedTo((string)$userIdentifier)          // sub: Subject Identifier (user id)
            ->issuedAt($now)                              // iat: Issued At timestamp
            ->expiresAt($expiresAt)                       // exp: Expiration timestamp
            ->identifiedBy(bin2hex(random_bytes(16)));    // jti: Unique token identifier

        // Add nonce if provided (required when nonce was in auth request)
        if ($nonce !== null) {
            $builder = $builder->withClaim('nonce', $nonce);
        }

        // Add auth_time claim (time of user authentication)
        $builder = $builder->withClaim('auth_time', $now->getTimestamp());

        // Add user claims based on scopes using the shared ClaimExtractor
        // Don't include 'sub' as it's already set via relatedTo()
        $userClaims = $this->claimExtractor->extract($userIdentifier, $scopes, false);
        foreach ($userClaims as $claim => $value) {
            $builder = $builder->withClaim($claim, $value);
        }

        // Sign and return the token
        return $builder
            ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey())
            ->toString();
    }


    /**
     * Extract scope identifiers from scope entities
     *
     * @param ScopeEntityInterface[] $scopes Array of scope entities
     *
     * @return string[] Array of scope identifier strings
     */
    private function getScopeIdentifiers(array $scopes): array
    {
        return array_map(
            fn(ScopeEntityInterface $scope) => $scope->getIdentifier(),
            $scopes
        );
    }
}
