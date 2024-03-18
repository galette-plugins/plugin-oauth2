<?php

/**
 * Copyright © 2021-2024 The Galette Team
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
use GaletteOAuth2\Tools\Config;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use Psr\Container\ContainerInterface;

$container = $app->getContainer();

$container->set(
    Config::class,
    static function (ContainerInterface $container) {
        $conf = new GaletteOAuth2\Tools\Config(OAUTH2_CONFIGPATH . '/config.yml');
        //$conf->writeFile();

        return $conf;
    },
);

$container->set(
    AuthorizationServer::class,
    function (ContainerInterface $container) {
        include OAUTH2_CONFIGPATH . '/encryption-key.php';

        // Setup the authorization server
        $server = new AuthorizationServer(
        // instance of ClientRepositoryInterface
            new ClientRepository($container),
            // instance of AccessTokenRepositoryInterface
            new AccessTokenRepository(),
            // instance of ScopeRepositoryInterface
            new ScopeRepository(),
            // path to private key
            'file://' . OAUTH2_CONFIGPATH . '/private.key',
            // encryption key
            Key::loadFromAsciiSafeString($encryptionKey),
        );

        $refreshTokenRepository = new RefreshTokenRepository();
        $grant = new AuthCodeGrant(
            new AuthCodeRepository(),
            // instance of RefreshTokenRepositoryInterface
            $refreshTokenRepository,
            new DateInterval('PT10M'),
        );

        // Enable the password grant on the server
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
