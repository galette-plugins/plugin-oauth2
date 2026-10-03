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

use Analog\Analog;
use Galette\Core\Preferences;
use GaletteOAuth2\Repositories\AccessTokenRepository;
use GaletteOAuth2\Repositories\AuthCodeRepository;
use GaletteOAuth2\Repositories\ClientRepository;
use GaletteOAuth2\Repositories\RefreshTokenRepository;
use GaletteOAuth2\Repositories\ScopeRepository;
use GaletteOAuth2\Tools\Config;
use GaletteOAuth2\Tools\EncryptionKey;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use Psr\Container\ContainerInterface;
use RKA\SessionMiddleware;
use Slim\Flash\Messages;

/** @var \Slim\Routing\RouteCollectorProxy<\DI\Container> $app */
$container = $app->getContainer();

$container->set(
    'oauth_session',
    function (ContainerInterface $container) {
        $session_name = PREFIX_DB . '_' . NAME_DB . '_' . str_replace('.', '_', GALETTE_VERSION);
        $session_name = 'galette_oauth_' . $session_name;
        $session = new SessionMiddleware([
            'name'      => $session_name,
            'lifetime'  => (int)$container->get(Preferences::class)->getConfigValue('pref_session_timeout')
        ]);

        //close Galette session; OAuth one has its own cookie, so its identifier can be renewed on login
        session_write_close();
        $sid = $_COOKIE[$session_name] ?? '';
        if (!is_string($sid) || !preg_match('/^[a-zA-Z0-9,-]{22,256}$/', $sid)) {
            $sid = session_create_id('galette-oauth-');
        }
        session_id($sid);
        $session->start();

        $container->get(Messages::class)->__construct($_SESSION);
        return new \RKA\Session();
    }
);

$container->set(
    Config::class,
    static fn() => Config::fromFile(OAUTH2_CONFIGPATH . '/config.yml')
);

$container->set(
    AuthorizationServer::class,
    function (ContainerInterface $container) {
        // Setup the authorization server
        $server = new AuthorizationServer(
            $container->get(ClientRepository::class),
            $container->get(AccessTokenRepository::class),
            $container->get(ScopeRepository::class),
            // path to private key
            'file://' . OAUTH2_CONFIGPATH . '/private.key',
            // encryption key
            EncryptionKey::load($container->get(Config::class), OAUTH2_CONFIGPATH),
        );

        $refreshTokenRepository = $container->get(RefreshTokenRepository::class);
        $grant = new AuthCodeGrant(
            $container->get(AuthCodeRepository::class),
            $refreshTokenRepository,
            new DateInterval('PT10M'),
        );

        // Enable the authorization code grant on the server
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

        return $server;
    },
);

$container->set(
    ResourceServer::class,
    static function (ContainerInterface $container) {
        $publicKeyPath = 'file://' . OAUTH2_CONFIGPATH . '/public.key';

        return new ResourceServer(
            $container->get(AccessTokenRepository::class),
            $publicKeyPath,
        );
    },
);
