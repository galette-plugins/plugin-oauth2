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

namespace GaletteOAuth2\Controllers;

use DI\Attribute\Inject;
use DI\Container;
use Galette\Controllers\AbstractPluginController;
use GaletteOAuth2\Tools\Config;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

/**
 * Discovery Controller for OAuth2 and OpenID Connect
 *
 * Implements the well-known discovery endpoints:
 * - /.well-known/openid-configuration (OpenID Connect Discovery)
 * - /.well-known/oauth-authorization-server (RFC 8414)
 * - /.well-known/jwks.json (JSON Web Key Set)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @see https://openid.net/specs/openid-connect-discovery-1_0.html
 * @see https://www.rfc-editor.org/rfc/rfc8414
 * @see https://www.rfc-editor.org/rfc/rfc7517
 */
final class DiscoveryController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette OAuth2")]
    protected array $module_info;

    protected Container $container;
    protected Config $config;

    /**
     * Default constructor
     *
     * @param Container $container Container instance
     *
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = $container->get(Config::class);
        parent::__construct($container);
    }

    /**
     * OpenID Connect Discovery endpoint
     *
     * Returns the OpenID Provider Configuration Information document.
     * This endpoint allows clients to discover the OAuth2/OIDC server
     * configuration automatically.
     *
     * @see https://openid.net/specs/openid-connect-discovery-1_0.html#ProviderConfig
     */
    public function openidConfiguration(Request $request, Response $response): ResponseInterface
    {
        $metadata = $this->buildMetadata($request, true);

        $response->getBody()->write(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withStatus(200);
    }

    /**
     * OAuth 2.0 Authorization Server Metadata endpoint (RFC 8414)
     *
     * Returns the authorization server metadata. This is similar to
     * the OIDC discovery document but focused on OAuth2 parameters.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8414
     */
    public function oauthServerMetadata(Request $request, Response $response): ResponseInterface
    {
        $metadata = $this->buildMetadata($request, false);

        $response->getBody()->write(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withStatus(200);
    }

    /**
     * JSON Web Key Set endpoint
     *
     * Returns the public keys used to verify signatures.
     * Clients use these keys to validate id_token signatures.
     *
     * @see https://www.rfc-editor.org/rfc/rfc7517
     */
    public function jwks(Request $request, Response $response): ResponseInterface
    {
        $jwks = $this->buildJwks();

        $response->getBody()->write(json_encode($jwks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=86400')
            ->withStatus(200);
    }

    /**
     * Build the metadata document
     *
     * @param Request $request      The HTTP request
     * @param bool    $includeOidc  Whether to include OIDC-specific fields
     *
     * @return array<string, mixed> The metadata array
     */
    private function buildMetadata(Request $request, bool $includeOidc): array
    {
        $baseUrl = $this->getBaseUrl($request);
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        // Get full URLs for endpoints
        $authorizationEndpoint = $routeParser->fullUrlFor(
            $request->getUri(),
            OAUTH2_PREFIX . '_authorize'
        );
        $tokenEndpoint = $routeParser->fullUrlFor(
            $request->getUri(),
            OAUTH2_PREFIX . '_token'
        );
        $userinfoEndpoint = $routeParser->fullUrlFor(
            $request->getUri(),
            OAUTH2_PREFIX . '_userinfo'
        );
        $jwksUri = $routeParser->fullUrlFor(
            $request->getUri(),
            OAUTH2_PREFIX . '_jwks'
        );

        // Base OAuth2 metadata (RFC 8414)
        $metadata = [
            'issuer' => $baseUrl,
            'authorization_endpoint' => $authorizationEndpoint,
            'token_endpoint' => $tokenEndpoint,
            'jwks_uri' => $jwksUri,
            'response_types_supported' => [
                'code',
            ],
            'grant_types_supported' => [
                'authorization_code',
                'refresh_token',
                'client_credentials',
                'password', // Resource Owner Password Credentials (if needed)
            ],
            'token_endpoint_auth_methods_supported' => [
                'client_secret_basic',
                'client_secret_post',
            ],
            'scopes_supported' => $this->getSupportedScopes($includeOidc),
            'code_challenge_methods_supported' => [
                'plain',
                'S256',
            ],
            'service_documentation' => 'https://galette-community.github.io/plugin-oauth2/',
        ];

        // Add OIDC-specific fields
        if ($includeOidc) {
            $metadata['userinfo_endpoint'] = $userinfoEndpoint;
            $metadata['subject_types_supported'] = ['public'];
            $metadata['id_token_signing_alg_values_supported'] = ['RS256'];
            $metadata['claims_supported'] = $this->getSupportedClaims();

            // OIDC response types (excluding implicit flow)
            $metadata['response_types_supported'] = [
                'code',
            ];

            // Response modes
            $metadata['response_modes_supported'] = [
                'query',
            ];

            // Claim types
            $metadata['claim_types_supported'] = ['normal'];

            // Display values supported for authorization UI
            $metadata['display_values_supported'] = ['page'];
        }

        return $metadata;
    }

    /**
     * Build the JWKS (JSON Web Key Set)
     *
     * Reads the public key and converts it to JWK format.
     *
     * @return array<string, mixed> The JWKS structure
     */
    private function buildJwks(): array
    {
        $publicKeyPath = OAUTH2_CONFIGPATH . '/public.key';

        if (!file_exists($publicKeyPath)) {
            return ['keys' => []];
        }

        $publicKey = file_get_contents($publicKeyPath);
        if ($publicKey === false) {
            return ['keys' => []];
        }

        $keyResource = openssl_pkey_get_public($publicKey);
        if ($keyResource === false) {
            return ['keys' => []];
        }

        $keyDetails = openssl_pkey_get_details($keyResource);
        if ($keyDetails === false || $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            return ['keys' => []];
        }

        // Convert RSA key components to base64url encoding
        $jwk = [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64UrlEncode($keyDetails['rsa']['n']),
            'e' => $this->base64UrlEncode($keyDetails['rsa']['e']),
        ];

        // Generate key ID from public key hash
        $jwk['kid'] = $this->generateKeyId($publicKey);

        return ['keys' => [$jwk]];
    }

    /**
     * Get the list of supported scopes
     *
     * @param bool $includeOidc Whether to include OIDC scopes
     *
     * @return string[] List of scope identifiers
     */
    private function getSupportedScopes(bool $includeOidc): array
    {
        // Base Galette OAuth2 scopes
        $scopes = [
            'member',
            'member:personal',
            'member:localization',
            'member:localization:precise',
            'member:phones',
            'member:socials',
            'member:groups',
            'member:due_date',
        ];

        // Add OIDC scopes
        if ($includeOidc) {
            array_unshift($scopes, 'openid', 'profile', 'email', 'address', 'phone');
        }

        return $scopes;
    }

    /**
     * Get the list of supported OIDC claims
     *
     * @return string[] List of claim names
     */
    private function getSupportedClaims(): array
    {
        return [
            // Standard OIDC claims
            'sub',
            'name',
            'family_name',
            'given_name',
            'nickname',
            'preferred_username',
            'locale',
            'updated_at',
            'gender',
            'birthdate',
            'email',
            'email_verified',
            'address',
            'phone_number',
            'phone_number_verified',
            // Galette-specific claims
            'groups',
            'due_date',
            'socials',
            'job',
            'birthplace',
            'gpgid',
        ];
    }

    /**
     * Get the base URL of the OAuth2 server
     *
     * @param Request $request The HTTP request
     *
     * @return string The base URL (issuer identifier)
     */
    private function getBaseUrl(Request $request): string
    {
        $uri = $request->getUri();

        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();

        $port = $uri->getPort();
        if ($port !== null && $port !== 80 && $port !== 443) {
            $baseUrl .= ':' . $port;
        }

        // Include path up to the plugin prefix
        $path = $uri->getPath();
        $prefixPos = strpos($path, '/.well-known');
        if ($prefixPos !== false) {
            $pluginPath = substr($path, 0, $prefixPos);
            $baseUrl .= $pluginPath;
        }

        return $baseUrl;
    }

    /**
     * Base64url encode (RFC 7515)
     *
     * @param string $data Binary data to encode
     *
     * @return string Base64url-encoded string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Generate a key ID from the public key
     *
     * Uses SHA-256 hash of the public key, truncated to 8 bytes.
     *
     * @param string $publicKey The PEM-encoded public key
     *
     * @return string The key ID
     */
    private function generateKeyId(string $publicKey): string
    {
        return substr(hash('sha256', $publicKey), 0, 16);
    }
}
