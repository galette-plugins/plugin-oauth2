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
 * Dependencies
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

use Defuse\Crypto\Key;
use GaletteOAuth2\Grant\OidcAuthCodeGrant;
use GaletteOAuth2\OIDC\ClaimExtractor;
use GaletteOAuth2\OIDC\IdTokenBuilder;
use GaletteOAuth2\Repositories\AccessTokenRepository;
use GaletteOAuth2\Repositories\AuthCodeRepository;
use GaletteOAuth2\Repositories\ClientRepository;
use GaletteOAuth2\Repositories\RefreshTokenRepository;
use GaletteOAuth2\Repositories\ScopeRepository;
use GaletteOAuth2\Repositories\UserRepository;
use GaletteOAuth2\ResponseTypes\OidcBearerTokenResponse;
use GaletteOAuth2\Tools\Config;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use Psr\Container\ContainerInterface;
use RKA\SessionMiddleware;
use Slim\Flash\Messages;

$container = $app->getContainer();

//$app->add($session);
$container->set(
    'oauth_session',
    function (ContainerInterface $container) {
        $session_name = PREFIX_DB . '_' . NAME_DB . '_' . str_replace('.', '_', GALETTE_VERSION);
        $session_name = 'galette_oauth_' . $session_name;
        $session = new SessionMiddleware([
            'name'      => $session_name,
            'lifetime'  => GALETTE_TIMEOUT
        ]);

        $galette_sid = session_id();
        session_write_close();
        session_id('galette-oauth-' . $galette_sid);
        $session->start();

        $container->get(Messages::class)->__construct($_SESSION);
        return new \RKA\Session();
    }
);

$container->set(
    Config::class,
    static function (ContainerInterface $container) {
        $conf = new GaletteOAuth2\Tools\Config(OAUTH2_CONFIGPATH . '/config.yml');

        do {
            $key = $conf->key();
            $current = $conf->current();
            if (isset($current['options'])) {
                Analog::log(
                    '"options" is deprecated, please use "authorize" instead for ' . $key,
                    Analog::WARNING
                );

                if (!isset($current['authorize'])) {
                    $conf->set($key . '.authorize', $current['options']);
                }
                $conf->remove($key . '.options');
            }
        } while ($conf->next());

        return $conf;
    },
);

$container->set(
    AuthorizationServer::class,
    function (ContainerInterface $container) {
        include OAUTH2_CONFIGPATH . '/encryption-key.php';

        // Get the issuer URL for OIDC
        $config = $container->get(Config::class);
        $issuer = $config->get('global.issuer', '');

        // Create OIDC-aware response type with IdTokenBuilder
        $privateKeyPath = 'file://' . OAUTH2_CONFIGPATH . '/private.key';
        $idTokenBuilder = new IdTokenBuilder(
            $container,
            OAUTH2_CONFIGPATH . '/private.key',
            $issuer
        );

        $responseType = new OidcBearerTokenResponse();
        $responseType->setIdTokenBuilder($idTokenBuilder);

        // Setup the authorization server with OIDC response type
        $server = new AuthorizationServer(
        // instance of ClientRepositoryInterface
            new ClientRepository($container),
            // instance of AccessTokenRepositoryInterface
            new AccessTokenRepository(),
            // instance of ScopeRepositoryInterface
            new ScopeRepository(),
            // path to private key
            $privateKeyPath,
            // encryption key
            Key::loadFromAsciiSafeString($encryptionKey),
            // OIDC-aware response type
            $responseType,
        );

        $refreshTokenRepository = new RefreshTokenRepository();

        // Use OIDC-aware AuthCodeGrant
        $grant = new OidcAuthCodeGrant(
            new AuthCodeRepository(),
            // instance of RefreshTokenRepositoryInterface
            $refreshTokenRepository,
            new DateInterval('PT10M'),
        );

        // Enable the authorization code grant on the server
        // with a token TTL of 1 hour
        $server->enableGrantType(
            $grant,
            // access tokens will expire after 1 hour
            new DateInterval('PT1H'),
        );

        $rt_grant = new RefreshTokenGrant($refreshTokenRepository);
        // new refresh tokens will expire after 1 month
        $rt_grant->setRefreshTokenTTL(new DateInterval('P1M'));

        // Enable the refresh token grant on the server
        $server->enableGrantType(
            $rt_grant,
            // new access tokens will expire after an hour
            new DateInterval('PT1H'),
        );

        //--
        $userRepository = new UserRepository($container); // instance of UserRepositoryInterface
        $grant = new \League\OAuth2\Server\Grant\PasswordGrant(
            $userRepository,
            $refreshTokenRepository,
        );

        $grant->setRefreshTokenTTL(new \DateInterval('P1M')); // refresh tokens will expire after 1 month

        // Enable the password grant on the server
        $server->enableGrantType(
            $grant,
            new \DateInterval('PT1H'), // access tokens will expire after 1 hour
        );

        // Enable the client credentials grant on the server
        $server->enableGrantType(
            new \League\OAuth2\Server\Grant\ClientCredentialsGrant(),
            new \DateInterval('PT1H'), // access tokens will expire after 1 hour
        );

        return $server;
    },
);

$container->set(
    ResourceServer::class,
    static function (ContainerInterface $container) {
        $publicKeyPath = 'file://' . OAUTH2_CONFIGPATH . '/public.key';

        return new ResourceServer(
            new AccessTokenRepository(),
            $publicKeyPath,
        );
    },
);

$container->set(
    ClaimExtractor::class,
    static function (ContainerInterface $container) {
        return new ClaimExtractor($container);
    },
);

$container->set(
    IdTokenBuilder::class,
    static function (ContainerInterface $container) {
        $config = $container->get(Config::class);
        $issuer = $config->get('global.issuer', '');

        return new IdTokenBuilder(
            $container,
            OAUTH2_CONFIGPATH . '/private.key',
            $issuer
        );
    },
);

