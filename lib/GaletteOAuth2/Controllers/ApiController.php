<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Controllers;

use Analog\Analog;
use DI\Attribute\Inject;
use DI\Container;
use Galette\Controllers\AbstractPluginController;
use GaletteOAuth2\Authorization\UserAuthorizationException;
use GaletteOAuth2\Authorization\UserHelper;
use GaletteOAuth2\Tools\Config;
use GaletteOAuth2\Tools\Debug;
use League\OAuth2\Server\ResourceServer;
use RKA\Session;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Controller for API
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class ApiController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette OAuth2")]
    protected array $module_info;
    protected Container $container;
    protected Config $config;
    #[Inject("oauth_session")]
    protected Session $session;

    /**
     * Default constructor
     *
     * @param Container $container COntainer instance
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = $container->get(Config::class);
        parent::__construct($container);
    }

    public function user(Request $request, Response $response): Response
    {
        Debug::logRequest('api/user()', $request);

        $server = $this->container->get(ResourceServer::class);
        $rep = $server->validateAuthenticatedRequest($request);

        $oauth_user_id = (int)$rep->getAttribute('oauth_user_id'); //SESSION is empty, use decrypted data
        $client_id = $rep->getAttribute('oauth_client_id');
        Debug::log("api/user() load user #{$oauth_user_id}");

        try {
            $data = UserHelper::getUserData(
                $this->container,
                $oauth_user_id,
                UserHelper::getAuthorization($this->config, $client_id),
                UserHelper::mergeScopes(
                    $this->config,
                    $client_id,
                    $rep->getAttribute('oauth_scopes')
                ),
                (bool)$this->config->get($client_id . '.legacy_data', false)
            );
        } catch (UserAuthorizationException $e) {
            UserHelper::logout($this->container);
            Analog::log(
                'api/user() error : ' . $e->getMessage(),
                Analog::ERROR
            );
            $response->getBody()->write(json_encode(['message' => $e->getMessage()]));
            return $response->withStatus(401);
        }

        Debug::log('api/user() return data = ' . Debug::printVar($data));

        $response->getBody()->write(json_encode($data));
        Debug::log('api/user() exit.');

        return $response->withStatus(200);
    }
}
