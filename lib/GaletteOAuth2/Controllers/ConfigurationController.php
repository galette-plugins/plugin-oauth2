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

/**
 * OpenID Connect Configuration Controller
 *
 * Provides the .well-known/openid-configuration and JWK endpoints.
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Florian Hatat <github@hatat.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

namespace GaletteOAuth2\Controllers;

use DI\Attribute\Inject;
use Galette\Controllers\AbstractPluginController;
use GaletteOAuth2\Repositories\ScopeRepository;
use GaletteOAuth2\Repositories\ClaimRepository;
use GaletteOAuth2\Tools\Config;
use GaletteOAuth2\Tools\Debug as Debug;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use League\OAuth2\Server\CryptKey;

final class ConfigurationController extends AbstractPluginController
{
    #[Inject("Plugin Galette OAuth2")]
    protected array $module_info;
    protected $container;
    protected $config;

    // constructor receives container instance
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $this->container->get(Config::class);
        parent::__construct($container);
    }

    public function openid(Request $request, Response $response): Response
    {
        Debug::logRequest('openid_configuration()', $request);
        $pluginBasePath = str_replace('/.well-known/openid-configuration', '', $this->routeparser->urlFor(OAUTH2_PREFIX . '_openid_configuration'));
        $issuer = 'https://' . $_SERVER['HTTP_HOST'] . $pluginBasePath;

        $data = [
            'issuer' => $issuer,
            'authorization_endpoint' => 'https://' . $_SERVER['HTTP_HOST'] . $this->routeparser->urlFor(OAUTH2_PREFIX . '_authorize'),
            'jwks_uri' => 'https://' . $_SERVER['HTTP_HOST'] . $this->routeparser->urlFor(OAUTH2_PREFIX . '_json_web_key'),
            'token_endpoint' => 'https://' . $_SERVER['HTTP_HOST'] . $this->routeparser->urlFor(OAUTH2_PREFIX . '_token'),
            'response_types_supported' => ['code', 'id_token', 'token id_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => array_keys(ScopeRepository::getScopes()),
            'claims_supported' => ClaimRepository::getAllClaims(),
        ];
        $response->getBody()->write(\json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withStatus(200)->withHeader('Content-type', 'application/json');
    }

    public function oauth_server(Request $request, Response $response): Response
    {
        Debug::logRequest('oauth_server_configuration()', $request);
        $pluginBasePath = str_replace('/.well-known/oauth-authorization-server', '', $this->routeparser->urlFor(OAUTH2_PREFIX . '_oauth_server_configuration'));
        $issuer = 'https://' . $_SERVER['HTTP_HOST'] . $pluginBasePath;

        $data = [
            'issuer' => $issuer,
            'authorization_endpoint' => 'https://' . $_SERVER['HTTP_HOST'] . $this->routeparser->urlFor(OAUTH2_PREFIX . '_authorize'),
            'token_endpoint' => 'https://' . $_SERVER['HTTP_HOST'] . $this->routeparser->urlFor(OAUTH2_PREFIX . '_token'),
            'jwks_uri' => 'https://' . $_SERVER['HTTP_HOST'] . $this->routeparser->urlFor(OAUTH2_PREFIX . '_json_web_key'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'password', 'client_credentials', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'scopes_supported' => array_keys(ScopeRepository::getScopes()),
        ];
        $response->getBody()->write(\json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withStatus(200)->withHeader('Content-type', 'application/json');
    }

    public function json_web_key(Request $request, Response $response): Response
    {
        $key = new CryptKey('file://' . OAUTH2_CONFIGPATH . '/public.key');
        $openssl_key = \openssl_pkey_get_public($key->getKeyPath());
        $key_details = \openssl_pkey_get_details($openssl_key);
        $key_data = ['use' => 'sig', 'kid' => 'signing key'];
        if ($key_details['type'] == OPENSSL_KEYTYPE_RSA) {
            $key_data['kty'] = 'RSA';
            $key_data['n'] = rtrim(strtr(base64_encode($key_details['rsa']['n']), '+/', '-_'), '=');
            $key_data['e'] = rtrim(strtr(base64_encode($key_details['rsa']['e']), '+/', '-_'), '=');
        } elseif ($key_details['type'] == OPENSSL_KEYTYPE_EC) {
            $key_data['kty'] = 'EC';
            $key_data['crv'] = $key_details['ec']['curve_name'];
            $key_data['x'] = rtrim(strtr(base64_encode($key_details['ec']['x']), '+/', '-_'), '=');
            $key_data['y'] = rtrim(strtr(base64_encode($key_details['ec']['y']), '+/', '-_'), '=');
        }
        $data = ['keys' => [$key_data]];
        $response->getBody()->write(\json_encode($data));
        return $response->withStatus(200)->withHeader('Content-type', 'application/jwk-set+json');
    }
}
