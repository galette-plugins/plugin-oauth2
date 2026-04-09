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
use GaletteOAuth2\Repositories\AccessTokenRepository;
use GaletteOAuth2\Repositories\AuthCodeRepository;
use GaletteOAuth2\Repositories\ClientRepository;
use GaletteOAuth2\Repositories\RefreshTokenRepository;
use GaletteOAuth2\Repositories\ScopeRepository;
use GaletteOAuth2\Repositories\UserRepository;
use GaletteOAuth2\Repositories\ClaimRepository;
use GaletteOAuth2\Tools\Config;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
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
    ClaimRepository::class,
    static function (ContainerInterface $container) {
        return new ClaimRepository();
    },
);

$container->set(
    AccessTokenRepository::class,
    static function (ContainerInterface $container) {
        return new AccessTokenRepository($container);
    },
);

$container->set(
    UserRepository::class,
    static function (ContainerInterface $container) {
        return new UserRepository($container);
    },
);

$container->set(
    AuthorizationServer::class,
    function (ContainerInterface $container) {
        include OAUTH2_CONFIGPATH . '/encryption-key.php';

        $privateKeyPath = 'file://' . OAUTH2_CONFIGPATH . '/private.key';
        
        if (class_exists(\Idaas\OpenID\CryptKey::class)) {
            $privateKey = new \Idaas\OpenID\CryptKey($privateKeyPath);
            $privateKey->setKid('signing key');
            $responseType = new \Idaas\OpenID\ResponseTypes\BearerTokenResponse();
        } else {
            $privateKey = $privateKeyPath;
            $responseType = new \League\OAuth2\Server\ResponseTypes\BearerTokenResponse();
        }

        // Setup the authorization server
        $server = new AuthorizationServer(
            new ClientRepository($container),
            $container->get(AccessTokenRepository::class),
            new ScopeRepository(),
            $privateKey,
            Key::loadFromAsciiSafeString($encryptionKey),
            $responseType
        );

        $refreshTokenRepository = new RefreshTokenRepository();
        
        if (class_exists(\Idaas\OpenID\Grant\AuthCodeGrant::class)) {
            $claimRepository = $container->get(ClaimRepository::class);
            $grant = new \Idaas\OpenID\Grant\AuthCodeGrant(
                new AuthCodeRepository(),
                $refreshTokenRepository,
                $claimRepository,
                new \Idaas\OpenID\Session(),
                new \DateInterval('PT10M'),
                new \DateInterval('PT10M'),
            );
            $grant->setIssuer('https://' . $_SERVER['HTTP_HOST']);
        } else {
            $grant = new AuthCodeGrant(
                new AuthCodeRepository(),
                $refreshTokenRepository,
                new \DateInterval('PT10M'),
            );
        }

        // Enable the password grant on the server
        // with a token TTL of 1 hour
        $server->enableGrantType(
            $grant,
            // access tokens will expire after 1 hour
            new \DateInterval('PT1H'),
        );

        $rt_grant = new RefreshTokenGrant($refreshTokenRepository);
        // new refresh tokens will expire after 1 month
        $rt_grant->setRefreshTokenTTL(new \DateInterval('P1M'));

        // Enable the refresh token grant on the server
        $server->enableGrantType(
            $rt_grant,
            // new access tokens will expire after an hour
            new \DateInterval('PT1H'),
        );

        //--
        $userRepository = $container->get(UserRepository::class); // instance of UserRepositoryInterface
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

        if (class_exists(\Idaas\OpenID\CryptKey::class)) {
            $publicKey = new \Idaas\OpenID\CryptKey($publicKeyPath);
            $publicKey->setKid('signing key');
        } else {
            $publicKey = $publicKeyPath;
        }

        return new ResourceServer(
            $container->get(AccessTokenRepository::class),
            $publicKey,
        );
    },
);

if (class_exists(\Idaas\OpenID\UserInfo::class)) {
    $container->set(
        \Idaas\OpenID\UserInfo::class,
        static function (ContainerInterface $container) {
            return new \Idaas\OpenID\UserInfo(
                $container->get(UserRepository::class),
                $container->get(AccessTokenRepository::class),
                $container->get(ResourceServer::class),
                $container->get(ClaimRepository::class),
            );
        },
    );
}
