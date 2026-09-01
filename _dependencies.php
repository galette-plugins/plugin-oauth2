<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Dependencies
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

use Defuse\Crypto\Key;
use Galette\Core\Preferences;
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
            'lifetime'  => (int)$container->get(Preferences::class)->getConfigValue('pref_session_timeout')
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
        $encryptionKey = $container->get(Config::class)->get('global.encryption_key', 'NONE');
        if ($encryptionKey === 'NONE' && file_exists(OAUTH2_CONFIGPATH . '/encryption-key.php')) {
            include OAUTH2_CONFIGPATH . '/encryption-key.php';
        }

        if (empty($encryptionKey) || $encryptionKey === 'NONE') {
            throw new RuntimeException('Encryption key not found!');
        }

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
