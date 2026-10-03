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
use Galette\Controllers\AbstractPluginController;
use GaletteOAuth2\Authorization\UserAuthorizationException;
use GaletteOAuth2\Authorization\UserHelper;
use GaletteOAuth2\Tools\Config;
use GaletteOAuth2\Tools\Debug;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ResponseInterface;
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
    #[Inject]
    protected Config $config;
    #[Inject("oauth_session")]
    protected Session $session;
    #[Inject]
    protected ResourceServer $server;
    #[Inject]
    protected UserHelper $userHelper;

    public function user(Request $request, Response $response): Response|ResponseInterface
    {
        Debug::logRequest('api/user()', $request);

        try {
            $rep = $this->server->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse($response);
        }

        $oauth_user_id = (int)$rep->getAttribute('oauth_user_id'); //SESSION is empty, use decrypted data
        $client_id = $rep->getAttribute('oauth_client_id');
        Debug::log("api/user() load user #{$oauth_user_id}");

        try {
            $data = $this->userHelper->getUserData(
                $oauth_user_id,
                UserHelper::getAuthorization($this->config, $client_id),
                //only scopes the user has consented to, stored in the token
                UserHelper::mergeScopes(
                    null,
                    $client_id,
                    $rep->getAttribute('oauth_scopes')
                ),
                (bool)$this->config->get($client_id . '.legacy_data', false)
            );
        } catch (UserAuthorizationException $e) {
            $this->userHelper->logout();
            Analog::log(
                'api/user() error : ' . $e->getMessage(),
                Analog::ERROR
            );
            return $this->withJson($response, ['message' => $e->getMessage()], 401);
        }

        Debug::log('api/user() exit.');

        return $this->withJson($response, $data);
    }
}
